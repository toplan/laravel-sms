<?php

use SmsManager;

$smsData = SmsManager::getSmsDataFromSession();

Validator::extend('mobile', function($attribute, $value, $parameters) {
    return preg_match('/^1[3|5|7|8|][0-9]{9}$/', $value);
});

Validator::extend('mobile_changed', function ($attribute, $value, $parameters) use ($smsData) {
    if ($smsData && $smsData['mobile'] == $value) {
        SmsManager::forgetSmsDataFromSession();
        return true;
    }
    return false;
});

Validator::extend('verify_code', function ($attribute, $value, $parameters) use ($smsData) {
    if ($smsData && $smsData['deadline_time'] >= time()) {
        if ($smsData['code'] == $value) {
            SmsManager::forgetSmsDataFromSession();
            return true;
        }
        return false;
    } else {
        return false;
    }
});
