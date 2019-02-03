var Obniz = require("obniz");
var request = require("request");

/*
 * plantlog for tkji, satzz
 */
function Plantlog(id, name)
{
	var _name = name;
	var _id = id;
	var _debug = false;
	var _life = 3;
	var _obniz = new Obniz(""+_name);
	// TASK KILL
	//setTimeout(function(){ dump("exiting.."); _obniz.close(); process.exit(); }, 120*1000);

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
	function bother()
	{
		_life--;
		if(_life < 0)
		{
			print("going to reset...");
			_life = 3;
			_obniz.close();
			//_obniz.reset();
			process.exit();
		}
	}

function device_POWER(o, num_gnd, num_vcc)
{
  var _obniz = o;
  _obniz["io"+num_gnd].output(false);
  _obniz["io"+num_vcc].drive("5v");  
  _obniz["io"+num_vcc].output(true);
  _obniz.keepWorkingAtOffline(true); // Maybe no sense.
  
  var _that = this;
  return this;
}

function device_LM35DZ(obniz, num_gnd, num_out, num_vcc)
{
  var _device = obniz.wired("LM35DZ",  { gnd:num_gnd , output:num_out, vcc:num_vcc});
  var _list = [];
  _device.onchange = function(temp){ _list.push(temp); if(_list.length % 10 == 0) dump("lm35> "+temp); };
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
    dump("tept> "+up+" "+down);
    if(up < down) return;
    _list.push(up - down);
  }, 10*1000);

  function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }
  this.get_lux = function()
  {
    var vol = get_median(_list);
    if(vol < 0.0) return 0;
    return Math.floor(11167 * vol) + 188;
  }
  this.clear = function(){ _list = []; }

  var _that = this;
  return this;
}

function device_MHZ19B(obniz, num_tx, num_rx)
{
  var _uart = obniz.getFreeUart();
  var _co2s = [];
  _uart.start({tx: num_tx, rx: num_rx, baud:9600, bits:8, stop:1, parity:"off", flowcontrol:"off"});
  reset();
  function reset(){ _uart.send([0xFF, 0x01, 0x79, 0xA0, 0x00, 0x00, 0x00, 0x00, 0xE6]); }
  function get_median(list){ if(list.length == 0) return 0; list.sort(function(a,b){return a-b;}); return list[ Math.floor(list.length/2) ]; }
  
  setInterval(async function(){
    var command = [0xFF, 0x01, 0x86, 0x00, 0x00, 0x00, 0x00, 0x00, 0x79];
    _uart.send(command);
    await obniz.wait(1);
    var res = _uart.readBytes();
    dump("mhz> "+res);
    if(res.length != 9 || res[1] != 134){ print(res); return; }
    var c = res[2] * 256 + res[3];
    _co2s.push(c);
    if(c == 0) reset();
  }, 10*1000);

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
		_dev_power = device_POWER(_obniz, 0, 11);
		_dev_lux = device_TEPT4400(_obniz, 5, 11);
		await _obniz.wait(30*1000);
		_dev_co2 = device_MHZ19B(_obniz, 4, 3);
		// saving
		setInterval(save, 15*60*1000);
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
		upload("http://49.212.141.20/Imager/temperature.php", {sensor: id, temperature: t });
		upload("http://49.212.141.20/Imager/co2.php", {sensor: id, co2: c });
		upload("http://49.212.141.20/Imager/lux.php", {sensor: id, lux: l });

		if(c == 0) bother();
	}

	var _that = this;
	return this;
};


var instances = [];
for(var n=2; n<process.argv.length; n++)
{
	var cells = process.argv[n].split("=");
	if(cells.length != 2) continue;
	var id = parseInt(cells[0]);
	var name = cells[1];
	instances.push(Plantlog(id, name));
}

//var instance1000 = Plantlog(1000, "9185-2914");
//var instance1002 = Plantlog(1002, "1515-3131");

