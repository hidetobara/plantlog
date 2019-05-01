@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-12 justify-content-center" align="center">
            <img width="90%" id="changing">
            <img width="1" id="buffer">
            <b id="name"></b>
        </div>
    </div>
</div>

<script>
function Controller()
{
    var _urls = [];
    var _names = [];
    var _index = 0;

    this.initialize = function()
    {
        var url = "{{url('guest/images/'.$sensor_id.'.json')}}?interval={{$interval}}&limit={{$limit}}";
        $.getJSON(url, function(data){
            for(url of data.urls) _urls.push(url);
            for(name of data.names) _names.push(name);
        });
    }
    function loading()
    {
        if(_urls.length == 0) return;
        var url = _urls[_index];
        console.log(url);
        var img = $("#changing");
        img.fadeOut(1);
        img.attr("src", url);
        img.fadeIn(500);
        $("#name").text(_names[_index]);
        _index += 1;
        if(_urls.length <= _index) _index = 0;

        var buf = $("#buffer");
        buf.attr("src", _urls[_index]);
    }
    setInterval(loading, 3000);
    return this;
};
instance = new Controller();
$(instance.initialize);
</script>

@endsection
