<?php

Route::group([
    'prefix'     => 'laravel-sms',
    'middleware' => config('laravel-sms.middleware', 'web'),
], function () {
    Route::get('info', 'Toplan\Sms\SmsController@getInfo');
    Route::post('verify-code', 'Toplan\Sms\SmsController@postSendCode');
    Route::post('voice-verify', 'Toplan\Sms\SmsController@postVoiceVerify');
});
