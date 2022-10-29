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

Route::prefix('hy')->group(function () {
    //首页
    Route::prefix('venue')->group(function () {
        Route::get('lists', 'VenueController@lists');
        Route::get('day', 'VenueController@day');
        Route::get('hour', 'VenueController@hour');
    });
    //支付
    Route::prefix('pay')->group(function () {
        Route::post('send', 'PayController@send');
        Route::get('result', 'PayController@result');
        Route::post('payment', 'PayController@payment');
        Route::post('refund', 'PayController@refund');
    });
    //订单
    Route::prefix('order')->group(function () {
        Route::get('lists', 'OrderController@lists');
    });
    //测试接口
    Route::prefix('test')->group(function () {
        Route::get('check', 'TestController@check');
        Route::get('info', 'TestController@info');
        Route::get('all', 'TestController@all');
        Route::get('image', 'TestController@image');
    });
});

