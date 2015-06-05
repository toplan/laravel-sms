<?php

Route::get('sms/info', 'Toplan\Sms\SmsController@getInfo');

Route::get('sms/verify-code/rule/{rule}/mobile/{mobile?}', 'Toplan\Sms\SmsController@getSendCode');
