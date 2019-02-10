<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Models\Obniz;
use App\MySession;

class SensorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $s = MySession::factory();
        try
        {
            $s->add('obnizs', Obniz::where(['user_id' => $s->getUserId()])->get());
        }
        catch(Exception $ex){ $s->addException($ex); }
        return view('sensor.index', $s->toHtml());
    }

    public function getChart($id, Request $request)
    {
        $s = MySession::factory();
        try
        {
            $s->add('obniz', Obniz::find($id));
        }
        catch(Exception $ex){ $s->addException($ex); }
        return view('sensor.chart', $s->toHtml());
    }

    public function getImage($id, Request $request)
    {
        $s = MySession::factory();
        try
        {
            $s->add('sensor_id', $id);
            $interval = $request->input('interval', 1);
            if($interval < 1) $interval = 1;
            $base = sprintf('images/s%04d/', $id);
            $files = Storage::allFiles($base);
            rsort($files);
            $paths = [];
            for($i = 0; $i < count($files); $i += $interval)
            {
                $info = pathinfo($files[$i]);
                $paths[] = $id . '/' .$info['basename'];
                if(count($paths) >= 24) break;
            }
            $s->add('paths', $paths);
        }
        catch(Exception $ex){ $s->addException($ex); }
        return view('sensor.image', $s->toHtml());
    }
}
