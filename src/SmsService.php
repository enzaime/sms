<?php

namespace Enzaime\Sms;

use Enzaime\Sms\Contracts\SmsContract;

/**
 * Class SmsService
 *
 * Main service for sending SMS using different drivers.
 *
 * @package Enzaime\Sms
 */
class SmsService implements SmsContract
{
    /**
     * The driver manager instance.
     *
     * @var DriverManager
     */
    private $manager;

    /**
     * The current driver name.
     *
     * @var string
     */
    private $driver = '';

    /**
     * SmsService constructor.
     */
    public function __construct()
    {
        $this->manager = new DriverManager();
    }

    /**
     * Set driver to send SMS.
     *
     * @param  string  $name
     * @return $this
     */
    public function driver($name = '')
    {
        $this->driver = $name;
        return $this;
    }

    /**
     * Send SMS to one or multiple numbers.
     *
     * @param  string|array  $numberOrList
     * @param  string  $text
     * @return int|mixed
     */
    public function send($numberOrList, $text)
    {
        if (! is_array($numberOrList)) {
            $driver = $this->isLocal($numberOrList)
                ? $this->getDriver()
                : $this->getFallbackDriver();

            return $driver->send($numberOrList, $text);
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
            $count = $this->getDriver()->send($locals, $text);
        }

        if (count($foreign)) {
            $count += $this->getFallbackDriver()->send($foreign, $text);
        }

        return $count;
    }

    /**
     * Determine if the given number is local (Bangladeshi).
     *
     * @param string $number
     * @return bool
     */
    public function isLocal(string $number)
    {
        $pattern = config('sms.local_number_regex');
        return $pattern ? preg_match($pattern, $number) : true;
    }

    /**
     * Get the current driver instance.
     *
     * @param string $driver
     * @return SmsContract
     */
    public function getDriver($driver = '')
    {
        return $this->manager->getDriver($driver ?: $this->driver);
    }

    /**
     * Get the fallback driver instance.
     *
     * @return SmsContract
     */
    public function getFallbackDriver()
    {
        return $this->getDriver($this->driver ?: config('sms.fallback'));
    }

    /**
     * Dynamically call methods on the driver instance.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        return $this->getDriver()->{$method}(...$arguments);
    }
}
