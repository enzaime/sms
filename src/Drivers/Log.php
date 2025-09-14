<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\SmsContract;
use Illuminate\Support\Facades\Log as LaravelLog;

/**
 * Class Log
 *
 * SMS driver that logs messages instead of sending them.
 * Useful for local development and staging environments.
 */
class Log implements SmsContract
{
    /**
     * Send SMS by logging the message.
     *
     * @return int Number of messages logged
     */
    public function send(string|array $numberOrNumberList, string $text): int
    {
        $numbers = is_array($numberOrNumberList) ? $numberOrNumberList : [$numberOrNumberList];
        foreach ($numbers as $number) {
            LaravelLog::info('[SMS][LOG DRIVER]', [
                'number' => $number,
                'text' => $text,
            ]);
        }

        return count($numbers);
    }
}
