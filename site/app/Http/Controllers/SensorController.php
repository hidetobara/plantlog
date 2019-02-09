<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;

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

    public function get($id, Request $request)
    {
        $s = MySession::factory();
        try
        {
            $s->add('obniz', Obniz::find($id));
        }
        catch(Exception $ex){ $s->addException($ex); }
        return view('sensor.chart', $s->toHtml());
    }
}
