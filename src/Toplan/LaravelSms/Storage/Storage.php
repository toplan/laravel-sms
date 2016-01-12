<?php

namespace Toplan\Sms;

interface Storage
{
    public function set($key, $value);

    public function get($key, $default);

    public function forget($key);
}
