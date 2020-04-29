<?php

namespace Enzaime\Sms;

use Illuminate\Support\Facades\Facade;

class SmsFacade extends Facade
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