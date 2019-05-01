@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">

        <div class="col-md-12">
            <div style="text-align:center;height:30vh;">
                <p style="font-size:84px;">
                    {{ config('app.name', 'Plantlog') }}
                </p>
            </div>

            @include('layouts.chart_js')
            <canvas id="canvas" style="height:50vh;"></canvas>
            <script>
var temperature = new ChartController({label:"Temperature",url:"{!! url('api/record/select_temperature?sensor=1') !!}",canvas:"canvas",rgb:"rgb(192,128,128)",show_time:false});
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
                        <a href="{{url('guest/chart/1')}}" class="list-group-item">Charts in my room</a>
                        <a href="{{url('guest/changing/1')}}" class="list-group-item">Images in my room</a>
                        <a href="{{url('guest/changing/2')}}" class="list-group-item">Images of orchid 1</a>
                        <a href="{{url('guest/changing/3')}}" class="list-group-item">Images of orchid 2</a>
                        <a href="{{url('guest/chart/3')}}" class="list-group-item">Charts in my corridor</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Tkji</div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="{{url('guest/chart/1000')}}" class="list-group-item">Charts</a>
                        <a href="{{url('guest/changing/1000')}}" class="list-group-item">Images(Side-view)</a>
                        <a href="{{url('guest/chart/1001')}}" class="list-group-item">Charts(Water)</a>
                        <a href="{{url('guest/changing/1001')}}" class="list-group-item">Images(Top-view)</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">Satzz</div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="{{url('guest/chart/1002')}}" class="list-group-item">Charts</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
