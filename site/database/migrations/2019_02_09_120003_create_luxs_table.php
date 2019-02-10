<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLuxsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('luxs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('sensor_id');
            $table->datetime('time');
            $table->integer('lux');
            $table->index(['sensor_id', 'time']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('luxs');
    }
}
