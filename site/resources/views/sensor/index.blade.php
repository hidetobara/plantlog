@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        @include('layouts.menu')

        <div class="col-md-9">
            @include('layouts.notice')

            <div class="card">
                <div class="card-header">Sensors</div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($obnizs as $o)
                            <tr>
                                <td>{{$o->id}}</td>
                                <td>{{$o->name}}</td>
                                <td>{{$o->description}}</td>
                                <td>
                                    <a class="btn btn-info" href="{{url('sensor/chart/'.$o->id)}}">Chart</a>
                                    <a class="btn btn-info" href="{{url('sensor/image/'.$o->id)}}">Images (every hour)</a>
                                    <a class="btn btn-info" href="{{url('sensor/image/'.$o->id)}}?interval=24">Images (every day)</a>
                                </td>
                            </tr>
                        @empty
                            No sensors.
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
