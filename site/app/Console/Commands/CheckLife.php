<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\NotificationMailable;
use App\Models\Obniz;
use App\Models\User;


class CheckLife extends Command
{
    protected $signature = 'command:check_life';
    protected $description = 'check life of sensors';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $rows = DB::table('temperatures')
            ->select('sensor_id', DB::raw('MAX(time) as last_time'))->groupBy('sensor_id')->get();
        $upper = Carbon::now()->subHours(6);
        $lower = Carbon::now()->subDays(7);
        foreach($rows as $row)
        {
            if($lower < $row->last_time && $row->last_time < $upper)
            {
                $obniz = Obniz::find($row->sensor_id);
                if(!$obniz) continue;
                $user = User::find($obniz->user_id);
                if(!$user) continue;
                Mail::to($user->email)->send((new NotificationMailable)->setStoppedSensor($row->sensor_id, $row->last_time));
            }
        }
    }
}
