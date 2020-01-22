@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        @include('layouts.menu')

        <div class="col-md-9">
            @include('layouts.notice')

            <div class="card">
                <div class="card-header">Upload an image</div>
                <div class="card-body">
                    <form class="form-group" enctype="multipart/form-data" action="{{my_url('api/record/image')}}" method="post">
                        {{ csrf_field() }}
                        <input type="hidden" name="sensor_id" value="{{$sensor_id}}" />
                        <div class="row">
                            <div class="col-md-4"><label>An image</label></div>
                            <div class="col-md-4"><input name="image" type="file" class="form-control-file" /></div>
                        </div>
                        <div class="row">
                            <div class="col-md-4"><button class="btn btn-primary" type="submit">Update</button></div>
                        </div>
                    </form>
                </div>
            </div>
            <br />

            <div class="card">
                <div class="card-header">Images</div>
                <div class="card-body">
                @forelse($paths as $path)
                    <img src="{{my_url('api/record/image/'.$path)}}" /> {{$path}} <br />
                @empty
                    No images.
                @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
