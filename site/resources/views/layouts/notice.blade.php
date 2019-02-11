
@isset($warnings)
    <div class="card text-white bg-danger">
        <div class="card-body">
        @foreach($warnings as $warning)
            <p>{{$warning}}</p>
        @endforeach
        </div>
    </div>
    <br />
@endisset

@isset($messages)
    <div class="card text-white bg-info">
        <div class="card-body">
        @foreach($messages as $message)
            <p>{{$message}}</p>
        @endforeach
        </div>
    </div>
    <br />
@endisset
