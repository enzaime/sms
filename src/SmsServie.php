<?php

namespace Enzaime\Sms;

use SoapClient;

class SmsService
{
    private $campaignName = '';
    private $client = null;


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

    public function oneToOne($mobileNumber, $text, $type = 'text')
    {
        $data = $this->getData($mobileNumber, $text, $type = 'text');
            
        return $this->getClient()->__call("OneToOne", [$data]);
    }

    protected function getCredentials()
    {
        return config('sms');
    }

    protected function getData($mobileNumber, $text, $type = 'text')
    {
        $data = [
            'mobileNumber' => $mobileNumber,
            'smsText' => $text,
            'type' => $type,
            'campaignName' => $this->campaignName,
        ];

        return array_merge($data, $this->getCredentials());
    }
}