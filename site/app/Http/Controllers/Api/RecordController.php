<?php

namespace App\Http\Controllers\Api;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Http\Controllers\Controller;
use App\Models\Co2;
use App\Models\Temperature;
use App\Models\Lux;
use App\Models\Experiment;
use App\Models\Pressure;
use App\Models\Humidity;
use App\MySession;


class RecordController extends Controller
{
    public function __construct()
    {
        //$this->middleware('auth');
    }

    public function updateCo2(Request $request)
    {
        return $this->updateRecord(function() use($request) {
            $sensor = $request->input('sensor');
            $value = $request->input('co2');
            if(empty($sensor) || empty($value)) throw new Exception('Empty sensor id or co2');
            Co2::updateOrCreate(['sensor_id' => $sensor, 'time' => $this->getNow()], ['co2' => $value]);
        });
    }
    public function selectCo2(Request $request)
    {
        return $this->selectRecord($request, function($from,$to) use($request) {
            $sensor = $request->input('sensor');
            if(empty($sensor)) throw new Exception('Empty sensor id');
            return Co2::where(['sensor_id' => $sensor])->whereBetween('time', [$from, $to])->get();
        });
    }

    public function updateTemperature(Request $request)
    {
        return $this->updateRecord(function() use($request) {
            $sensor = $request->input('sensor');
            $value = $request->input('temperature');
            if(empty($sensor) || empty($value)) throw new Exception('Empty sensor id or temperature');
            Temperature::updateOrCreate(['sensor_id' => $sensor, 'time' => $this->getNow()], ['temperature' => $value]);
        });
    }
    public function selectTemperature(Request $request)
    {
        return $this->selectRecord($request, function($from,$to) use($request) {
            $sensor = $request->input('sensor');
            if(empty($sensor)) throw new Exception('Empty sensor id');
            return Temperature::where(['sensor_id' => $sensor])->whereBetween('time', [$from, $to])->get();
        });
    }

    public function updateLux(Request $request)
    {
        return $this->updateRecord(function() use($request) {
            $sensor = $request->input('sensor');
            $value = $request->input('lux');
            if(empty($sensor) || empty($value)) throw new Exception('Empty sensor id or lux');
            Lux::updateOrCreate(['sensor_id' => $sensor, 'time' => $this->getNow()], ['lux' => $value]);
        });
    }
    public function selectLux(Request $request)
    {
        return $this->selectRecord($request, function($from,$to) use($request) {
            $sensor = $request->input('sensor');
            if(empty($sensor)) throw new Exception('Empty sensor id');
            return Lux::where(['sensor_id' => $sensor])->whereBetween('time', [$from, $to])->get();
        });
    }

    public function updatePressure(Request $request)
    {
        return $this->updateRecord(function() use($request) {
            $sensor = $request->input('sensor');
            $value = $request->input('pressure');
            if(empty($sensor) || empty($value)) throw new Exception('Empty sensor id or pressure');
            Pressure::updateOrCreate(['sensor_id' => $sensor, 'time' => $this->getNow()], ['pressure' => $value]);
        });
    }
    public function selectPressure(Request $request)
    {
        return $this->selectRecord($request, function($from,$to) use($request) {
            $sensor = $request->input('sensor');
            if(empty($sensor)) throw new Exception('Empty sensor id');
            return Pressure::where(['sensor_id' => $sensor])->whereBetween('time', [$from, $to])->get();
        });
    }

    public function updateHumidity(Request $request)
    {
        return $this->updateRecord(function() use($request) {
            $sensor = $request->input('sensor');
            $value = $request->input('humidity');
            if(empty($sensor) || empty($value)) throw new Exception('Empty sensor id or humidity');
            Humidity::updateOrCreate(['sensor_id' => $sensor, 'time' => $this->getNow()], ['humidity' => $value]);
        });
    }
    public function selectHumidity(Request $request)
    {
        return $this->selectRecord($request, function($from,$to) use($request) {
            $sensor = $request->input('sensor');
            if(empty($sensor)) throw new Exception('Empty sensor id');
            return Humidity::where(['sensor_id' => $sensor])->whereBetween('time', [$from, $to])->get();
        });
    }

    public function updateExperiment(Request $request)
    {
        return $this->updateRecord(function() use($request) {
            $sensor = $request->input('sensor');
            $a = $request->input('a');
            $b = $request->input('b');
            if(empty($sensor) || empty($a) || empty($b)) throw new Exception('Empty sensor id or values');
            Experiment::updateOrCreate(['sensor_id' => $sensor, 'time' => Carbon::now()], ['a' => $a, 'b' => $b]);
        });
    }

    public function updateImage(Request $request)
    {
        $s = MySession::factory();
        try
        {
            if(!$request->file('image') || !$request->file('image')->isValid()) throw new Exception('Broken image.');
            if(!$request->has('sensor_id')) throw new Exception("No sensor id.");

            $extension = $request->file('image')->getClientOriginalExtension();
            $now = Carbon::now();
            $folder = sprintf("images/s%04d/%s", $request->input('sensor_id'), $now->format('ym'));
            $filename = sprintf("%s.%s", $now->format('ymdH00'), $extension);

            Storage::putFileAs($folder, $request->file('image'), $filename);
            $s->add('filename', $filename);
        }
        catch(Exception $ex)
        {
            $s->addException($ex);
        }
        return response()->json($s->toApi());
    }

    public function getImage($id, $time, Request $request)
    {
        $s = MySession::factory();
        try
        {
            $folder = substr($time, 0, 4);
            $path = sprintf("images/s%04d/%s/%s", $id, $folder, $time);
            if(!Storage::exists($path)) throw new Exception('Not exist the image.');
            return Storage::download($path);
        }
        catch(Exception $ex)
        {
            $s->addException($ex);
            return response()->json($s->toApi());
        }
    }

    // Common process.
    private function updateRecord($func)
    {
        $s = MySession::factory();
        try
        {
            $func();
        }
        catch(Exception $ex)
        {
            $s->addException($ex);
        }
        return response()->json($s->toApi());
    }

    private function selectRecord(Request $request, $func)
    {
        $s = MySession::factory();
        try
        {
            $from = Carbon::parse($request->input('from', '-3 day'));
            $from->setTime($from->hour, 0); // Just time
            $to = Carbon::parse($request->input('to', 'now'));
            $interval = $request->input('interval', 1);
            if($interval < 1) $interval = 1;

            $times = [];
            foreach($func($from,$to) as $row)
            {
                $key = $row->time->format('Y-m-d H:00');
                $times[$key] = $row->getValue();
            }
            $records = [];
            for($t = clone($from); $t <= $to; $t->modify("+{$interval} hour"))
            {
                $key = $t->format('Y-m-d H:00');
                $value = isset($times[$key]) ? $times[$key]: null;
                $records[] = ['time' => $key, 'value' => $value];
            }
            $s->add('records', $records);
        }
        catch(Exception $ex)
        {
            $s->addException($ex);
        }
        return response()->json($s->toApi());
    }

    private function getNow()
    {
        $now = Carbon::now();
        $now->setTime($now->hour, $now->minute, 0);
        return $now;
    }
}
