<?php

namespace Enzaime\Sms\Contracts;

interface SmsContract
{
    /**
     * Send SMS
     *
     * @param string|array $numberOrNumberList
     * @param string $text
     * @param string|null $type
     * @return int|mixed
     */
    public function send($number, $text, $type = null);
}