<style>
    canvas
    {
        -moz-user-select: none;
        -webkit-user-select: none;
        -ms-user-select: none;
    }
</style>
<script src="{{url('js/Chart.bundle.js')}}"></script>
<script>
function ChartController(option)
{
	var _title = option.title == null ? null: option.title;
	var _label = option.label == null ? 'No label.': option.label;
	var _rgb = option.rgb == null ? 'rgb(255,99.132)': option.rgb;
	var _show_time = option.show_time == null ? true: option.show_time;

	var _url = option.url;
	var _canvas = option.canvas;
	if(_url == null || _canvas == null){ console.log("url or canvas is empty."); return null; }

	var _config = {
			type: 'line',
			data: {
				labels: [], // need to insert
				datasets: [
					{
						type: 'line',
						label: _label,
						backgroundColor: _rgb,
						borderColor: _rgb,
						data: [],
						fill: false,
					},
				]
			},
			options: {
				responsive: true,
				title: {
					display: _title == null ? false : true,
					text: _title,
				},
				tooltips: {
					mode: 'index',
					intersect: false,
				},
				hover: {
					mode: 'nearest',
					intersect: true
				},
				scales: {
					xAxes:
					[
						{
						display: _show_time,
						scaleLabel: { display: true, labelString: 'Date' }
						}
					],
				}
			}
		};

	this.initialize = function()
	{
		$.getJSON( _url, function( data ) {
			//console.log(data);
			if( data.status != 'ok' ){ console.log(data); return; }
			var values = [];
			for(var row of data.records)
			{
				values[row.time] = row.value == null ? 'NaN': row.value;
			}
			initializeChart(values);
		});

	}
	
	function initializeChart(values)
	{
		var labels = [];
		var data = [];
		for(var i in values)
		{
			labels.push(i.substr(5)); // cut this year.
			data.push(values[i]);
		}
		_config.data.labels = labels;
		_config.data.datasets[0].data = data;
		var ctx = document.getElementById(_canvas).getContext('2d');
		if(window.myLines == null) window.myLines = [];
		window.myLines.push(new Chart(ctx, _config));
	}

	var _that = this;
	return this;
};
</script>