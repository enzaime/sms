<?php

namespace Enzaime\Sms\Facades;

use Enzaime\Sms\SmsService;
use Illuminate\Support\Facades\Facade;

class EnzSms extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     *
     * @throws \RuntimeException
     */
    protected static function getFacadeAccessor()
    {
        return new SmsService();
    }
}
