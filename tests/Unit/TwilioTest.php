<?php

declare(strict_types=1);

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\Twilio;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Twilio\Rest\Client as TwilioClient;

class TwilioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set(
            'sms.drivers.twilio', [
            'sid' => 'test-sid',
            'token' => 'test-token',
            'number' => '+1234567890',
            ]
        );
    }

    public function testSendSingleNumber()
    {
        $mockMessages = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['create'])
            ->getMock();
        $mockMessages->expects($this->once())
            ->method('create')
            ->with(
                '+8801700000000',
                [
                    'from' => '+1234567890',
                    'body' => 'Test message',
                ]
            );

        $mockTwilio = $this->getMockBuilder(TwilioClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockTwilio->messages = $mockMessages;
        $this->app->instance(TwilioClient::class, $mockTwilio);

        $driver = $this->getMockBuilder(Twilio::class)
            ->onlyMethods(['getClient'])
            ->getMock();
        $driver->method('getClient')->willReturn(app(TwilioClient::class));
        $driver->from('+1234567890');
        $result = $driver->send('+8801700000000', 'Test message');
        $this->assertEquals(1, $result);
    }

    public function testSendMultipleNumbers()
    {
        $mockMessages = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['create'])
            ->getMock();
        $mockMessages->expects($this->exactly(2))
            ->method('create');

        $mockTwilio = $this->getMockBuilder(TwilioClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockTwilio->messages = $mockMessages;
        $this->app->instance(TwilioClient::class, $mockTwilio);

        $driver = $this->getMockBuilder(Twilio::class)
            ->onlyMethods(['getClient'])
            ->getMock();
        $driver->method('getClient')->willReturn(app(TwilioClient::class));
        $driver->from('+1234567890');
        $result = $driver->send(['+8801700000000', '+8801800000000'], 'Test message');
        $this->assertEquals(2, $result);
    }
} 