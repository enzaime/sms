<?php

namespace Enzaime\Sms\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Enzaime\Sms\SmsService driver(string $name = '') Set driver to send SMS
 * @method static int|mixed send(string|array $numberOrList, string $text, string $type = '') Send SMS
 * @method static bool isLocal(string $number) Check if number is local
 * @method static \Enzaime\Sms\Contracts\SmsContract getDriver(string $driver = '') Get the current driver
 * @method static \Enzaime\Sms\Contracts\SmsContract getFallbackDriver() Get the fallback driver
 *
 * @see \Enzaime\Sms\SmsService
 */
class EnzSms extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sms';
    }
}
