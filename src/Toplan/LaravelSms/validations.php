<?php

use Toplan\Sms\LaravelSmsException;

Validator::extend('zh_mobile', function ($attribute, $value, $parameters) {
    return preg_match('/^(\+?0?86\-?)?((13\d|14[57]|15[^4,\D]|17[678]|18\d)\d{8}|170[059]\d{7})$/', $value);
});

Validator::extend('confirm_mobile_not_change', function ($attribute, $value, $parameters) {
    $token = isset($parameters[0]) ? $parameters[0] : null;
    $smsData = SmsManager::retrieveSentInfo($token);
    if ($smsData && $smsData['mobile'] === $value) {
        return true;
    }

    return false;
});

Validator::extend('verify_code', function ($attribute, $value, $parameters) {
    $token = isset($parameters[0]) ? $parameters[0] : null;
    $smsData = SmsManager::retrieveSentInfo($token);
    if ($smsData && $smsData['deadline_time'] >= time() && $smsData['code'] === $value) {
        return true;
    }

    return false;
});

Validator::extend('confirm_mobile_rule', function ($attribute, $value, $parameters) {
    if (!isset($parameters[0])) {
        throw new LaravelSmsException('Please give validator rule [confirm_mobile_rule] a parameter');
    }
    $token = isset($parameters[1]) ? $parameters[1] : null;
    $smsData = SmsManager::retrieveSentInfo($token);
    if ($smsData && $smsData['verify']['mobile']['use'] === $parameters[0]) {
        return true;
    }

    return false;
});
