<?php

Validator::extend('mobile', function($attribute, $value, $parameters) {
    return preg_match('/^1[3|5|7|8|][0-9]{9}$/', $value);
});

Validator::extend('mobile_changed', function ($attribute, $value, $parameters) {
    $smsData = SmsManager::getSmsDataFromSession();
    if ($smsData && $smsData['mobile'] == $value) {
        return true;
    }
    return false;
});

Validator::extend('verify_code', function ($attribute, $value, $parameters) {
    $smsData = SmsManager::getSmsDataFromSession();
    if ($smsData && $smsData['deadline_time'] >= time() && $smsData['code'] == $value) {
        return true;
    }
    return false;
});

Validator::extend('verify_rule', function($attribute, $value, $parameters) {
    $smsData = SmsManager::getSmsDataFromSession();
    if ($smsData && $smsData['verify']['mobile']['choose_rule'] == $parameters[0]) {
        return true;
    }
    return false;
});