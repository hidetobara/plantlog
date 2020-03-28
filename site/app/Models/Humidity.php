<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Humidity extends Model
{
    protected $table = 'humidities';
    protected $fillable = ['sensor_id', 'time', 'humidity'];
    protected $dates = ['time'];
    public $timestamps = false;

    public function getValue(){ return $this->humidity; }
}