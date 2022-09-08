<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\ClientInterface;
use Exception;
use Twilio\Rest\Client;

class Twilio implements ClientInterface
{
    /**
     * @var \Twilio\Rest\Client
     */
    private $client;

    /**
     * @var string
     */
    private $twilioNumber;

    /**
     * Set twilio number from which sms will be sent
     *
     * @param  string  $number
     * @return void
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
     * @param  string  $text
     * @param  string|null  $type
     * @return int|mixed
     */
    public function send($numberOrList, $text, $type = null)
    {
        $client = $this->getClient();

        if (! is_array($numberOrList)) {
            return $client->messages->create(
                $numberOrList,
                [
                    'from' => $this->getFromNumber(),
                    'body' => $text,
                ]
            );
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
     *
     * @return \Twilio\Rest\Client
     */
    public function getClient()
    {
        if (! $this->client) {
            $config = $this->config();

            $this->client = new Client($config['sid'], $config['token']);
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
