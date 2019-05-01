<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Models\Obniz;
use App\MySession;


class GuestController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    public function index()
    {
        return view('guest.index');
    }

    public function getChart($id, Request $request)
    {
        $s = MySession::factory();
        try
        {
            $s->add('obniz', Obniz::find($id));
        }
        catch(Exception $ex){ $s->addException($ex); }
        return view('guest.chart', $s->toHtml());
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
        return view('guest.image', $s->toHtml());
    }

    public function getChanging($id, Request $request)
    {
        $s = MySession::factory();
        try
        {
            $s->add('sensor_id', $id);
            $s->add('interval', $request->input('interval', 2));
            $s->add('limit', $request->input('limit', 84));
        }
        catch(Exception $ex){ $s->addException($ex); }
        return view('guest.changing', $s->toHtml());
    }

    public function getImagesJson($id, Request $request)
    {
        $s = MySession::factory();
        try
        {
            $interval = $request->input('interval', 1);
            if($interval < 1) $interval = 1;
            $limit = $request->input('limit', 28);
            $base = sprintf('images/s%04d/', $id);
            $files = Storage::allFiles($base);
            rsort($files);
            $paths = [];
            $names = [];
            for($i = 0; $i < count($files); $i += $interval)
            {
                $info = pathinfo($files[$i]);
                $paths[] = url('api/record/image/' . $id . '/' .$info['basename']);
                $names[] = $info['basename'];
                if(count($paths) >= $limit) break;
            }
            sort($paths);
            sort($names);
            $s->add('urls', $paths);
            $s->add('names', $names);
        }
        catch(Exception $ex){ $s->addException($ex); }
        return response()->json($s->toApi());
    }
}
