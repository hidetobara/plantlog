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

    def run_power(self, obniz, gnd, vdd):
        if vdd is not None:
            #obniz.__dict__['io{}'.format(vdd)].drive("5v") # obniz bug ?
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
                print("tept4400", land.value, drain.value)
                self.store("lux", calculate_lux(drain.value - land.value))
                await obniz.wait(1000)
            obniz.close()
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_tept4400
        return self

    def set_dht12(self, sda=7, scl=9, gnd=11, vdd=5):
        # do not run
        async def on_dht12(obniz):
            self.run_power(obniz, gnd, vdd)
            await obniz.wait(3000)
            i2c = obniz.i2c0
            i2c.start( {"mode":"master", "sda":sda, "scl":scl, "clock":100000, "pull":"5v"} )
            i2c.onerror = lambda e: print("dht12", e)
            for _ in range(0, 10):
                i2c.write(0x5c, [0x00])
                r = await i2c.read_wait(0x5c, 5)
                chk = (r[0] + r[1] + r[2] + r[3]) & 0xff
                print("dht=", r, "chk=", chk)
                if chk != r[4]: continue
                self.store("humidity", r[0] + r[1]*0.1)
                self.store("temperature", r[2] + r[3]*0.1)
                await obniz.wait(1000)
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_dht12
        return self

    def set_tsl2561(self, sda=1, scl=0, gnd=2, vdd=3):
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
            i2c.write(0x39, [0x80, 0x03])
            i2c.write(0x39, [0x81, GAIN])
            for _ in range(0, 10):
                i2c.write(0x39, [0xAC])
                c0 = await i2c.read_wait(0x39, 2)
                ch0 = c0[1] * 256 + c0[0]
                i2c.write(0x39, [0xAE])
                c1 = await i2c.read_wait(0x39, 2)
                ch1 = c1[1] * 256 + c1[0]
                print("tsl2561=", ch0, ch1)
                self.store("lux", calculate_lux(ch0, ch1))
                await obniz.wait(1000)
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_tsl2561
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
        d.set_dht12().run()
        print("temperature=", d.get_temperature())
        print("humidity=", d.get_humidity())
        print("lux=", d.get_lux())
