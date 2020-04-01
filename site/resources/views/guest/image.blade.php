@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        <div class="col-md-12 justify-content-center">
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
