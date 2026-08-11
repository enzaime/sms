<?php

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\BulkSmsDhaka;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BulkSmsDhakaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sms.drivers.bulksmsdhaka', [
            'caller_id' => '1234',
            'api_key' => 'test-api-key',
            'api_url' => 'https://bulksmsdhaka.net/api/sendtext',
        ]);
    }

    public function test_sends_sms_to_a_single_number(): void
    {
        Http::fake([
            'bulksmsdhaka.net/*' => Http::response(['code' => 1000], 200),
        ]);

        $sent = (new BulkSmsDhaka)->send('01912345678', 'Hello world');

        $this->assertSame(1, $sent);

        Http::assertSent(function (Request $request) {
            return str_starts_with($request->url(), 'https://bulksmsdhaka.net/api/sendtext')
                && $request['apikey'] === 'test-api-key'
                && $request['callerID'] === '1234'
                && $request['number'] === '01912345678'
                && $request['message'] === 'Hello world';
        });
    }

    public function test_sends_sms_to_each_number_in_a_list(): void
    {
        Http::fake([
            'bulksmsdhaka.net/*' => Http::response(['code' => 1000], 200),
        ]);

        $sent = (new BulkSmsDhaka)->send(['01912345678', '01812345678'], 'Hello world');

        $this->assertSame(2, $sent);

        Http::assertSentCount(2);
    }

    public function test_returns_zero_when_request_fails(): void
    {
        Http::fake([
            'bulksmsdhaka.net/*' => Http::response('Server Error', 500),
        ]);

        $sent = (new BulkSmsDhaka)->send('01912345678', 'Hello world');

        $this->assertSame(0, $sent);
    }

    /**
     * The gateway answers 200 and puts the real verdict in the body, so a
     * refusal — "ip Not whitelisted", "Balance Insufficient" — arrives as a
     * perfectly successful HTTP response. Reading the status alone counted
     * every one of those as a delivered message.
     */
    public function test_an_error_code_in_a_200_body_is_not_a_delivery(): void
    {
        Http::fake([
            'bulksmsdhaka.net/*' => Http::response(['code' => 1008], 200),
        ]);

        $sent = (new BulkSmsDhaka)->send('01912345678', 'Hello world');

        $this->assertSame(0, $sent, 'code 1008 is "IP not whitelisted", not a delivery');
    }

    public function test_a_queued_code_counts_as_a_delivery(): void
    {
        Http::fake([
            'bulksmsdhaka.net/*' => Http::response(['code' => 1001], 200),
        ]);

        $this->assertSame(1, (new BulkSmsDhaka)->send('01912345678', 'Hello world'));
    }

    /**
     * Not every response carries a code, and a body we cannot read must not be
     * turned into a failure — the HTTP status remains the fallback verdict.
     */
    public function test_a_body_without_a_code_falls_back_to_the_http_status(): void
    {
        Http::fake([
            'bulksmsdhaka.net/*' => Http::response('OK', 200),
        ]);

        $this->assertSame(1, (new BulkSmsDhaka)->send('01912345678', 'Hello world'));
    }

    public function test_a_refusal_is_logged_with_its_meaning_and_without_the_message(): void
    {
        Http::fake([
            'bulksmsdhaka.net/*' => Http::response(['code' => 1008], 200),
        ]);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        (new BulkSmsDhaka)->send('01912345678', 'Your code is 55555');

        $this->assertCount(1, $logged);
        $this->assertSame('error', $logged[0]->level);
        $this->assertSame(1008, $logged[0]->context['code']);
        $this->assertSame('IP not whitelisted', $logged[0]->context['meaning']);

        // This driver carries one-time codes; a log is the wrong place for them.
        $this->assertStringNotContainsString('55555', json_encode($logged[0]->context));
    }
}
