<?php

namespace Enzaime\Sms;

use Enzaime\Sms\Contracts\SmsContract;
use Illuminate\Support\Str;

/**
 * Class DriverManager
 *
 * Responsible for resolving and creating SMS drivers.
 *
 * @package Enzaime\Sms
 */
class DriverManager
{
    /**
     * Return driver to send SMS.
     *
     * @param  string  $name
     * @return SmsContract
     */
    public function getDriver($name = ''): SmsContract
    {
        $name = $name ?: $this->defaultDriverName();
        return $this->createDriver($name);
    }

    /**
     * Create a driver instance by name.
     *
     * @param string $name
     * @return SmsContract
     */
    protected function createDriver($name)
    {
        $name = Str::studly($name);
        return app()->make('\\Enzaime\\Sms\\Drivers\\'.$name);
    }

    /**
     * Get the default driver name from config.
     *
     * @return string
     */
    protected function defaultDriverName()
    {
        return config('sms.default');
    }
}
