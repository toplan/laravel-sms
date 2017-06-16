<?php

$routeAttr = config('laravel-sms.route', []);
unset($routeAttr['enable']);

$attributes = array_merge([
    'prefix' => 'laravel-sms',
], $routeAttr);

Route::group($attributes, function () {
    Route::get('info', 'Toplan\Sms\SmsController@getInfo');
    Route::post('verify-code', 'Toplan\Sms\SmsController@postSendCode');
    Route::post('voice-verify', 'Toplan\Sms\SmsController@postVoiceVerify');
});
