// Copyright 2015-2017 Espressif Systems (Shanghai) PTE LTD
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//     http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.


#include <stdio.h>
#include <stdlib.h>
#include <string.h>

#include "mbedtls/base64.h"

#include "freertos/FreeRTOS.h"
#include "freertos/task.h"
#include "freertos/semphr.h"
#include "freertos/event_groups.h"
#include "soc/timer_group_struct.h"
#include "driver/periph_ctrl.h"
#include "driver/timer.h"

#include "esp_system.h"
#include "esp_wifi.h"
#include "esp_event_loop.h"
#include "esp_log.h"
#include "esp_err.h"
#include "nvs_flash.h"
#include "esp_http_client.h"

#include "driver/gpio.h"
#include "camera.h"
#include "http_server.h"

#include <lwip/sockets.h>

static void handle_jpg(http_context_t http_ctx, void* ctx);
static esp_err_t event_handler(void *ctx, system_event_t *event);
static void initialize_wifi(void);
static void initialize_wifi_auto(void);
static esp_err_t http_event_handler(esp_http_client_event_t *evt);
static void http_get_task();
static void http_post_task();

static void my_timer_init(int timer_idx, bool auto_reload, double timer_interval_sec);
static void IRAM_ATTR my_timer_isr(void *para);
static void tiny_timer_init();

static const char* TAG = "camera_demo";

static EventGroupHandle_t s_wifi_event_group;
const int CONNECTED_BIT = BIT0;
static ip4_addr_t s_ip_addr;
static camera_pixelformat_t s_pixel_format;

//画像サイズを指定、QVGA、SVGAなど
#define CAMERA_FRAME_SIZE CAMERA_FS_QVGA // CAMERA_FS_VGA,CAMERA_FS_QVGA

#define WIFI_SSID          "tkji"
#define WIFI_PASS          "dankogai"
//#define WIFI_SSID          "HEROZ-LAN1"
//#define WIFI_PASS          "herozheroz123"
//#define WIFI_SSID          "aterm-723858-g"
//#define WIFI_PASS          "kogaidan"
//カメラのIPアドレスを指定
// The IP address that we want our device to have.
#define DEVICE_IP          "192.168.3.201"

// The Gateway address where we wish to send packets.
// This will commonly be our access point.
#define DEVICE_GW          "192.168.3.1" // home:"192.168.0.201", heroz:"192.168.31.254"

// The netmask specification.
#define DEVICE_NETMASK     "255.255.255.0"

#define PING_URL           "http://49.212.141.20/plant/api/record/ping?sensor_id=%d&tag=%s"
#define WEB_URL            "http://49.212.141.20/plant/api/record/image"
#define SENSOR_ID          1001 // home:0-9, heroz:10-19, tkji:1000,1001

#define TIMER_DIVIDER         16  //  Hardware timer clock divider
#define TIMER_SCALE           (TIMER_BASE_CLK / TIMER_DIVIDER)  // convert counter value to seconds
#define TIMER_INTERVAL_SEC   (60.0) // sample test interval for the first timer
#define TEST_WITHOUT_RELOAD   0        // testing will be done without auto reload
#define TEST_WITH_RELOAD      1        // testing will be done with auto reload

typedef struct {
    int type;  // the type of timer's event
    int timer_group;
    int timer_idx;
    uint64_t timer_counter_value;
} timer_event_t;

xQueueHandle timer_queue;

///// MAIN /////
int _counter = 0;

void app_main()
{
    esp_log_level_set("wifi", ESP_LOG_WARN);
    esp_log_level_set("gpio", ESP_LOG_WARN);

    esp_err_t err = nvs_flash_init();
    if (err != ESP_OK) {
        ESP_ERROR_CHECK( nvs_flash_erase() );
        ESP_ERROR_CHECK( nvs_flash_init() );
    }

    ESP_ERROR_CHECK(gpio_install_isr_service(0));

    camera_config_t camera_config = {
        .ledc_channel = LEDC_CHANNEL_0,
        .ledc_timer = LEDC_TIMER_0,
        .pin_d0 = CONFIG_D0,
        .pin_d1 = CONFIG_D1,
        .pin_d2 = CONFIG_D2,
        .pin_d3 = CONFIG_D3,
        .pin_d4 = CONFIG_D4,
        .pin_d5 = CONFIG_D5,
        .pin_d6 = CONFIG_D6,
        .pin_d7 = CONFIG_D7,
        .pin_xclk = CONFIG_XCLK,
        .pin_pclk = CONFIG_PCLK,
        .pin_vsync = CONFIG_VSYNC,
        .pin_href = CONFIG_HREF,
        .pin_sscb_sda = CONFIG_SDA,
        .pin_sscb_scl = CONFIG_SCL,
        .pin_reset = CONFIG_RESET,
        .xclk_freq_hz = CONFIG_XCLK_FREQ,
    };

    camera_model_t camera_model;
    err = camera_probe(&camera_config, &camera_model);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Camera probe failed with error 0x%x", err);
        return;
    }

  if (camera_model == CAMERA_OV2640) { 
       ESP_LOGI(TAG, "Detected OV2640 camera, using JPEG format");
        s_pixel_format = CAMERA_PF_JPEG;
        camera_config.frame_size = CAMERA_FRAME_SIZE;
        camera_config.jpeg_quality = 10;//画質設定（数値が小さいほどよい）default=10
    } else {
        ESP_LOGE(TAG, "Camera not supported");
        return;
    }

    camera_config.pixel_format = s_pixel_format;
    err = camera_init(&camera_config);
    if (err != ESP_OK) {
        ESP_LOGE(TAG, "Camera init failed with error 0x%x", err);
        return;
    }

    //initialize_wifi_auto();
    initialize_wifi();

    http_server_t server;
    http_server_options_t http_options = HTTP_SERVER_OPTIONS_DEFAULT();
    ESP_ERROR_CHECK( http_server_start(&http_options, &server) );

    if (s_pixel_format == CAMERA_PF_JPEG) {
        ESP_ERROR_CHECK( http_register_handler(server, "/jpg", HTTP_GET, HTTP_HANDLE_RESPONSE, &handle_jpg, NULL) );
        ESP_LOGI(TAG, "Open http://" IPSTR "/jpg for single image/jpg image", IP2STR(&s_ip_addr));
    }
    ESP_LOGI(TAG, "Free heap: %u", xPortGetFreeHeapSize());
    ESP_LOGI(TAG, "Camera demo ready...");
	
	//tiny_timer_init();
	my_timer_init(TIMER_0, true, TIMER_INTERVAL_SEC);
}

static void handle_jpg(http_context_t http_ctx, void* ctx)
{
    ESP_LOGI(TAG, "Handling jpg");
	esp_err_t err = camera_run();
    if (err != ESP_OK) {
        ESP_LOGD(TAG, "Camera capture failed with error = %d", err);
        return;
    }

	//http_get_task();
    http_post_task();

    http_response_begin(http_ctx, 200, "image/jpeg", camera_get_data_size());
    http_response_set_header(http_ctx, "Content-disposition", "inline; filename=capture.jpg");
    http_buffer_t fb_data = {
            .data = camera_get_fb(),
            .size = camera_get_data_size(),
            .data_is_persistent = true
    };
    http_response_write(http_ctx, &fb_data);
    http_response_end(http_ctx);
}

static esp_err_t event_handler(void *ctx, system_event_t *event)
{
    switch (event->event_id) {
        case SYSTEM_EVENT_STA_START:
            esp_wifi_connect();
            break;
        case SYSTEM_EVENT_STA_GOT_IP:
            xEventGroupSetBits(s_wifi_event_group, CONNECTED_BIT);
            s_ip_addr = event->event_info.got_ip.ip_info.ip;
            break;
        case SYSTEM_EVENT_STA_DISCONNECTED:
            esp_wifi_connect();
            xEventGroupClearBits(s_wifi_event_group, CONNECTED_BIT);
            break;
        default:
            break;
    }
    return ESP_OK;
}

static void initialize_wifi(void)
{
    tcpip_adapter_init();
    tcpip_adapter_dhcpc_stop(TCPIP_ADAPTER_IF_STA); // Don't run a DHCP client
    tcpip_adapter_ip_info_t ipInfo;

    inet_pton(AF_INET, DEVICE_IP, &ipInfo.ip);
    inet_pton(AF_INET, DEVICE_GW, &ipInfo.gw);
    inet_pton(AF_INET, DEVICE_NETMASK, &ipInfo.netmask);

    tcpip_adapter_set_ip_info(TCPIP_ADAPTER_IF_STA, &ipInfo);

    s_wifi_event_group = xEventGroupCreate();
    ESP_ERROR_CHECK( esp_event_loop_init(event_handler, NULL) );
    wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
    ESP_ERROR_CHECK( esp_wifi_init(&cfg) );
    ESP_ERROR_CHECK( esp_wifi_set_storage(WIFI_STORAGE_RAM) );
    wifi_config_t wifi_config = {
        .sta = {
            .ssid = WIFI_SSID,
            .password = WIFI_PASS,
        },
    };
    ESP_ERROR_CHECK( esp_wifi_set_mode(WIFI_MODE_STA) );
    ESP_ERROR_CHECK( esp_wifi_set_config(WIFI_IF_STA, &wifi_config) );
    ESP_ERROR_CHECK( esp_wifi_start() );
    ESP_ERROR_CHECK( esp_wifi_set_ps(WIFI_PS_NONE) );
    ESP_LOGI(TAG, "Connecting to \"%s\"", wifi_config.sta.ssid);
    xEventGroupWaitBits(s_wifi_event_group, CONNECTED_BIT, false, true, portMAX_DELAY);
    ESP_LOGI(TAG, "Connected");
}

static void initialize_wifi_auto(void)
{
	tcpip_adapter_init();
	ESP_ERROR_CHECK(esp_event_loop_init(event_handler, NULL));

	wifi_init_config_t cfg = WIFI_INIT_CONFIG_DEFAULT();
	ESP_ERROR_CHECK(esp_wifi_init(&cfg));
	wifi_config_t wifi_config = {
		.sta = {
			.ssid = WIFI_SSID,
			.password = WIFI_PASS,
			.scan_method = WIFI_ALL_CHANNEL_SCAN,
			.sort_method = WIFI_CONNECT_AP_BY_SIGNAL,
			.threshold.rssi = -127,
			.threshold.authmode = WIFI_AUTH_OPEN,
		},
	};
	ESP_ERROR_CHECK(esp_wifi_set_mode(WIFI_MODE_STA));
	ESP_ERROR_CHECK(esp_wifi_set_config(ESP_IF_WIFI_STA, &wifi_config));
	ESP_ERROR_CHECK(esp_wifi_start());
}

static esp_err_t http_event_handler(esp_http_client_event_t *evt)
{
    switch(evt->event_id) {
        case HTTP_EVENT_ERROR:
            ESP_LOGD(TAG, "HTTP_EVENT_ERROR");
            break;
        case HTTP_EVENT_ON_CONNECTED:
            ESP_LOGD(TAG, "HTTP_EVENT_ON_CONNECTED");
            break;
        case HTTP_EVENT_HEADER_SENT:
            ESP_LOGD(TAG, "HTTP_EVENT_HEADER_SENT");
            break;
        case HTTP_EVENT_ON_HEADER:
            ESP_LOGD(TAG, "HTTP_EVENT_ON_HEADER, key=%s, value=%s", evt->header_key, evt->header_value);
            break;
        case HTTP_EVENT_ON_DATA:
            ESP_LOGD(TAG, "HTTP_EVENT_ON_DATA, len=%d", evt->data_len);
            if (!esp_http_client_is_chunked_response(evt->client)) {
                // Write out data
                // printf("%.*s", evt->data_len, (char*)evt->data);
            }

            break;
        case HTTP_EVENT_ON_FINISH:
            ESP_LOGD(TAG, "HTTP_EVENT_ON_FINISH");
            break;
        case HTTP_EVENT_DISCONNECTED:
            ESP_LOGD(TAG, "HTTP_EVENT_DISCONNECTED");
            break;
    }
    return ESP_OK;
}

static void http_get_task(char* tag)
{
	char url[256];
	sprintf(url, PING_URL, SENSOR_ID, tag);
    esp_http_client_config_t config = {
        .url = url,
        .event_handler = http_event_handler,
    };
    esp_http_client_handle_t client = esp_http_client_init(&config);
	//ESP_LOGI(TAG, "Getting to url=%s, size=%d", config.url, camera_get_data_size());

    // GET
    esp_err_t err = esp_http_client_perform(client);
    if (err == ESP_OK) {
        ESP_LOGI(TAG, "HTTP GET Status = %d, content_length = %d", esp_http_client_get_status_code(client), esp_http_client_get_content_length(client));
    } else {
        ESP_LOGE(TAG, "HTTP GET request failed: %s", esp_err_to_name(err));
    }
}

static void http_post_task()
{
	// ready for camera
	esp_err_t err = camera_run();
	if (err != ESP_OK) {
		ESP_LOGD(TAG, "Camera capture failed with error = %d", err);
		return;
	}
	size_t size = camera_get_data_size();
	if(size == 0)
	{
		http_get_task("empty_image");
		return;
	}
	
	esp_http_client_config_t config = {
		.url = WEB_URL,
		.event_handler = http_event_handler
	};
	esp_http_client_handle_t client = esp_http_client_init(&config);

	const char *boundary = "----WebKitFormBoundaryO5quBRiT4G7Vm3R7";
	char head_type[80];
	char head_length[32];
	char body_front[512] = "";
	char body_back[64];
	char tmp[256];
	sprintf(head_type, "multipart/form-data; boundary=%s", boundary);
	strcat(body_front, "--"); strcat(body_front, boundary); strcat(body_front, "\r\n");
	sprintf(tmp, "Content-Disposition: form-data; name=\"sensor_id\"\r\n\r\n%d\r\n", SENSOR_ID); strcat(body_front, tmp);
	strcat(body_front, "--"); strcat(body_front, boundary); strcat(body_front, "\r\n");
	strcat(body_front, "Content-Disposition: form-data; name=\"image\"; filename=\"esp32.jpg\"\r\n");
	strcat(body_front, "Content-Type: image/jpeg\r\n");
	sprintf(tmp, "Content-Length: %d\r\n\r\n", size); strcat(body_front, tmp);
	sprintf(body_back, "\r\n--%s--", boundary);
	int length = strlen(body_front) + strlen(body_back) + size;
	sprintf(head_length, "%d", length);
    ESP_LOGI(TAG, "Posting to %s size=%d", config.url, length);

	esp_http_client_set_header(client, "Content-Type", head_type);
	esp_http_client_set_header(client, "Content-Length", head_length);
    esp_http_client_set_method(client, HTTP_METHOD_POST);
	esp_http_client_open(client, length);
	esp_http_client_write(client, (const char*)body_front, strlen(body_front));
	esp_http_client_write(client, (const char*)camera_get_fb(), camera_get_data_size());
	esp_http_client_write(client, (const char*)body_back, strlen(body_back));

	length = esp_http_client_fetch_headers(client);
	int code = esp_http_client_get_status_code(client);
	if(length > 0) esp_http_client_read(client, tmp, length > 255 ? 255: length);
	else length = esp_http_client_read(client, tmp, 255);
	ESP_LOGI(TAG, "HTTP POST Status = %d, content_length = %d\n>%s\n>%s\n>%s\n>%s", code, length, head_type, body_front, body_back, tmp);

	esp_http_client_close(client);
	esp_http_client_cleanup(client);
}

static void tiny_timer_init()
{
	http_get_task("timer_init");
	while(1)
	{
		sleep(60);
		_counter = (_counter+1) % 15;
		if(_counter != 2) continue;
		
		esp_err_t err = camera_run();
		if (err != ESP_OK) {
			printf(TAG, "Camera capture failed with error = %d", err);
			continue;
		}
		http_post_task();
	}
}


static void my_timer_init(int timer_idx, bool auto_reload, double timer_interval_sec)
{
	// init queue
    timer_queue = xQueueCreate(10, sizeof(timer_event_t));

	/* Select and initialize basic parameters of the timer */
	timer_config_t config;
	config.divider = TIMER_DIVIDER;
	config.counter_dir = TIMER_COUNT_UP;
	config.counter_en = TIMER_PAUSE;
	config.alarm_en = TIMER_ALARM_EN;
	config.intr_type = TIMER_INTR_LEVEL;
	config.auto_reload = auto_reload;
	timer_init(TIMER_GROUP_0, timer_idx, &config);

	/* Timer's counter will initially start from value below.
	   Also, if auto_reload is set, this value will be automatically reload on alarm */
	timer_set_counter_value(TIMER_GROUP_0, timer_idx, 0x00000000ULL);

	/* Configure the alarm value and the interrupt on alarm. */
	timer_set_alarm_value(TIMER_GROUP_0, timer_idx, timer_interval_sec * TIMER_SCALE);
	timer_enable_intr(TIMER_GROUP_0, timer_idx);
	timer_isr_register(TIMER_GROUP_0, timer_idx, my_timer_isr, (void *) timer_idx, ESP_INTR_FLAG_IRAM, NULL);

	timer_start(TIMER_GROUP_0, timer_idx);
	
	while (1) {
		timer_event_t evt;
		xQueueReceive(timer_queue, &evt, portMAX_DELAY);
		int cnt = evt.timer_counter_value;
		
		if(cnt % 15 == 1) http_post_task();
		ESP_LOGI(TAG, "counter=%d", cnt);
	}
	// Not reach here.
}

static void IRAM_ATTR my_timer_isr(void *para)
{
    int timer_idx = (int) para;

	/* Retrieve the interrupt status and the counter value from the timer that reported the interrupt */
	TIMERG0.hw_timer[timer_idx].update = 1;
//	uint64_t timer_counter_value = 
//		((uint64_t) TIMERG0.hw_timer[timer_idx].cnt_high) << 32
//		| TIMERG0.hw_timer[timer_idx].cnt_low;

	/* Prepare basic event data that will be then sent back to the main program task */
	timer_event_t evt;
	evt.timer_group = 0;
	evt.timer_idx = timer_idx;
	evt.timer_counter_value = _counter;

	/* Clear the interrupt and update the alarm time for the timer with without reload */
	evt.type = TEST_WITH_RELOAD;
	TIMERG0.int_clr_timers.t0 = 1;
//	timer_counter_value += (uint64_t) (TIMER_INTERVAL_SEC * TIMER_SCALE);
//	TIMERG0.hw_timer[timer_idx].alarm_high = (uint32_t) (timer_counter_value >> 32);
//	TIMERG0.hw_timer[timer_idx].alarm_low = (uint32_t) timer_counter_value;

	/* After the alarm has been triggered we need enable it again, so it is triggered the next time */
	TIMERG0.hw_timer[timer_idx].config.alarm_en = TIMER_ALARM_EN;

	/* Now just send the event data back to the main program task */
	xQueueSendFromISR(timer_queue, &evt, NULL);

	_counter++;
	_counter = _counter % 60;
}

