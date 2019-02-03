const Obniz = require("obniz");
const request = require("request");
const FormData = require('form-data');
const fs = require('fs');

function Measuring(id, name)
{
	const COOL_DOWN = 30;
	var _name = name;
	var _id = id;
	var _temperatures = [];
	var _co2s = [];
	var _luxs = [];

	this.get_median = function(list)
	{
		if(list.length == 0) return 0;
		if(list.length == 1) return list[0];

		list.sort();
		var o = list[ Math.floor(list.length / 2) ];
		return o;
	}

	this.calculate_lux_200 = function(v)
	{
		if(v < 0.0) return 0;
		return 11167 * v + 188;
	}

	this.save_temperature = function(id, t)
	{
		request.get({
			url: "http://49.212.141.20/Imager/temperature.php",
			qs: { sensor: id, temperature: t }
			},
			function(err, res, body){ dump("temperature="+body); }
		);
	}

	this.save_co2 = function(id, c)
	{
		request.get({
			url: "http://49.212.141.20/Imager/co2.php",
			qs: { sensor: id, co2: c }
			},
			function(err, res, body){ dump("co2="+body); }
		);
	}

	this.save_lux = function(id, l)
	{
		request.get({
			url: "http://49.212.141.20/Imager/lux.php",
			qs: { sensor: id, lux: l }
			},
			function(err, res, body){ dump("lux="+body); }
		);
	}
	function addZero(n){ if(n < 10) return "0" + n; else return n.toString(); }
        function getDateTimeString()
	{
		var now = new Date();
		var year = now.getFullYear().toString().substr(2,2);
		var month = addZero(now.getMonth() + 1);
		var day = addZero(now.getDate());
		var hours = addZero(now.getHours());
		return year + month + day + hours + "00";
	}

	function print(mes)
	{
		_obniz.display.clear();
		_obniz.display.print(mes);
		console.log(mes);
	}
	function dump(mes)
	{
		console.log(mes);
	}

	var _obniz = new Obniz(""+_name);
	print("started...");
	_obniz.onconnect = async function ()
	{
		// initialize
		print("initialized...");
		var temperature_sensor = _obniz.wired("LM35DZ",	{gnd:0 , output:1, vcc:2});
		var co2_sensor = _obniz.getFreeUart();
		co2_sensor.start({tx: 3, rx: 4, baud:9600, bits:8, stop:1, parity:"off", flowcontrol:"off"});
		co2_sensor.send([0xFF, 0x01, 0x79, 0xA0, 0x00, 0x00, 0x00, 0x00, 0xE6]);
		//await _obniz.wait(30*1000);
		// measuring
		print("measuring...");
		temperature_sensor.onchange = function(temp){ _temperatures.push(temp); };
		setInterval(async function(){
			var command = [0xFF, 0x01, 0x86, 0x00, 0x00, 0x00, 0x00, 0x00, 0x79];
			co2_sensor.send(command);
			await _obniz.wait(100);
			var res = co2_sensor.readBytes();
			console.log(res);
			if(res.length != 9) return;
			if(res[1] != 134) return;
			_co2s.push(res[2] * 256 + res[3]);
		}, 2000);
		_obniz.ad9.start();
		_obniz.ad10.start();
		_obniz.ad11.start();
		setInterval(async function(){
			var vi = _obniz.ad11.value;
			var v = _obniz.ad10.value;
			var vg = _obniz.ad9.value;
			//dump([vg, v, vi]);
			_luxs.push(vi - v);
		}, 1000);
		// saving
		setTimeout(save, 60*1000);
		// camera
		print("camera..")
		var cam = _obniz.wired("JpegSerialCam", {cam_tx:5, cam_rx:6});
		await cam.startWait({baud: 38400});
		await cam.setSizeWait("320x240");
		const jpeg = await cam.takeWait();
		print("length="+jpeg.length);
		var base64 = cam.arrayToBase64(jpeg);
		var bitmap = new Buffer(base64, 'base64');
		fs.writeFileSync("tmp.jpg", bitmap);
		var buffer = fs.readFileSync("tmp.jpg");
		var filename = getDateTimeString() + ".jpg";
		print("uploading... filename=" + filename);
		const form = new FormData();
		form.append("userfile", bitmap, {filename: filename, contentType: "image/jpeg", /*knownLength: jpeg.length*/});
		form.submit("http://49.212.141.20/Imager/upload/home", function(err,res)
			{
				console.log("code=" + res.statusCode + " body=" + res.statusMessage);
			});
	}

	async function save()
	{
		var t = get_median(_temperatures);
		var c = get_median(_co2s);
		var vl = get_median(_luxs);
		var l = calculate_lux_200(vl);
		save_temperature(_id, t);
		save_co2(_id, c);
		save_lux(_id, l);
		print("saved temp="+t+",co2="+c+",lux="+l);
	};

	// Task Kill
	setTimeout(function(){ _obniz.close(); process.exit(); }, (COOL_DOWN+60)*1000);

	var _that = this;
	return this;
};

var instance = Measuring(1, "6860-8688");
