<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pressure extends Model
{
    protected $table = 'pressures';
    protected $fillable = ['sensor_id', 'time', 'pressure'];
    protected $dates = ['time'];
    public $timestamps = false;

    public function getValue(){ return $this->pressure; }
}