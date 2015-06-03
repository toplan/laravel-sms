<?php

// 自定义验证
Validator::extend('mobile', function($attribute, $value, $parameters) {
    return preg_match('/^1[3|5|7|8|][0-9]{9}$/', $value);
});