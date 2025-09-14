<?php

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\Log;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Support\Facades\Log as LaravelLog;

/**
 * @covers \Enzaime\Sms\Drivers\Log
 */
class LogTest extends TestCase
{
    public function testSendSingleNumber()
    {
        LaravelLog::shouldReceive('info')
            ->once()
            ->with(
                '[SMS][LOG DRIVER]', [
                'number' => '01700000000',
                'text' => 'Test message',
                ]
            );
        $driver = new Log();
        $result = $driver->send('01700000000', 'Test message');
        $this->assertEquals(1, $result);
    }

    public function testSendMultipleNumbers()
    {
        LaravelLog::shouldReceive('info')
            ->twice();
        $driver = new Log();
        $result = $driver->send(['01700000000', '01800000000'], 'Test message');
        $this->assertEquals(2, $result);
    }
} 