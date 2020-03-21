import os,sys,string,traceback,random,glob,json,datetime,argparse,math
import asyncio
from obniz import Obniz


class Manager:
    def __init__(self):
        self.obniz = Obniz('0167-3051')

    def set_lux(self):
        async def on_lux(obniz):
            obniz.io0.output(False)
            obniz.ad1.start()
            obniz.io2.output(True)
            for _ in range(0, 10):
                await obniz.wait(1000)
                print("voltage=", obniz.ad1.value)
            obniz.io2.end()
            obniz.close()
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_lux
        return self

    def set_dht12(self):
        # do not run
        async def on_dht12(obniz):
            obniz.io5.drive("5v")
            obniz.io5.output(True)
            obniz.io11.output(False)
            await obniz.wait(3000)
            i2c = obniz.i2c0
            i2c.start( {"mode":"master", "sda":7, "scl":9, "clock":100000, "pull":"5v"} )
            i2c.onerror = lambda e: print("dht12", e)
            for _ in range(0, 10):
                r = await i2c.read_wait(0x5c, 5)
                chk = (r[0] + r[1] + r[2] + r[3]) & 0xff
                print("dht=", r, "chk=", chk)
                await obniz.wait(1000)
            asyncio.get_event_loop().stop()
        self.obniz.onconnect = on_dht12
        return self

    def set_tsl2561(self):
        GAINS = {0x00: 16.0*322.0/11.0, 0x01: 16.0*322.0/81.0, 0x02: 16.0*1.0, 0x12: 1.0*1.0}
        gain = 0x00 # Need modify gain.
        def getScale():
            return GAINS[gain]
        def compute_lux(int0, int1):
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
            obniz.io3.drive("5v") # 3v is better, but do not run.
            obniz.io3.output(True)
            obniz.io2.output(False)
            await obniz.wait(3000)
            i2c = obniz.i2c0
            i2c.start( {"mode":"master", "sda":1, "scl":0, "clock":100000, "pull":"5v"} )
            i2c.onerror = lambda e: print("tsl2561", e)
            i2c.write(0x39, [0x80, 0x03])
            i2c.write(0x39, [0x81, 0x00])
            for _ in range(0, 10):
                i2c.write(0x39, [0xAC])
                c0 = await i2c.read_wait(0x39, 2)
                ch0 = c0[1] * 256 + c0[0]
                i2c.write(0x39, [0xAE])
                c1 = await i2c.read_wait(0x39, 2)
                ch1 = c1[1] * 256 + c1[0]
                print("tsl2561=", ch0, ch1, compute_lux(ch0, ch1))
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

    m = Manager()
    if args.on:
        m.set_tsl2561().run()
