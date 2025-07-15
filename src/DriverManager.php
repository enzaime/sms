<?php

namespace Enzaime\Sms;

use Enzaime\Sms\Contracts\SmsContract;
use Illuminate\Support\Str;

/**
 * Class DriverManager
 *
 * Responsible for resolving and creating SMS drivers.
 */
class DriverManager
{
    /**
     * Return driver to send SMS.
     */
    public function getDriver(string $name = ''): SmsContract
    {
        $name = $name ?: $this->defaultDriverName();

        return $this->createDriver($name);
    }

    /**
     * Create a driver instance by name.
     */
    protected function createDriver(string $name): SmsContract
    {
        $name = Str::studly($name);

        return app()->make('\\Enzaime\\Sms\\Drivers\\'.$name);
    }

    /**
     * Get the default driver name from config.
     */
    protected function defaultDriverName(): string
    {
        return config('sms.default');
    }
}
