@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-12 justify-content-center">
            <div class="card">
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="{{my_url('guest/image/'.$sensor_id)}}" class="list-group-item">Every hours</a>
                        <a href="{{my_url('guest/image/'.$sensor_id)}}?interval=24" class="list-group-item">Everyday</a>
                        <a href="{{my_url('guest/changing/'.$sensor_id)}}" class="list-group-item">Changing</a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Images</div>
                <div class="card-body" style="text-align:center;">
                @foreach(range(2,0,-1) as $n)
                    <img id="img{{$n}}" /> <p id="txt{{$n}}"></p> <br />
                    <hr />
                @endforeach
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function Controller()
{
    this.initialize = function()
    {
        var url = "{{my_url('guest/images/'.$sensor_id.'.json')}}?interval=12&limit=3";
        $.getJSON(url, function(data){
            if(data.names.length != 3) return;
            for(var n = 2; n >= 0; n--)
            {
                $("#img"+n).attr("src", data.urls[n]);
                $("#img"+n).attr("width", (50+20*n)+"%");
                $("#txt"+n).text(data.names[n])
            }
        });
    }
    return this;
};
instance = new Controller();
$(instance.initialize);
</script>

@endsection
