<?php
use Toplan\Sms\LaravelSmsException;

Validator::extend('zh_mobile', function($attribute, $value, $parameters) {
    return preg_match('/^(\+80)*1[3|5|7|8][0-9]{9}$/', $value);
});

Validator::extend('confirm_mobile', function ($attribute, $value, $parameters) {
    $uuid = isset($parameters[0]) ? $parameters[0] : null;
    $smsData = SmsManager::getSentInfoFromStorage($uuid);
    if ($smsData && $smsData['mobile'] == $value) {
        return true;
    }
    return false;
});

Validator::extend('verify_code', function ($attribute, $value, $parameters) {
    $uuid = isset($parameters[0]) ? $parameters[0] : null;
    $smsData = SmsManager::getSentInfoFromStorage($uuid);
    if ($smsData && $smsData['deadline_time'] >= time() && $smsData['code'] == $value) {
        return true;
    }
    return false;
});

Validator::extend('confirm_mobile_rule', function($attribute, $value, $parameters) {
    if (!isset($parameters[0])) {
        throw new LaravelSmsException('Please give validator rule [confirm_mobile_rule] a parameter');
    }
    $uuid = isset($parameters[1]) ? $parameters[1] : null;
    $smsData = SmsManager::getSentInfoFromStorage($uuid);
    if ($smsData && $smsData['verify']['mobile']['use'] == $parameters[0]) {
        return true;
    }
    return false;
});