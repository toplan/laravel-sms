<?php

Route::group([
    'prefix' => 'sms',
    'middleware' => config('laravel-sms.middleware', 'web')
], function () {
    Route::get('info/{token?}', 'Toplan\Sms\SmsController@getInfo');
    Route::post('verify-code', 'Toplan\Sms\SmsController@postSendCode');
    Route::post('voice-verify', 'Toplan\Sms\SmsController@postVoiceVerify');
});
