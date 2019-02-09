<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Co2 extends Model
{
    protected $table = 'co2s';
    protected $fillable = ['sensor_id', 'time', 'co2'];
    protected $dates = ['time'];
    public $timestamps = false;

    public function getValue(){ return $this->co2; }
}