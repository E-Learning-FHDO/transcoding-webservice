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


use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Auth::routes();

Route::redirect('/',  admin_url('auth/login'));

Route::group(['prefix' => 'api', 'middleware' => ['auth:api']], function(){
    Route::post('/transcode', 'DownloadController@store')->name('download');
    Route::get('/download/{filename}', 'VideoController@getFile')->name('getFile');
    Route::post('/download/{filename}/finished', 'VideoController@setDownloadFinished')->name('setDownloadFinished');
    Route::get('/status/{mediakey}', 'VideoController@getStatus')->name('getStatus');
    Route::delete('/delete/{mediakey}', 'VideoController@deleteAllByMediakey')->name('deleteAllByMediakey');
    Route::post('/testurl', 'VideoController@testUrl');

    Route::group(['prefix' => 'jobs'], function(){
        Route::get('/video', 'VideoController@jobs')->name('downloadJobs');
        Route::get('/download', 'DownloadController@jobs')->name('videoJobs');
    });
});


