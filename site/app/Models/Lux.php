<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lux extends Model
{
    protected $table = 'luxs';
    protected $fillable = ['sensor_id', 'time', 'lux'];
    protected $dates = ['time'];
    public $timestamps = false;

    public function getValue(){ return $this->lux; }
}