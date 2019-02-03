var Obniz = require("obniz");
var request = require("request");
var devices = require('./devices');

setTimeout(function(){ process.exit(); }, 90*1000);

var _obniz = new Obniz("0167-3051");
var extend = new devices(_obniz, 200);
_obniz.onconnect = async function()
{
	var _dev_lux = new extend.TEST(9, 10);
	var _dev_solar = new extend.TEST(9, 11);

	setTimeout(async function(){
		var l = _dev_lux.get_value();
		var s = _dev_solar.get_value();
		extend.p("lux="+l+" solar="+s);
		request.get(
			{
				url: "http://49.212.141.20/Imager/voltage.php",
				qs: { sensor: extend.getId(), value: s, voltage: l }
			},
			function(err, res, body){ extend.p("body="+body); }
		);

	}, 60*1000);
}

