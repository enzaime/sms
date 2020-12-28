<?php

namespace Enzaime\Sms\Drivers;

use Enzaime\Sms\Contracts\ClientInterface;
use SoapClient;

class Onnorokom implements ClientInterface
{
    private $campaignName = '';
    private $client = null;

    /**
     * Send SMS
     *
     * @param string|array $numberOrNumberList
     * @param string $text
     * @param string|null $type
     * @return int|mixed
     */
    public function send($mobileNumberOrList, $text, $type = null)
    {
        if (!is_array($mobileNumberOrList)) {
            return $this->oneToOne($mobileNumberOrList, $text, $type = 'text');
        }
        
        $successCount = 0;

        foreach ($mobileNumberOrList as $mobileNumber) {
            //Call oneToOne method of SoapClient
            $resp = $this->oneToOne($mobileNumber, $text, $type = 'text');
            $successCount += $resp ? 1 : 0;
        }

        return $successCount;
    }   

    public function getClient()
    {
        if (!$this->client) {
            $this->client = new SoapClient("https://api2.onnorokomSMS.com/sendSMS.asmx?wsdl");
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
        // Multiple numbers are separated by comma(,)
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
}