<?php

Route::get('sms/info', 'Toplan\Sms\SmsController@getInfo');

Route::post('sms/verify-code/mobile/{mobile?}/rule/{rule?}', 'Toplan\Sms\SmsController@postSendCode');

Route::post('sms/voice-verify/mobile/{mobile?}/rule/{rule?}', 'Toplan\Sms\SmsController@postVoiceVerify');
