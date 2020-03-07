@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">

        <div class="col-md-12">
            <div style="text-align:center;">
                <img style="width:60%;" src="{{my_url('img/s1000.gif')}}" />
            </div>

            @include('layouts.chart_js')
            <canvas id="canvas" style="height:50vh;"></canvas>
            <script>
var temperature = new ChartController({label:"Temperature",url:"{!! my_url('api/record/select_temperature?sensor=1') !!}",canvas:"canvas",rgb:"rgb(192,128,128)",show_time:false});
$(temperature.initialize);
            </script>
        </div>
        <br />
        <br />

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Home</div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="{{my_url('guest/chart/1')}}" class="list-group-item">Charts in my room</a>
                        <a href="{{my_url('guest/image/1')}}" class="list-group-item">Images with infrared camera</a>
                        <a href="{{my_url('guest/image/2')}}" class="list-group-item">Images of orchid 1</a>
                        <a href="{{my_url('guest/image/3')}}" class="list-group-item">Images of Corridor</a>
                        <a href="{{my_url('guest/image/4')}}" class="list-group-item">Images of orchid 2</a>
                        <a href="{{my_url('guest/chart/3')}}" class="list-group-item">Charts in my office</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Tkji</div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="{{my_url('guest/chart/1000')}}" class="list-group-item">Charts</a>
                        <a href="{{my_url('guest/image/1000')}}" class="list-group-item">Images(Side-view)</a>
                        <a href="{{my_url('guest/chart/1001')}}" class="list-group-item">Charts(Water)</a>
                        <a href="{{my_url('guest/image/1002')}}" class="list-group-item">Images(Top-view)</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
