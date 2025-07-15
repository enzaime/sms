<?php

namespace Enzaime\Sms;

use Enzaime\Sms\Contracts\SmsContract;

/**
 * Class SmsService
 *
 * Main service for sending SMS using different drivers.
 */
class SmsService implements SmsContract
{
    /**
     * The driver manager instance.
     */
    private DriverManager $manager;

    /**
     * The current driver name.
     */
    private string $driver = '';

    /**
     * SmsService constructor.
     */
    public function __construct()
    {
        $this->manager = new DriverManager;
    }

    /**
     * Set driver to send SMS.
     *
     * @return $this
     */
    public function driver(string $name = ''): self
    {
        $this->driver = $name;

        return $this;
    }

    /**
     * Send SMS to one or multiple numbers.
     */
    public function send(string|array $numberOrList, string $text): int|mixed
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
     */
    public function isLocal(string $number): bool
    {
        $pattern = config('sms.local_number_regex');

        return $pattern ? preg_match($pattern, $number) : true;
    }

    /**
     * Get the current driver instance.
     */
    public function getDriver(string $driver = ''): SmsContract
    {
        return $this->manager->getDriver($driver ?: $this->driver);
    }

    /**
     * Get the fallback driver instance.
     */
    public function getFallbackDriver(): SmsContract
    {
        return $this->getDriver($this->driver ?: config('sms.fallback'));
    }

    /**
     * Dynamically call methods on the driver instance.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->getDriver()->{$method}(...$arguments);
    }
}
