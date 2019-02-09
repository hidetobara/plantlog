<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Experiment extends Model
{
    protected $table = 'experiments';
    protected $fillable = ['sensor_id', 'time', 'a', 'b'];
    protected $dates = ['time'];
    public $timestamps = false;
}