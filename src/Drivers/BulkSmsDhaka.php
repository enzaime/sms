<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\SmsContract;
use Exception;
use Illuminate\Support\Facades\Http;

/**
 * Bulk SMS Dhaka driver integration.
 *
 * @see https://bulksmsdhaka.net The admin dashboard.
 */
class BulkSmsDhaka implements SmsContract
{
    /**
     * Send SMS
     *
     * @param  string|array  $numberOrList
     */
    public function send(string|array $numberOrList, string $text): int
    {
        $numbers = is_array($numberOrList) ? $numberOrList : [$numberOrList];

        $successCount = 0;

        foreach ($numbers as $number) {
            $successCount += $this->sendToOne($number, $text) ? 1 : 0;
        }

        return $successCount;
    }

    /**
     * Send SMS to a single recipient number.
     */
    protected function sendToOne(string $number, string $text): bool
    {
        try {
            $response = Http::get($this->getEndPoint(), [
                'apikey' => $this->getApiKey(),
                'callerID' => $this->getCallerId(),
                'number' => $number,
                'message' => $text,
            ]);
        } catch (Exception $ex) {
            return false;
        }

        return $response->successful();
    }

    /**
     * Return bulksmsdhaka config
     *
     * @return array
     */
    protected function config()
    {
        return config('sms.drivers.bulksmsdhaka');
    }

    protected function getEndPoint(): string
    {
        return $this->config()['api_url'];
    }

    protected function getApiKey(): string
    {
        return $this->config()['api_key'];
    }

    protected function getCallerId(): string
    {
        return $this->config()['caller_id'] ?? '';
    }
}
