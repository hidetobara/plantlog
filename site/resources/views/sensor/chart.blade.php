@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        @include('layouts.menu')

        <div class="col-md-10">
			<script src="{{url('js/jquery-3.3.1.min.js')}}"></script>
            <script src="{{url('js/Chart.bundle.js')}}"></script>
            <style>
                canvas
                {
                    -moz-user-select: none;
                    -webkit-user-select: none;
                    -ms-user-select: none;
                }
            </style>

            <div style="width:95%;">
                <canvas id="canvas_temperature"></canvas>
            </div>
            <div style="width:95%;">
                <canvas id="canvas_temperature_month"></canvas>
            </div>
            <div style="width:95%;">
                <canvas id="canvas_lux"></canvas>
            </div>
            <div style="width:95%;">
                <canvas id="canvas_co2"></canvas>
            </div>

            <script>
function ChartController(option)
{
	var _title = option.title == null ? null: option.title;
	var _label = option.label == null ? 'No label.': option.label;
	var _rgb = option.rgb == null ? 'rgb(255,99.132)': option.rgb;
	
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
						display: true,
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
			labels.push(i);
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

<?php
$last_momth = Carbon\Carbon::today()->startOfMonth()->addMonth(-1)->format("Y-m-d H:i");
?>
var temperature = new ChartController({label:"Temperature",url:"{!! url('api/record/select_temperature?sensor='.$obniz->id) !!}",canvas:"canvas_temperature",rgb:"rgb(128,255,192)"});
$(temperature.initialize);
var temperature_month = new ChartController({label:"Temperature(month)",url:"{!! url('api/record/select_temperature?interval=12&sensor='.$obniz->id.'&from='.$last_momth) !!}",canvas:"canvas_temperature_month",rgb:"rgb(128,255,192)"});
$(temperature_month.initialize);
var lux = new ChartController({label:"Lux",url:"{!! url('api/record/select_lux?sensor='.$obniz->id) !!}",canvas:"canvas_lux",rgb:"rgb(224,224,128)"});
$(lux.initialize);
var co2 = new ChartController({label:"CO2",url:"{!! url('api/record/select_co2?sensor='.$obniz->id) !!}",canvas:"canvas_co2",rgb:"rgb(128,128,192)"});
$(co2.initialize);
</script>

        </div>
    </div>
</div>
@endsection
