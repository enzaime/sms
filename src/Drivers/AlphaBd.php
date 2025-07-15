<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\SmsContract;
use Exception;
use Illuminate\Support\Facades\Http;

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
     * @param  string  $text
     * @return int|mixed
     */
    public function send($numberOrList, $text)
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
