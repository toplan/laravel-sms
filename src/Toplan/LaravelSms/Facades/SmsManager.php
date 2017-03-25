<?php

namespace Toplan\Sms\Facades;

use Illuminate\Support\Facades\Facade;

class SmsManager extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Toplan\\Sms\\SmsManager';
    }
}
