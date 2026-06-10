<?php

declare(strict_types=1);

use think\facade\Route;

Route::group('api/v1/auth', function () {
    Route::post('register', 'app\controller\AuthController@register');
    Route::post('login', 'app\controller\AuthController@login');
})->allowCrossDomain([
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Credentials' => 'false',
    'Access-Control-Allow-Methods' => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With',
  ]);

// tronusdt 回调：无需认证
Route::get('api/v1/payment/tronusdt-callback/:id', 'app\controller\PaymentController@tronusdtCallback')->allowCrossDomain([
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Credentials' => 'false',
  ]);

Route::group('api/v1', function () {
    Route::group('streamer', function () {
        Route::get('configs/:id', 'app\controller\StreamerController@detail');
        Route::get('configs', 'app\controller\StreamerController@configs');
        Route::post('upload-avatar', 'app\controller\StreamerController@uploadAvatar');
        Route::post('configs', 'app\controller\StreamerController@create');
        Route::put('configs', 'app\controller\StreamerController@update');
        Route::delete('configs/:id', 'app\controller\StreamerController@delete');
        // 预留：GET configs/:id/relationships - 查询智能体人际关系及关联智能体详情
    });

    Route::group('admin', function () {
        Route::get('users/configs', 'app\controller\AdminController@usersConfigs');
        Route::put('training-status', 'app\controller\AdminController@toggleTrainingStatus');
        Route::get('users', 'app\controller\AdminController@users');
        Route::put('users/:id/status', 'app\controller\AdminController@toggleUserStatus');
        Route::put('users/:id/kick', 'app\controller\AdminController@kickUser');
        Route::put('users/:id/quota', 'app\controller\AdminController@setUserQuota');
        Route::put('configs/:id/assign', 'app\controller\AdminController@assignConfig');
        Route::get('recharges', 'app\controller\AdminController@recharges');
        Route::put('recharges/:id/confirm', 'app\controller\AdminController@confirmRecharge');
        Route::put('recharges/:id/reject', 'app\controller\AdminController@rejectRecharge');
        Route::put('trc20-config', 'app\controller\AdminController@trc20Config');
    });

    Route::group('payment', function () {
        Route::get('balance', 'app\controller\PaymentController@balance');
        Route::get('trc20-address', 'app\controller\PaymentController@trc20Address');
        Route::post('recharge', 'app\controller\PaymentController@recharge');
        Route::get('recharge-records', 'app\controller\PaymentController@rechargeRecords');
        Route::post('purchase', 'app\controller\PaymentController@purchase');
        Route::get('purchase-records', 'app\controller\PaymentController@purchaseRecords');
    });

    Route::group('voice', function () {
        Route::post('command', 'app\controller\VoiceAgentController@command');
        Route::post('recognize', 'app\controller\VoiceAgentController@recognize');
        Route::get('status', 'app\controller\VoiceAgentController@status');
        Route::get('logs', 'app\controller\VoiceAgentController@logs');
        Route::get('xfyun-config', 'app\controller\VoiceAgentController@xfyunConfig');
    });
})->middleware(\app\middleware\Auth::class)
  ->allowCrossDomain([
    'Access-Control-Allow-Origin' => '*',
    'Access-Control-Allow-Credentials' => 'false',
    'Access-Control-Allow-Methods' => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers' => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-CSRF-TOKEN, X-Requested-With',
  ]);
