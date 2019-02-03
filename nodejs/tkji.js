var Obniz = require("obniz");
var request = require("request");
var devices = require('./devices');

setTimeout(function(){ process.exit(); }, 90*1000);

var _obniz = new Obniz("9185-2914");
var extend = new devices(_obniz, 1001);
_obniz.onconnect = async function()
{
	var _dev_temp = new extend.LM35DZ(8, 9, 10);

	setTimeout(async function(){
		var t = _dev_temp.get_temperature();
		extend.p("temp="+t);
		request.get(
			{
				url: "http://49.212.141.20/Imager/temperature.php",
				qs: { sensor: extend.getId(), temperature: t }
			},
			function(err, res, body){ extend.p("body="+body); }
		);

	}, 60*1000);
}

