const Obniz = require("obniz");
const request = require("request");

// Task Kill
//setTimeout(function(){ obniz.close(); process.exit(); }, 90*1000);

var obniz = new Obniz("0167-3051");

function device_POWER(o, num_gnd, num_vcc, num_chk=-1)
{
  var _obniz = o;
  _obniz["io"+num_gnd].output(false);
  _obniz["io"+num_vcc].drive("5v");
  _obniz["io"+num_vcc].output(true);
  if(num_chk >= 0)
  {
     _obniz["ad"+num_chk].start();
     this.get_value = function(){ return _obniz["ad"+num_chk].value; }
  }
  _obniz.keepWorkingAtOffline(true); 
 
  var _that = this;
  return this;
}

function device_CCS811(o, num_sda, num_scl)
{
  const ADDRESS = 0x5B;
  const START = 0xF4;
  const STATUS = 0x00;
  const REGISTER = 0x01;
  const ALG_RESULT_DATA = 0x02;
  const ERROR = 0xE0;
  const HARDWARE = 0x20;
  const VERSION = 0x21;
  const INTERVAL = 10000;

  var _obniz = o;
  var _coxs = [];
  
  // private
  function print(m){ _obniz.display.clear(); _obniz.display.print(m); console.log(m); }
  function toHex(v){ return '0x' + (('00' + v.toString(16).toUpperCase()).substr(-2)); } 
  function get_median(list)
  {
    if(list.length == 0) return 0;
    if(list.length == 1) return list[0];

    list.sort(function(a,b){ if(a>b) return 1; if(a<b) return -1; return 0; });
    var o = list[ Math.floor(list.length / 2) ];
    return o;
  }
  function get_max(list)
  {
    if(list.length == 0) return 0;
    if(list.length == 1) return list[0];

    list.sort(function(a,b){ if(a>b) return 1; if(a<b) return -1; return 0; });
    var o = list[ list.length-1 ];
    return o;
  }
  
  // public
  this.get_cox = function(){ return get_median(_coxs); }
  this.clear = function(){ _coxs = []; }

  // initialize
  var i2c = _obniz.getFreeI2C();
  i2c.start({mode:"master", sda:num_sda, scl:num_scl, clock:100000});
  i2c.write(ADDRESS, [START]);
  //i2c.write(ADDRESS, [0xFF, 0x11, 0xE5, 0x72, 0x8A]); // reset
  //i2c.write(ADDRESS, [REGISTER, 0x10]); // 0x40 ?

  // auto measuring
  var age = 0;
  setInterval(async function()
  {
    age++;
    if(age <= 3)
    {
      i2c.write(ADDRESS, [START, 0]);
      i2c.write(ADDRESS, [REGISTER, 0x10]);
      i2c.write(ADDRESS, [HARDWARE, 0]);
      var reg = await i2c.readWait(ADDRESS, 1);
      i2c.write(ADDRESS, [VERSION, 0]);
      var ver = await i2c.readWait(ADDRESS, 1);
      print("[ccs811] reg=" + toHex(reg[0]) + " ver=" + toHex(ver[0]));
      return;
    }

    i2c.write(ADDRESS, [STATUS]);
    var sta = await i2c.readWait(ADDRESS, 1); 
    print("[ccs811] status=" + toHex(sta[0]));
    if(sta[0] & 0x8)
    {
      i2c.write(ADDRESS, [ALG_RESULT_DATA]);
      var res0 = await i2c.readWait(ADDRESS, 8);
      console.log(res0);
      var cox = res0[0]*256+res0[1];
      print("[ccs811] cox=" + cox);
      _coxs.push(cox);
    }
    if(sta[0] & 0x1)
    {
      i2c.write(ADDRESS, [ERROR]);
      var err = await i2c.readWait(ADDRESS,1);
      print("error="+ toHex(err[0]));
    }
  }, INTERVAL);

  var _that = this;
  return this;
}

obniz.onconnect = async function ()
{
	function print(m){ obniz.display.clear(); obniz.display.print(m); console.log(m); }
	function save_co2(id, c)
	{
		request.get({
			url: "http://49.212.141.20/Imager/co2.php",
			qs: { sensor: id, co2: c }
			},
			function(err, res, body){ print("co2>"+body+" c="+c); }
		);
	}

	// initialize
	print("started...");
	//for(var i=1; i<11; i++) obniz["io"+i].end(); // Not need ?
	var _power = device_POWER(obniz, 0, 11);
	obniz.wait(3*1000);
	//setInterval(function(){ print("chk=" + power.get_value()); }, 10*1000);
	var _ccs811 = device_CCS811(obniz, 1, 2);
	setInterval(function()
		{
			var cox = _ccs811.get_cox();
			if(cox > 0) save_co2(2, cox);
			_ccs811.clear();
		}, 15*60*1000);
};
