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
Route::get('record/update_pressure', 'Api\RecordController@updatePressure');
Route::get('record/select_pressure', 'Api\RecordController@selectPressure');
Route::get('record/update_humidity', 'Api\RecordController@updateHumidity');
Route::get('record/select_humidity', 'Api\RecordController@selectHumidity');
Route::get('record/update_experiment', 'Api\RecordController@updateExperiment');
Route::post('record/image', 'Api\RecordController@updateImage');
Route::get('record/image/{id}/{time}', 'Api\RecordController@getImage');

