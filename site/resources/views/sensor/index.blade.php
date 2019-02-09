@extends('layouts.app')

@section('content')

<div class="container">
    <div class="row">
        @include('layouts.menu')

        <div class="col-md-8 justify-content-center">
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
                                <td><a href="{{url('sensor/'.$o->id)}}">Chart</a></td>
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
