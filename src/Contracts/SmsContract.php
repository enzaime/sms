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
     */
    public function send(string|array $number, string $text): int;
}
