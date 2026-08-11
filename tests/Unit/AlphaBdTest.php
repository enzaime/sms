<?php

declare(strict_types=1);

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\AlphaBd;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    public function test_send_single_number()
    {
        Http::fake();
        $driver = new AlphaBd;
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

    public function test_send_multiple_numbers()
    {
        Http::fake();
        $driver = new AlphaBd;
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

    /**
     * The count is this driver's only report to its caller, and it was returned
     * without ever reading the response — so a gateway refusing the send was
     * indistinguishable from one accepting it.
     */
    public function test_refused_send_is_not_counted_as_delivered()
    {
        Http::fake(['*' => Http::response('Forbidden', 403)]);

        $this->assertEquals(0, (new AlphaBd)->send('01700000000', 'Test message'));
    }

    public function test_refused_send_is_logged_without_the_message()
    {
        Http::fake(['*' => Http::response('Forbidden', 403)]);

        $logged = [];
        Log::listen(
            function ($event) use (&$logged) {
                $logged[] = $event;
            }
        );

        (new AlphaBd)->send('01700000000', 'Your code is 55555');

        $this->assertCount(1, $logged);
        $this->assertEquals('error', $logged[0]->level);
        $this->assertEquals(403, $logged[0]->context['status']);

        // This driver carries one-time codes; a log is the wrong place for them.
        $this->assertStringNotContainsString('55555', json_encode($logged[0]->context));
    }
}
