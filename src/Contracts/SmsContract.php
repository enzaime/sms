<?php

namespace Enzaime\Sms\Contracts;

/**
 * Interface SmsContract
 *
 * Contract for sending SMS messages.
 */
interface SmsContract
{
    /**
     * Send SMS.
     *
     * @param  string|array  $numberOrNumberList
     * @param  string  $text
     * @return int|mixed
     */
    public function send($number, $text);
}
