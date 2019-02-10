var Obniz = require("obniz");
var request = require("request");

/*
 * plantlog for company
 */
function Plantlog(id, name)
{
	var _name = name;
	var _id = id;
	var _debug = true;
	var _obniz = new Obniz(""+_name);
	var _isResetting = false;
	// TASK KILL
	setTimeout(exit, 90*1000);

	function exit(){ dump("exiting.."); for(var i=0; i<12; i++) _obniz["io"+i].end(); _obniz.close(); process.exit(); }
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
	function upload(url, params)
	{
		request.get({
			url: url,
			qs: params
		}, function(err, res, body){ dump(url + "=" + body); });
	}
	this.enableReset = function(){ _isResetting = true; }
	function reset()
	{
		print("going to reset...");
		//process.exit();
		_obniz.io11.output(true);
		_obniz.wait(2000);
		_obniz.io11.end();
	}

function device_POWER(o, num_gnd, num_vcc)
{
  var _obniz = o;
  _obniz["io"+num_gnd].output(false);
  _obniz["io"+num_vcc].drive("5v");  
  _obniz["io"+num_vcc].output(true);
  //_obniz.keepWorkingAtOffline(true); // Maybe no sense.
  
  var _that = this;
  return this;
}

function device_LM35DZ(obniz, num_gnd, num_out, num_vcc)
{
  var _device = obniz.wired("LM35DZ",  { gnd:num_gnd , output:num_out, vcc:num_vcc});
  var _list = [];
  _device.onchange = function(temp){ _list.push(temp); if(_list.length % 100 == 0) dump("lm35> "+temp); };
  function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }
  
  this.get_temperature = function(){ return get_median(_list); }
  this.clear = function(){ _list = []; }
  var _that = this;
  return this;
}
  
function device_TEPT4400(obniz, num_top, num_vcc)
{
  obniz["ad"+num_top].start();
  obniz["ad"+num_vcc].start();
  var _list = [];
  setInterval(function(){
    var down = obniz["ad"+num_top].value;
    var up = obniz["ad"+num_vcc].value;
    dump("tept> down="+down+" up="+up);
    if(up < down) return;
    _list.push(up - down);
  }, 5*1000);

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

function device_MHZ19B(obniz, num_tx, num_rx)
{
  var _uart = obniz.getFreeUart();
  var _co2s = [];
  function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }

  this.reset = function()
  {
    dump("mhz> reset");
    for(var i=0; i<3; i++){ _uart.send([0xFF, 0x01, 0x79, 0xA0, 0x00, 0x00, 0x00, 0x00, 0xE6]); obniz.wait(1000); }
  }
  
  _uart.start({tx: num_tx, rx: num_rx, baud:9600, bits:8, stop:1, parity:"off", flowcontrol:"off"});
  setInterval(async function(){
    var command = [0xFF, 0x01, 0x86, 0x00, 0x00, 0x00, 0x00, 0x00, 0x79];
    _uart.send(command);
    await obniz.wait(100);
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

	var _dev_power, _dev_temperature, _dev_lux, _dev_co2;
	print("starting id="+_id+" name="+_name);
	_obniz.onconnect = async function ()
	{
		// initialize
		print("initialized...");
		_dev_temperature = device_LM35DZ(_obniz, 0, 1, 2);
		_dev_lux = device_TEPT4400(_obniz, 5, 6);
		_dev_co2 = device_MHZ19B(_obniz, 4, 3);
		// resetting
		if(_isResetting)
		{
			reset();
			_obniz.wait(3*1000);
			_dev_co2.reset();
			return;
		}
		// saving
		setInterval(save, 60*1000);
	}

	async function save()
	{
		var t = 0, l = 0, c = 0;
		var id = _id;
		t = _dev_temperature.get_temperature();
		l = _dev_lux.get_lux();
		c = _dev_co2.get_co2();
		_dev_temperature.clear();
		_dev_lux.clear();
		_dev_co2.clear();

		print("saved id="+id +",temp="+t+",co2="+c+",lux="+l);
		upload("http://49.212.141.20/plant/api/record/update_temperature", {sensor: id, temperature: t });
		upload("http://49.212.141.20/plant/api/record/update_co2", {sensor: id, co2: c });
		upload("http://49.212.141.20/plant/api/record/update_lux", {sensor: id, lux: l });
	}

	var _that = this;
	return this;
};

// argv, argc
var instances = [];
var enableReset = false;
for(var n=2; n<process.argv.length; n++)
{
	var item = process.argv[n];
	if(item.startsWith("--"))
	{
		if(item == "--reset") enableReset = true;
		continue;
	}
	var cells = process.argv[n].split("=");
	if(cells.length == 2)
	{
		var id = parseInt(cells[0]);
		var name = cells[1];
		var i = new Plantlog(id, name);
		if(enableReset) i.enableReset();
		instances.push(i);
		continue;
	}
}
//var instance3 = Plantlog(3, "6364-0285"); // 3=6364-0285
//var instance1000 = Plantlog(1000, "9185-2914"); // 1000=9185-2914
//var instance1002 = Plantlog(1002, "1515-3131"); // 1002=1515-3131

