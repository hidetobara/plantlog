<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
*/

Route::get('record/update_co2', 'Api\RecordController@updateCo2');
Route::get('record/select_co2', 'Api\RecordController@selectCo2');
Route::get('record/update_temperature', 'Api\RecordController@updateTemperature');
Route::get('record/select_temperature', 'Api\RecordController@selectTemperature');
Route::get('record/update_lux', 'Api\RecordController@updateLux');
Route::get('record/select_lux', 'Api\RecordController@selectLux');
Route::get('record/update_experiment', 'Api\RecordController@updateExperiment');
