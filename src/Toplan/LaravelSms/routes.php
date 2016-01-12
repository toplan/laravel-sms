<?php

Route::get('sms/info/{token?}', 'Toplan\Sms\SmsController@getInfo');

Route::post('sms/verify-code', 'Toplan\Sms\SmsController@postSendCode');

Route::post('sms/voice-verify', 'Toplan\Sms\SmsController@postVoiceVerify');
