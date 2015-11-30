<?php
namespace Toplan\Sms;

use \Session;
class SessionStorage implements Storage
{
    public function set($key, $value)
    {
        Session::put($key, $value);
    }

    public function get($key, $default)
    {
        return Session::get($key, $default);
    }

    public function forget($key)
    {
        Session::forget($key);
    }
}