@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        @include('layouts.chart_js')

        <div class="col-md-6">
            <div style="width:95%;">
                <canvas id="canvas_temperature"></canvas>
            </div>
            <div style="width:95%;">
                <canvas id="canvas_temperature_month"></canvas>
            </div>
            <div style="width:95%;">
                <canvas id="canvas_lux"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div style="width:95%;">
                <canvas id="canvas_humidity"></canvas>
            </div>
            <div style="width:95%;">
                <canvas id="canvas_pressure"></canvas>
            </div>
            <div style="width:95%;">
                <canvas id="canvas_co2"></canvas>
            </div>
        </div>
<script>
<?php
$last_momth = Carbon\Carbon::today()->startOfMonth()->addMonth(-1)->format("Y-m-d H:i");
?>
var temperature = new ChartController({label:"Temperature",url:"{!! my_url('api/record/select_temperature?sensor='.$obniz->id) !!}",canvas:"canvas_temperature",rgb:"rgb(128,255,192)"});
$(temperature.initialize);
var temperature_month = new ChartController({label:"Temperature(month)",url:"{!! my_url('api/record/select_temperature?interval=12&sensor='.$obniz->id.'&from='.$last_momth) !!}",canvas:"canvas_temperature_month",rgb:"rgb(128,255,192)"});
$(temperature_month.initialize);
var lux = new ChartController({label:"Lux",url:"{!! my_url('api/record/select_lux?sensor='.$obniz->id) !!}",canvas:"canvas_lux",rgb:"rgb(255,192,192)"});
$(lux.initialize);
var humidity = new ChartController({label:"Humidity",url:"{!! my_url('api/record/select_humidity?sensor='.$obniz->id) !!}",canvas:"canvas_humidity",rgb:"rgb(128,192,192)"});
$(humidity.initialize);
var pressure = new ChartController({label:"Pressure",url:"{!! my_url('api/record/select_pressure?sensor='.$obniz->id) !!}",canvas:"canvas_pressure",rgb:"rgb(192,192,255)"});
$(pressure.initialize);
var co2 = new ChartController({label:"CO2",url:"{!! my_url('api/record/select_co2?sensor='.$obniz->id) !!}",canvas:"canvas_co2",rgb:"rgb(128,128,128)"});
$(co2.initialize);
</script>

        </div>
    </div>
</div>
@endsection
