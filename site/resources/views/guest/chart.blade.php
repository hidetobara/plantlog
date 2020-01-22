@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        @include('layouts.chart_js')

        <div class="col-md-10">
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
<?php
$last_momth = Carbon\Carbon::today()->startOfMonth()->addMonth(-1)->format("Y-m-d H:i");
?>
var temperature = new ChartController({label:"Temperature",url:"{!! my_url('api/record/select_temperature?sensor='.$obniz->id) !!}",canvas:"canvas_temperature",rgb:"rgb(128,255,192)"});
$(temperature.initialize);
var temperature_month = new ChartController({label:"Temperature(month)",url:"{!! my_url('api/record/select_temperature?interval=12&sensor='.$obniz->id.'&from='.$last_momth) !!}",canvas:"canvas_temperature_month",rgb:"rgb(128,255,192)"});
$(temperature_month.initialize);
var lux = new ChartController({label:"Lux",url:"{!! my_url('api/record/select_lux?sensor='.$obniz->id) !!}",canvas:"canvas_lux",rgb:"rgb(224,224,128)"});
$(lux.initialize);
var co2 = new ChartController({label:"CO2",url:"{!! my_url('api/record/select_co2?sensor='.$obniz->id) !!}",canvas:"canvas_co2",rgb:"rgb(128,128,192)"});
$(co2.initialize);
</script>

        </div>
    </div>
</div>
@endsection
