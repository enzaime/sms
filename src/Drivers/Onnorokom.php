<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\ClientInterface;
use Enzaime\Sms\Contracts\SmsContract;
use SoapClient;

class Onnorokom implements ClientInterface, SmsContract
{
    private $campaignName = '';

    private $client = null;

    /**
     * Optional type for the SMS (settable).
     *
     * @var string|null
     */
    protected $type = null;

    /**
     * Set the type for the SMS.
     *
     * @return $this
     */
    public function setType(string $type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Send SMS
     *
     * @param  string|array  $numberOrNumberList
     * @return int|mixed
     */
    public function send(string|array $mobileNumberOrList, string $text): int
    {
        $type = $this->type ?? 'text';
        if (! is_array($mobileNumberOrList)) {
            return $this->oneToOne($mobileNumberOrList, $text, $type);
        }
        $successCount = 0;
        foreach ($mobileNumberOrList as $mobileNumber) {
            $resp = $this->oneToOne($mobileNumber, $text, $type);
            $successCount += $resp ? 1 : 0;
        }

        return $successCount;
    }

    public function getClient(): mixed
    {
        if (! $this->client) {
            $this->client = new SoapClient('https://api2.onnorokomSMS.com/sendSMS.asmx?wsdl');
        }

        return $this->client;
    }

    public function campaign(string $name)
    {
        $this->campaignName = $name;

        return $this;
    }

    protected function getCredentials()
    {
        return config('sms.drivers.onnorokom');
    }

    protected function getData($mobileNumber, $text, $type = 'text')
    {
        $data = [
            'type' => $type,
            'campaignName' => $this->campaignName,
        ];
        if (strpos($mobileNumber, ',') !== false) {
            $data['numberList'] = $mobileNumber;
            $data['messageText'] = $text;
        } else {
            $data['mobileNumber'] = $mobileNumber;
            $data['smsText'] = $text;
        }

        return array_merge($data, $this->getCredentials());
    }

    public function __call($name, $arguments)
    {
        $data = $this->getData(...$arguments);

        return $this->getClient()->__call($name, [$data]);
    }

    /**
     * Send a single SMS (internal helper).
     *
     * @param  string  $mobileNumber
     * @param  string  $text
     * @param  string  $type
     * @return mixed
     */
    protected function oneToOne($mobileNumber, $text, $type = 'text')
    {
        $data = $this->getData($mobileNumber, $text, $type);

        return $this->getClient()->OneToOne($data);
    }
}
