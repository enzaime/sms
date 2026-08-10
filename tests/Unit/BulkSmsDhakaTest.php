<?php

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\BulkSmsDhaka;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

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
}
