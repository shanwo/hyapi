<?php

use Illuminate\Support\Facades\Route;

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

Route::redirect('/', 'https://shanwo.cc');

//首页
Route::prefix('venue')->group(function () {
    Route::get('lists', 'VenueController@lists');
    Route::get('images', 'VenueController@images');
});
//支付
Route::prefix('swpay')->group(function () {
    Route::get('lists', 'PayController@lists');
    Route::post('send', 'PayController@send');
    Route::post('result', 'PayController@result');
    Route::post('payment', 'PayController@payment');
    Route::post('refund', 'PayController@refund');
});
//测试接口
Route::prefix('test')->group(function () {
    Route::get('initial', 'TestController@initial');
    Route::get('check', 'TestController@check');
    Route::get('info', 'TestController@info');
    Route::get('all', 'TestController@all');
    Route::get('image', 'TestController@image');
});

