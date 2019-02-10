var request = require("request");

module.exports = function(obniz, id)
{
	var _id = id;
	var _obniz = obniz;
	var _debug = true;

	function addZero(s){ return ("0"+s).slice(-2); }
	function dateStr(date)
	{
		if(date == null) date = new Date();
		const y = date.getFullYear()
		const m = addZero(date.getMonth() + 1);
		const d = addZero(date.getDate());
		const h = addZero(date.getHours());
		const i = addZero(date.getMinutes());
		const s = addZero(date.getSeconds());
		return y+"-"+m+"-"+d+"Z"+h+":"+i;
	}
	function print(m)
	{
		if(_obniz != null){ _obniz.display.clear(); _obniz.display.print(_id+":\n"+m); } 
		console.log("["+dateStr()+"]"+_id+":"+m);
	}
	function dump(m){ if(_debug) console.log(_id+":"+m); }

	this.upload = function(url, params)
	{
		request.get({
			url: url,
			qs: params
		}, function(err, res, body){ dump(url + "=" + body); });
	}
	this.p = function(s){ print(s); }
	this.getId = function(){ return _id; }

	this.POWER = function(num_gnd, num_vcc)
	{
		_obniz["io"+num_gnd].output(false);
		_obniz["io"+num_vcc].drive("5v");  
		_obniz["io"+num_vcc].output(true);
	
		var _that = this;
		return this;
	}

	this.TEST = function(num_down, num_up)
	{
		let _list = [];
		function getKey(){ return num_down+"."+num_up; }
		function getList(){ return _list; }

		_obniz["ad"+num_down].start();
		_obniz["ad"+num_up].start();
		setInterval(function(){
			let down = _obniz["ad"+num_down].value;
			let up = _obniz["ad"+num_up].value;
			dump("test> down="+down+" up="+up+" num="+num_down+"-"+num_up+" val="+getKey());
			getList().push(up - down);
		}, 2000);
		function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }

		this.get_value = function(){ return get_median(getList()); }
		this.clear = function(){ _list = []; }

		var _that = this;
		return this;
	}

	this.LM35DZ = function(num_gnd, num_out, num_vcc)
	{
		var _device = _obniz.wired("LM35DZ",  { gnd:num_gnd , output:num_out, vcc:num_vcc});
		var _list = [];
		_device.onchange = function(temp){ _list.push(temp); if(_list.length % 100 == 0) dump("lm35> "+temp); };
		function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }

		this.get_temperature = function(){ return get_median(_list); }
		this.clear = function(){ _list = []; }
		var _that = this;
		return this;
	}

	this.TSL2561 = function(num_sda, num_scl)
	{
		var GAINS = {0x00: 16.0*322.0/11.0, 0x01: 16.0*322.0/81.0, 0x02: 16.0*1.0, 0x12: 1.0*1.0}
		var _gain = 0x00; // Need modify gain.
		function getGain(){ return _gain; }
		function getScale() { return GAINS[_gain]; }

		var _list = [];
		var _i2c = null;

		function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }
		this.get_lux = function(){ return get_median(_list); }
		this.clear = function(){ _list = []; }

		function compute_lux(int0, int1)
		{
			var ch0 = int0 * getScale();
			var ch1 = int1 * getScale();

			var ratio = ch1 / ch0;
			var lux = 0.0;
			if(ratio >= 0 && ratio <= 0.50) lux = 0.0304 * ch0 - 0.062 * ch0 * Math.pow(ratio, 1.4);
			else if(ratio <= 0.61) lux = 0.0224 * ch0 - 0.031 * ch1;
			else if(ratio <= 0.80) lux = 0.0128 * ch0 - 0.0153 * ch1;
			else if(ratio <= 1.30) lux = 0.00146 * ch0 - 0.00112 * ch1;
			return lux;
		}

		function start()
		{
			// lux
			_i2c = _obniz.getFreeI2C();
			//_i2c = _obniz.i2c0;
			_i2c.start({mode:"master", sda:num_sda, scl:num_scl, clock:100000});
			// start tsl
			_i2c.write(0x39, [0x80, 0x03]);
			// modify tsl gain
			_i2c.write(0x39, [0x81, getGain()]);
			_i2c.onerror = function(error){ print(error); start(); }
		}
		start();

		setInterval(async function(){
			// lux
			_i2c.write(0x39, [0xAC]);
			var c0 = await _i2c.readWait(0x39, 2);
			var ch0 = c0[1] * 256 + c0[0];
			//print("c0> "+c0);
			_i2c.write(0x39, [0xAE]);
			var c1 = await _i2c.readWait(0x39, 2);
			var ch1 = c1[1] * 256 + c1[0];
			//print("c1> "+c1);
			var lux = compute_lux(ch0, ch1);
			print("lux> "+lux+ " ch0="+ch0+" ch1="+ch1);
			_list.push(lux);
		}, 3000);

		var _that = this;
		return this;
	}

	this.MHZ19B = function(num_tx, num_rx)
	{
		var _uart = _obniz.getFreeUart();
		var _co2s = [];
		function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }

		this.reset = function()
		{
			dump("mhz> reset");
			for(var i=0; i<3; i++){ _uart.send([0xFF, 0x01, 0x79, 0xA0, 0x00, 0x00, 0x00, 0x00, 0xE6]); _obniz.wait(1000); }
		}
		
		_uart.start({tx: num_tx, rx: num_rx, baud:9600, bits:8, stop:1, parity:"off", flowcontrol:"off"});
		setInterval(async function(){
		var command = [0xFF, 0x01, 0x86, 0x00, 0x00, 0x00, 0x00, 0x00, 0x79];
		_uart.send(command);
		await _obniz.wait(100);
		var res = _uart.readBytes();
		if(res.length < 9 || res[0] != 255 || res[1] != 134){ dump("mhz> " +res); return; }
		var c = res[2] * 256 + res[3];

		dump("mhz> co2=" + c);
		if(c == 0 || c == 5000){ reset(); return; }
		_co2s.push(c);
		}, 5*1000);

		this.get_co2 = function(){ return get_median(_co2s); }
		this.clear = function(){ _co2s = []; }

		var _that = this;
		return this;
	}

	this.TEPT4400 = function(num_top, num_vcc)
	{
		_obniz["ad"+num_top].start();
		_obniz["ad"+num_vcc].start();
		var _list = [];
		setInterval(function(){
			var down = _obniz["ad"+num_top].value;
			var up = _obniz["ad"+num_vcc].value;
			dump("tept> "+up+" "+down);
			if(up < down) return;
			_list.push(up - down);
		}, 10*1000);

		function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }
		this.get_lux = function()
		{
			var vol = get_median(_list);
			if(vol < 0.0) return 0;
			var lux = 7232.4 * vol + 2.5;
			return Math.floor(lux / 100) * 100;
		}
		this.clear = function(){ _list = []; }

		var _that = this;
		return this;
	}

	return this;
}
