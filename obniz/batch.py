import os,sys,string,traceback,random,glob,json,datetime,argparse,math
import asyncio
from obniz import Obniz


class DeviceWithObniz:
    """
    Obnizを使ったデバイス制御
    """

    def __init__(self, obid='0167-3051'):
        self.obniz = Obniz(obid)
        self.data = {}

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
        return self.calculate_median("temperature")
    def get_lux(self):
        return self.calculate_median("lux")
    def get_atmosphere(self):
        return self.calculate_median("atmosphere")

    def run_power(self, obniz, gnd, vdd):
        if vdd is not None:
            obniz.__dict__['io{}'.format(vdd)].pull("3v")
            obniz.__dict__['io{}'.format(vdd)].output(True)
        if gnd is not None:
            obniz.__dict__['io{}'.format(gnd)].output(False)

    def set_tept4400(self, ad=1, gnd=0, vdd=2):
        def calculate_lux(vol):
            if vol < 0.0: return 0
            lux = 7232.4 * vol + 2.5
            return math.floor(lux / 100) * 100

        async def on_tept4400(obniz):
            self.run_power(gnd, vdd)
            land = obniz.__dict__['ad{}'.format(ad)]
            land.start()
            drain = obniz.__dict__['ad{}'.format(vdd)]
            drain.start()
            await obniz.wait(3000)
            for _ in range(0, 10):
                print("tept4400>", land.value, drain.value)
                self.store("lux", calculate_lux(drain.value - land.value))
                await obniz.wait(1000)
            obniz.close()
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_tept4400
        return self

    def set_dht12(self, sda=7, scl=9, gnd=11, vdd=5):
        ADR = 0x5c
        async def on_dht12(obniz):
            self.run_power(obniz, gnd, vdd)
            await obniz.wait(3000)
            i2c = obniz.i2c0
            i2c.start( {"mode":"master", "sda":sda, "scl":scl, "clock":100000, "pull":"5v"} )
            i2c.onerror = lambda e: print("dht12", e)
            for _ in range(0, 10):
                i2c.write(ADR, [0x00])
                r = await i2c.read_wait(ADR, 5)
                chk = (r[0] + r[1] + r[2] + r[3]) & 0xff
                print("dht>", r, chk)
                if chk != r[4]: continue
                self.store("humidity", r[0] + r[1]*0.1)
                self.store("temperature", r[2] + r[3]*0.1)
                await obniz.wait(1000)
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_dht12
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
            i2c.start( {"mode":"master", "sda":sda, "scl":scl, "clock":100000, "pull":"5v"} )
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
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_tsl2561
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
                return (p/256.0) / 100.0

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
                print("ID=", data)
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
                self.store("temperature", t)
                self.store("atmosphere", p)
                print("bmp280>", t, p)
                await obniz.wait(1000)
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_bmp280
        return self

    def run(self):
        try:
            asyncio.get_event_loop().run_forever()
        except Exception as ex:
            print('ERROR=', ex)
            asyncio.get_event_loop().stop()

            
if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument('--on', action='store_true')
    args = parser.parse_args()

    d = DeviceWithObniz()
    if args.on:
        d.set_bmp280().run()
        print("temperature=", d.get_temperature())
        print("humidity=", d.get_humidity())
        print("atmosphere", d.get_atmosphere())
        #print("lux=", d.get_lux())
