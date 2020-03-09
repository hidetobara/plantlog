import os,sys,string,traceback,random,glob,json,datetime,argparse
import asyncio
from obniz import Obniz


def run():
    async def onconnect(obniz):
        obniz.io0.pull("3v")
        obniz.io0.output(True)
        obniz.io1.output(False)
        await obniz.wait(1000)
        obniz.io0.end()
        obniz.close()
        sys.exit()
    obniz = Obniz('0167-3051')
    obniz.onconnect = onconnect

    asyncio.get_event_loop().run_forever()

            
if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument('--on', action='store_true')
    args = parser.parse_args()
    if args.on:
        run()
