<?php


use Illuminate\Routing\Router;

Admin::routes();

Route::group([
    'prefix'        => config('admin.route.prefix'),
    'namespace'     => config('admin.route.namespace'),
    'middleware'    => config('admin.route.middleware'),
], function (Router $router) {

    $router->get('/', 'HomeController@index')->name('admin.home');
    $router->resource('profiles', ProfileController::class);
    $router->resource('downloadqueue', DownloadQueueController::class);
    $router->resource('transcodingqueue', TranscodingQueueController::class);

    $router->get('/auth/login', '\\App\\Admin\\Controllers\\AuthController@getLogin');
    $router->post('/auth/login', '\\App\\Admin\\Controllers\\AuthController@postLogin');
    $router->get('auth/setting', '\\App\\Admin\\Controllers\\AuthController@getSetting');
    $router->put('auth/setting', '\\App\\Admin\\Controllers\\AuthController@putSetting');


    $router->get('auth/users/{id]', '\\App\\Admin\\Controllers\\UserController@detail');
    $router->resources([
        'users'                 => UserController::class,
    ]);
});

