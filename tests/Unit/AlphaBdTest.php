<?php

declare(strict_types=1);

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\AlphaBd;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class AlphaBdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set(
            'sms.drivers.alphabd', [
            'api_url' => 'https://fake.api',
            'api_key' => 'test-key',
            'sender_id' => 'SENDER',
            ]
        );
    }

    public function testSendSingleNumber()
    {
        Http::fake();
        $driver = new AlphaBd();
        $result = $driver->send('01700000000', 'Test message');
        Http::assertSent(
            function ($request) {
                $url = $request->url();
                $parts = parse_url($url);
                parse_str($parts['query'] ?? '', $query);
                return $parts['scheme'] === 'https'
                && $parts['host'] === 'fake.api'
                && $query['to'] === '01700000000'
                && $query['msg'] === 'Test message'
                && $query['api_key'] === 'test-key'
                && $query['sender_id'] === 'SENDER';
            }
        );
        $this->assertEquals(1, $result);
    }

    public function testSendMultipleNumbers()
    {
        Http::fake();
        $driver = new AlphaBd();
        $numbers = ['01700000000', '01800000000'];
        $result = $driver->send($numbers, 'Test message');
        Http::assertSent(
            function ($request) {
                $url = $request->url();
                $parts = parse_url($url);
                parse_str($parts['query'] ?? '', $query);
                return $parts['scheme'] === 'https'
                && $parts['host'] === 'fake.api'
                && $query['to'] === '01700000000,01800000000'
                && $query['msg'] === 'Test message'
                && $query['api_key'] === 'test-key'
                && $query['sender_id'] === 'SENDER';
            }
        );
        $this->assertEquals(2, $result);
    }
} 