<?php

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\Log;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Support\Facades\Log as LaravelLog;
use Mockery;

/**
 * @covers \Enzaime\Sms\Drivers\Log
 */
class LogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Proxy partial mock: PHP deprecation warnings raised while the
        // facade is mocked are forwarded to
        // Log::channel('deprecations')->warning(), so unexpected calls must
        // fall through to the real LogManager instead of failing the info()
        // expectations.
        LaravelLog::swap(Mockery::mock($this->app->make('log'))->makePartial());
    }

    public function test_send_single_number()
    {
        LaravelLog::shouldReceive('info')
            ->once()
            ->with(
                '[SMS][LOG DRIVER]', [
                    'number' => '01700000000',
                    'text' => 'Test message',
                ]
            );
        $driver = new Log;
        $result = $driver->send('01700000000', 'Test message');
        $this->assertEquals(1, $result);
    }

    public function test_send_multiple_numbers()
    {
        LaravelLog::shouldReceive('info')
            ->twice();
        $driver = new Log;
        $result = $driver->send(['01700000000', '01800000000'], 'Test message');
        $this->assertEquals(2, $result);
    }
}
