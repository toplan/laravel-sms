<?php

Route::get('sms/info', 'Toplan\Sms\SmsController@getInfo');

Route::post('sms/verify-code/rule/{rule}/mobile/{mobile?}', 'Toplan\Sms\SmsController@postSendCode');

Route::post('sms/voice-verify/rule/{rule}/mobile/{mobile?}', 'Toplan\Sms\SmsController@postVoiceVerify');
