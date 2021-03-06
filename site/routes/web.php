<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Auth::routes();

Route::get('/', 'GuestController@index');
Route::get('/guest/chart/{id}', 'GuestController@getChart');
Route::get('/guest/image/{id}', 'GuestController@getImage');
Route::get('/guest/changing/{id}', 'GuestController@getChanging');
Route::get('/guest/recent/{id}', 'GuestController@getRecent');
Route::get('/guest/images/{id}.json', 'GuestController@getImagesJson');

Route::get('/home', 'HomeController@index')->name('home');

Route::get('/sensor', 'SensorController@index');
Route::get('/sensor/chart/{id}', 'SensorController@getChart');
Route::get('/sensor/image/{id}', 'SensorController@getImage');
