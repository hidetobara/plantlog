import os,sys,string,traceback,random,glob,json,datetime,argparse,math,time
import asyncio
import urllib.request
from obniz import Obniz


class ObnizWithDevice:
    """
    Obnizを使ったデバイス制御
    """

    def __init__(self, obniz_id):
        print("obniz>", obniz_id)
        self.obniz = Obniz(obniz_id)
        self.data = {}
        self.events = []

    def store(self, name, value):
        if name not in self.data:
            self.data[name] = []
        self.data[name].append(value)

    def calculate_median(self, name):
        if name not in self.data:
            return None
        array = sorted(self.data[name])
        return array[ int(len(array)/2) ]

    def get_humidity(self):
        return self.calculate_median("humidity")
    def get_temperature(self):
        t = self.calculate_median("temperature")
        return t if t is not None else self.calculate_median("temperature_")
    def get_lux(self):
        return self.calculate_median("lux")
    def get_pressure(self):
        return self.calculate_median("pressure")
    def get_co2(self):
        return self.calculate_median("co2")

    def run_power(self, obniz, gnd, vdd, voltage="3v"):
        if vdd is not None:
            obniz.get_io(vdd).pull(voltage)
            obniz.get_io(vdd).output(True)
        if gnd is not None:
            obniz.get_io(gnd).output(False)
        print("power>", gnd, vdd, voltage)

    def stop_power(self, obniz, gnd, vdd):
        if vdd is not None:
            obniz.get_io(vdd).end()
        if gnd is not None:
            obniz.get_io(gnd).end()

    def set_tept4400(self, ad=1, gnd=0, vdd=2):
        def calculate_lux(vol):
            if vol < 0.0: return 0
            lux = 7232.4 * vol + 2.5
            return math.floor(lux / 100) * 100

        async def on_tept4400(obniz):
            self.run_power(obniz, gnd, vdd, "5v")
            land = obniz.__dict__['ad{}'.format(ad)]
            land.start()
            drain = obniz.__dict__['ad{}'.format(vdd)]
            drain.start()
            await obniz.wait(3000)
            for _ in range(0, 10):
                print("tept4400>", land.value, drain.value)
                self.store("lux", calculate_lux(drain.value - land.value))
                await obniz.wait(1000)

        self.events.append(on_tept4400)
        return self

    def set_5v(self, gnd=None, vdd=5, seconds=3):
        async def on_5v(obniz):
            self.run_power(obniz, gnd, vdd, "5v")
            await obniz.wait(seconds * 1000)
            self.stop_power(obniz, gnd, vdd)
            await obniz.wait(1000)
        self.events.append(on_5v)
        return self

    def set_dht12(self, sda=7, scl=9, gnd=11, vdd=5):
        ADR = 0x5c
        async def on_dht12(obniz):
            self.run_power(obniz, gnd, vdd)
            await obniz.wait(3000)
            i2c = obniz.i2c0
            i2c.start( {"mode":"master", "sda":sda, "scl":scl, "clock":100000, "pull":None} )
            i2c.onerror = lambda e: print("dht12", e)
            for _ in range(0, 10):
                i2c.write(ADR, [0x00])
                r = await i2c.read_wait(ADR, 5)
                chk = (r[0] + r[1] + r[2] + r[3]) & 0xff
                print("dht>", r)
                if chk != r[4]: continue
                self.store("humidity", r[0] + r[1]*0.1)
                self.store("temperature", r[2] + r[3]*0.1)
                await obniz.wait(1000)

        self.events.append(on_dht12)
        return self

    def set_ccs811(self, sda=10, scl=11):
        """
        need VDD 1.8-3.6V
        """
        ADR = 0x5B
        async def on_ccs811(obniz):
            i2c = obniz.i2c0
            i2c.start( {"mode":"master", "sda":sda, "scl":scl, "clock":100000, "pull":"3v"} )
            i2c.onerror = lambda e: print("ccs811", e)
            #i2c.write(ADR, [0x20])
            #r = await i2c.read_wait(ADR, 1)
            #print("HW=", r)
            #i2c.write(ADR, [0xFF, 0x11, 0xE5, 0x72, 0x8A])  # reset
            i2c.write(ADR, [0xF4])  # app start
            i2c.write(ADR, [0x01, 0x10])  # every 1sec
            await obniz.wait(3000)
            for _ in range(0, 10):
                await obniz.wait(1000)
                i2c.write(ADR, [0x02])
                r = await i2c.read_wait(ADR, 8)
                print("ccs811>", r)
                if r[4] != 144: continue
                eco2 = r[0] << 8 | r[1]
                tvoc = r[2] << 8 | r[3]
                if eco2 == 0: continue
                self.store("co2", eco2)
                self.store("tvoc", tvoc)
            i2c.write(ADR, [0x01, 0x00])  # sleep
            await obniz.wait(1)
        self.events.append(on_ccs811)
        return self

    def set_tsl2561(self, sda=1, scl=0, gnd=2, vdd=3):
        ADR = 0x39
        GAINS = {0x00: 16.0*322.0/11.0, 0x01: 16.0*322.0/81.0, 0x02: 16.0*1.0, 0x12: 1.0*1.0}
        GAIN = 0x00 # Need modify gain.
        def getScale():
            return GAINS[GAIN]
        def calculate_lux(int0, int1):
            ch0 = int0 * getScale()
            ch1 = int1 * getScale()

            if ch0 == 0: return 0.0
            ratio = ch1 / ch0
            lux = 0.0
            if ratio >= 0 and ratio <= 0.50:
                lux = 0.0304 * ch0 - 0.062 * ch0 * math.pow(ratio, 1.4)
            elif ratio <= 0.61:
                lux = 0.0224 * ch0 - 0.031 * ch1
            elif ratio <= 0.80:
                lux = 0.0128 * ch0 - 0.0153 * ch1
            elif ratio <= 1.30:
                lux = 0.00146 * ch0 - 0.00112 * ch1
            return lux

        async def on_tsl2561(obniz):
            self.run_power(obniz, gnd, vdd)
            await obniz.wait(3000)
            i2c = obniz.i2c0
            i2c.start( {"mode":"master", "sda":sda, "scl":scl, "clock":100000, "pull":"3v"} )
            i2c.onerror = lambda e: print("tsl2561", e)
            i2c.write(ADR, [0x80, 0x03])
            i2c.write(ADR, [0x81, GAIN])
            for _ in range(0, 10):
                i2c.write(ADR, [0xAC])
                c0 = await i2c.read_wait(ADR, 2)
                ch0 = c0[1] * 256 + c0[0]
                i2c.write(ADR, [0xAE])
                c1 = await i2c.read_wait(ADR, 2)
                ch1 = c1[1] * 256 + c1[0]
                print("tsl2561>", ch0, ch1)
                self.store("lux", calculate_lux(ch0, ch1))
                await obniz.wait(1000)
        
        self.events.append(on_tsl2561)
        return self

    def set_bmp280(self, sda=7, scl=9, gnd=11, vdd=5):
        ADR = 0x76

        class bmp280:
            """
            http://www.neko.ne.jp/~freewing/raspberry_pi/raspberry_pi_3_i2c_pressure_bmp280/
            """
            def __init__(self, i2c):
                self.i2c = i2c
                self.t_fine = 0
                self.cal_T1 = 0
                self.cal_T2 = 0
                self.cal_T3 = 0
                self.cal_P1 = 0
                self.cal_P2 = 0
                self.cal_P3 = 0
                self.cal_P4 = 0
                self.cal_P5 = 0
                self.cal_P6 = 0
                self.cal_P7 = 0
                self.cal_P8 = 0
                self.cal_P9 = 0

            async def get_value_temp(self):
                val = await self.get_value(0xFA)
                val = val>>4

                val_1 = ((((val>>3) - (self.cal_T1 <<1))) * (self.cal_T2)) >> 11
                val_2 = (((((val>>4) - (self.cal_T1)) * ((val>>4) - (self.cal_T1))) >> 12) * (self.cal_T3)) >> 14
                val_f = val_1 + val_2
                self.t_fine = val_f
                return ((val_f * 5 + 128) >> 8) / 100.0

            async def get_value_pres(self):
                val = await self.get_value(0xF7)
                val = val>>4

                val_1 = (self.t_fine) - 128000
                val_2 = val_1 * val_1 * self.cal_P6
                val_2 = val_2 + ((val_1*self.cal_P5)<<17)
                val_2 = val_2 + ((self.cal_P4)<<35)
                val_1 = ((val_1 * val_1 * self.cal_P3)>>8) + ((val_1 * self.cal_P2)<<12)
                val_1 = ((((1)<<47)+val_1))*(self.cal_P1)>>33
                if val_1 == 0: return 0

                p = 1048576 - val
                p = int( (((p<<31) - val_2)*3125) / val_1 )
                val_1 = ((self.cal_P9) * (p>>13) * (p>>13)) >> 25
                val_2 = ((self.cal_P8) * p) >> 19

                p = ((p + val_1 + val_2) >> 8) + ((self.cal_P7)<<4)
                return p/256.0

            async def get_value_alti(self):
                seaLevelhPa = 1013.25

                p = await self.get_value_pres()
                p = p/100.0
                alti = 44330 * (1.0 - pow(p / seaLevelhPa, 0.1903))
                return alti

            async def get_value16(self, adr):
                self.i2c.write(ADR, [adr])
                data = await self.i2c.read_wait(ADR, 2)
                tmp = data[1]
                tmp = tmp<<8
                tmp = tmp | data[0]
                return tmp

            async def get_value16s(self, adr):
                self.i2c.write(ADR, [adr])
                data = await self.i2c.read_wait(ADR, 2)
                tmp = data[1]
                sign = tmp & 0x80
                tmp = tmp & 0x7F
                tmp = tmp<<8
                tmp = tmp | data[0]
                if sign > 0:
                    tmp = tmp - 32768
                return tmp

            async def get_value(self, adr):
                self.i2c.write(ADR, [adr])
                data = await self.i2c.read_wait(ADR, 3)
                tmp = data[0]
                tmp = tmp<<8
                tmp = tmp | data[1]
                tmp = tmp<<8
                tmp = tmp | data[2]
                tmp = tmp & 0xFFFFFF
                return tmp

            async def prepare(self):
                self.i2c.write(ADR, [0xE0, 0xB6])
                await asyncio.sleep(0.1)
                self.i2c.write(ADR, [0xD0])
                data = await self.i2c.read_wait(ADR, 1)
                #print("ID=", data)
                self.i2c.write(ADR, [0xF4, 0x6F])
                self.cal_T1 = await self.get_value16(0x88)
                self.cal_T2 = await self.get_value16s(0x8A)
                self.cal_T3 = await self.get_value16s(0x8C)
                self.cal_P1 = await self.get_value16(0x8E)
                self.cal_P2 = await self.get_value16s(0x90)
                self.cal_P3 = await self.get_value16s(0x92)
                self.cal_P4 = await self.get_value16s(0x94)
                self.cal_P5 = await self.get_value16s(0x96)
                self.cal_P6 = await self.get_value16s(0x98)
                self.cal_P7 = await self.get_value16s(0x9A)
                self.cal_P8 = await self.get_value16s(0x9C)
                self.cal_P9 = await self.get_value16s(0x9E)

        async def on_bmp280(obniz):
            self.run_power(obniz, gnd, vdd)
            await obniz.wait(3000)
            i2c = obniz.i2c0
            i2c.start( {"mode":"master", "sda":sda, "scl":scl, "clock":100000, "pull":None} )
            i2c.onerror = lambda e: print("bmp280", e)
            d = bmp280(i2c)
            await d.prepare()
            for _ in range(0, 10):
                t = await d.get_value_temp()
                p = await d.get_value_pres()
                self.store("temperature_", t) # ignore
                self.store("pressure", p)
                print("bmp280>", t, p)
                await obniz.wait(1000)
            
        self.events.append(on_bmp280)
        return self

    def run(self):
        try:
            async def on_events(obniz):
                for handler in self.events:
                    await handler(obniz)
                print("ending...")
                asyncio.get_event_loop().stop()
            self.obniz.onconnect = on_events
            asyncio.get_event_loop().run_until_complete(sleeping(60))
        except Exception as ex:
            print('ERROR=', ex)
            asyncio.get_event_loop().stop()
        finally:
            self.obniz.close()

def update_value(sensor, key, value):
    try:
        if value is None: return
        url = 'http://49.212.141.20/plant/api/record/update_{}?sensor={}&{}={}'.format(key, sensor, key, value)
        req = urllib.request.Request(url)
        with urllib.request.urlopen(req) as res:
            print("OK=", key, res.read())
    except Exception as ex:
        print("ERROR=", url, ex)

async def sleeping(sec):
    loop = asyncio.get_event_loop()
    await loop.run_in_executor(None, time.sleep, sec)

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument('--obniz_id', type=str, required=True, help='ID of obniz')
    parser.add_argument('--pid', type=int, required=True, help='ID of plant')
    parser.add_argument('--water', action='store_true')
    parser.add_argument('--sensing', action='store_true')
    parser.add_argument('--hoge', action='store_true')
    args = parser.parse_args()

    if args.hoge:
        d = ObnizWithDevice(obniz_id=args.obniz_id)
        d.set_ccs811().run()
        print("co2=", d.get_co2())
        update_value(args.pid, "co2", d.get_co2())

    if args.sensing:
        d = ObnizWithDevice(obniz_id=args.obniz_id)
        d.set_tept4400(ad=0, gnd=None, vdd=1) \
            .set_dht12(sda=3, scl=4, gnd=None, vdd=2) \
            .set_bmp280(sda=3, scl=4, gnd=None, vdd=2) \
            .run()
        print("temperature=", d.get_temperature())
        print("humidity=", d.get_humidity())
        print("pressure=", d.get_pressure())
        print("lux=", d.get_lux())
        update_value(args.pid, "temperature", d.get_temperature())
        update_value(args.pid, "lux", d.get_lux())
        update_value(args.pid, "pressure", d.get_pressure())
        update_value(args.pid, "humidity", d.get_humidity())

    if args.water:
        d = ObnizWithDevice(obniz_id=args.obniz_id)
        d.set_5v(gnd=None, vdd=5, seconds=10).run()

