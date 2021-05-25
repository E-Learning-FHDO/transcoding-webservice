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

Route::group(['prefix' => 'api/v1', 'middleware' => ['auth:api', 'user.active']], function(){
    Route::post('/transcode', 'DownloadController@store')->name('download');
    Route::get('/download/{filename}', 'MediaController@getFile')->name('getFile');
    Route::post('/download/{filename}/finished', 'MediaController@setDownloadFinished')->name('setDownloadFinished');
    Route::get('/status/{mediakey}', 'MediaController@getMediaStatus')->name('getMediaStatus');
    Route::get('/status', 'MediaController@getServiceStatus')->name('getServiceStatus');
    Route::delete('/delete/{mediakey}', 'MediaController@deleteAllByMediakey')->name('deleteAllByMediakey');
    Route::post('/testurl', 'MediaController@testUrl');

    Route::group(['prefix' => 'jobs'], function(){
        Route::get('/media', 'MediaController@jobs')->name('downloadJobs');
        Route::get('/download', 'DownloadController@jobs')->name('mediaJobs');
    });
});


