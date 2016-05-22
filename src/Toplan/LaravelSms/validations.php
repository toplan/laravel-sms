<?php

Validator::extend('zh_mobile', function ($attribute, $value) {
    return preg_match('/^(\+?0?86\-?)?((13\d|14[57]|15[^4,\D]|17[678]|18\d)\d{8}|170[059]\d{7})$/', $value);
});

Validator::extend('confirm_mobile_not_change', function ($attribute, $value) {
    $state = SmsManager::retrieveState();

    return $state && $state['to'] === $value;
});

Validator::extend('verify_code', function ($attribute, $value) {
    $state = SmsManager::retrieveState();

    return $state && $state['deadline'] >= time() && $state['code'] === $value;
});

Validator::extend('confirm_rule', function ($attribute, $value, $parameters) {
    $state = SmsManager::retrieveState();
    $field = isset($parameters[0]) ? $parameters[0] : null;
    $name = null;
    if (array_key_exists(1, $parameters)) {
        $name = $parameters[1];
    } else {
        try {
            $parsed = parse_url(url()->previous());
            $name = $parsed['path'];
        } catch (\Exception $e) {
            //swallow exception
        }
    }

    return $state && array_key_exists($field, $state['usedRule']) && $state['usedRule'][$field] === $name;
});
