@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-9 justify-content-center">
            <div class="card">
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="{{url('guest/image/'.$sensor_id)}}" class="list-group-item">Every hours</a>
                        <a href="{{url('guest/image/'.$sensor_id)}}?interval=24" class="list-group-item">Everyday</a>
                        <a href="{{url('guest/changing/'.$sensor_id)}}" class="list-group-item">Changing</a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Images</div>
                <div class="card-body">
                @forelse($paths as $path)
                    <img src="{{url('api/record/image/'.$path)}}" /> {{$path}} <br />
                @empty
                    No images.
                @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
