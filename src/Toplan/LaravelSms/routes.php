<?php

$attributes = array_merge([
    'prefix' => 'laravel-sms',
], config('laravel-sms.routeAttributes', []));

Route::group($attributes, function () {
    Route::get('info', 'Toplan\Sms\SmsController@getInfo');
    Route::post('verify-code', 'Toplan\Sms\SmsController@postSendCode');
    Route::post('voice-verify', 'Toplan\Sms\SmsController@postVoiceVerify');
});
