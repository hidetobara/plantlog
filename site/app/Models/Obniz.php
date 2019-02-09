<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Obniz extends Model
{
    protected $table = 'obnizs';
    protected $fillable = ['name', 'user_id', 'description'];
}