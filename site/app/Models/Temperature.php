<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temperature extends Model
{
    protected $table = 'temperatures';
    protected $fillable = ['sensor_id', 'time', 'temperature'];
    protected $dates = ['time'];
    public $timestamps = false;

    public function getValue(){ return $this->co2; }
}