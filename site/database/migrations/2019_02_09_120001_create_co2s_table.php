<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateCo2sTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('co2s', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('sensor_id');
            $table->datetime('time');
            $table->integer('co2');
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
        Schema::dropIfExists('co2s');
    }
}
