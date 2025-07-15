<?php

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\Onnorokom;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Support\Facades\Config;

class OnnorokomTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set(
            'sms.drivers.onnorokom', [
            'username' => 'test-user',
            'password' => 'test-pass',
            'api_key' => 'test-key',
            ]
        );
    }

    public function testSendSingleNumber()
    {
        $mockSoap = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['OneToOne'])
            ->getMock();
        $mockSoap->expects($this->once())
            ->method('OneToOne')
            ->willReturn(true);
        $driver = $this->getMockBuilder(Onnorokom::class)
            ->onlyMethods(['getClient'])
            ->getMock();
        $driver->method('getClient')->willReturn($mockSoap);
        $result = $driver->send('01700000000', 'Test message');
        $this->assertEquals(1, $result);
    }

    public function testSendMultipleNumbers()
    {
        $mockSoap = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['OneToOne'])
            ->getMock();
        $mockSoap->expects($this->exactly(2))
            ->method('OneToOne')
            ->willReturn(true);
        $driver = $this->getMockBuilder(Onnorokom::class)
            ->onlyMethods(['getClient'])
            ->getMock();
        $driver->method('getClient')->willReturn($mockSoap);
        $result = $driver->send(['01700000000', '01800000000'], 'Test message');
        $this->assertEquals(2, $result);
    }
} 