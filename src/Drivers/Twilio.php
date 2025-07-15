<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\ClientInterface;
use Enzaime\Sms\Contracts\SmsContract;
use Exception;

class Twilio implements ClientInterface, SmsContract
{
    /**
     * @var \Twilio\Rest\Client|null
     */
    private $client;

    /**
     * @var string
     */
    private $twilioNumber;

    /**
     * Set twilio number from which sms will be sent
     *
     * @return $this
     */
    public function from(string $number)
    {
        $this->twilioNumber = $number;

        return $this;
    }

    /**
     * Send SMS
     *
     * @param  string|array  $numberOrNumberList
     * @return int|mixed
     */
    public function send(string|array $numberOrList, string $text): int
    {
        $client = $this->getClient();
        if (! is_array($numberOrList)) {
            $client->messages->create(
                $numberOrList,
                [
                    'from' => $this->getFromNumber(),
                    'body' => $text,
                ]
            );

            return 1;
        }
        $successCount = 0;
        foreach ($numberOrList as $number) {
            try {
                $client->messages->create(
                    $number,
                    [
                        'from' => $this->getFromNumber(),
                        'body' => $text,
                    ]
                );
                $successCount++;
            } catch (Exception $ex) {
            }
        }

        return $successCount;
    }

    /**
     * Return twilio client
     */
    public function getClient(): ?\Twilio\Rest\Client
    {
        if (! $this->client) {
            $config = $this->config();
            $this->client = new \Twilio\Rest\Client($config['sid'], $config['token']);
        }

        return $this->client;
    }

    /**
     * Return twilio config
     *
     * @return array
     */
    protected function config()
    {
        return config('sms.drivers.twilio');
    }

    /**
     * Return twilio number from which sms will be sent
     *
     * @return string
     */
    public function getFromNumber()
    {
        if (! $this->twilioNumber) {
            $this->twilioNumber = $this->config()['number'];
        }

        return $this->twilioNumber;
    }
}
