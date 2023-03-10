<?php

namespace Enzaime\Sms;

use Enzaime\Sms\Contracts\ClientInterface;
use Illuminate\Support\Str;

class DriverManager
{
    /**
     * Return driver to send SMS
     *
     * @param  string  $name
     * @return SmsContract
     */
    public function getDriver($name = ''): ClientInterface
    {
        $name = $name ?: $this->defaultDriverName();

        return $this->createDriver($name);
    }

    protected function createDriver($name)
    {
        $name = Str::studly($name);

        return app()->make('\\Enzaime\\Sms\\Drivers\\'.$name);
    }

    protected function defaultDriverName()
    {
        return config('sms.default');
    }
}
