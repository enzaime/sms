<?php

namespace Enzaime\Sms;

use Enzaime\Sms\Contracts\SmsContract;

class SmsService implements SmsContract
{
    /**
     * @var DriverManager
     */
    private $manager;

    /**
     * @var string
     */
    private $driver = '';

    public function __construct()
    {
        $this->manager = new DriverManager();
    }

    /**
     * Set driver to send SMS
     *
     * @param  string  $name
     * @return Enzaime\Sms\SmsService
     */
    public function driver($name = '')
    {
        $this->driver = $name;

        return $this;
    }

    /**
     * Send SMS
     *
     * @param  string|array  $numberOrNumberList
     * @param  string  $text
     * @param  string  $type
     * @return int|mixed
     */
    public function send($numberOrList, $text, $type = '')
    {
        if (! is_array($numberOrList)) {
            $driver = $this->isLocal($numberOrList)
                ? $this->getDriver()
                : $this->getFallbackDriver();

            return $driver->send($numberOrList, $text, $type = '');
        }

        $locals = [];
        $foreign = [];

        foreach ($numberOrList as $number) {
            if ($this->isLocal($number)) {
                $locals[] = $number;
            } else {
                $foreign[] = $number;
            }
        }

        $count = 0;

        if (count($locals)) {
            $count = $this->getDriver()->send($locals, $text, $type);
        }

        if (count($foreign)) {
            $count += $this->getFallbackDriver()->send($foreign, $text, $type);
        }

        return $count;
    }

    public function isLocal(string $number)
    {
        $pattern = config('sms.local_number_regex');

        return $pattern ? preg_match($pattern, $number) : true;
    }

    /**
     * Undocumented function
     *
     * @return SmsContract
     */
    public function getDriver($driver = '')
    {
        return $this->manager->getDriver($driver ?: $this->driver);
    }

    /**
     * Return fallback driver if $this->driver is specified
     *
     * @return SmsContract
     */
    public function getFallbackDriver()
    {
        return $this->getDriver($this->driver ?: config('sms.fallback'));
    }

    public function __call($method, $arguments)
    {
        return $this->getDriver()->{$method}(...$arguments);
    }
}
