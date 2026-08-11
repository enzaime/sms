<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\SmsContract;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Alpha SMS driver integration.
 *
 * @see https://portal.sms.net.bd The admin dashboard.
 */
class AlphaBd implements SmsContract
{
    /**
     * Send SMS
     *
     * @param  string|array  $numberOrNumberList
     * @return int|mixed
     */
    public function send(string|array $numberOrList, string $text): int
    {
        $successCount = 1;
        if (is_array($numberOrList)) {
            $successCount = count($numberOrList);
            $numberOrList = implode(',', $numberOrList);
        }
        try {
            $response = Http::get($this->getEndPoint(), [
                'api_key' => $this->getApiKey(),
                'to' => $numberOrList,
                'msg' => $text,
                'sender_id' => $this->getSenderId(),
            ]);
        } catch (Exception $ex) {
            Log::error('[SMS][AlphaBd] send failed', [
                'number' => $numberOrList,
                'exception' => $ex->getMessage(),
            ]);

            return 0;
        }

        // The count is this driver's only report to its caller, so returning it
        // without reading the response claimed every refused send as delivered
        // — including the ones a gateway rejects with a perfectly clear 4xx.
        if (! $response->successful()) {
            Log::error('[SMS][AlphaBd] send failed', [
                'number' => $numberOrList,
                'status' => $response->status(),
                'response' => Str::limit($response->body(), 500),
            ]);

            return 0;
        }

        return $successCount;
    }

    /**
     * Return twilio config
     *
     * @return array
     */
    protected function config()
    {
        return config('sms.drivers.alphabd');
    }

    protected function getEndPoint(): string
    {
        return $this->config()['api_url'];
    }

    protected function getApiKey(): string
    {
        return $this->config()['api_key'];
    }

    protected function getSenderId(): string
    {
        return $this->config()['sender_id'] ?? '';
    }
}
