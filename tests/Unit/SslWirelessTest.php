<?php

namespace Enzaime\Sms\Tests\Unit;

use Enzaime\Sms\Drivers\SslWireless;
use Enzaime\Sms\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SslWirelessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sms.drivers.sslwireless', [
            'sid' => 'ENZAIME',
            'api_token' => 'test-api-token',
            'secret_key' => 'test-secret-key',
            'api_url' => 'https://smsplus.sslwireless.com/api/v3',
        ]);
    }

    /**
     * @param  array<int, array<string, string>>  $smsinfo
     * @return array<string, mixed>
     */
    protected function successBody(array $smsinfo): array
    {
        return [
            'status' => 'SUCCESS',
            'status_code' => 200,
            'error_message' => '',
            'smsinfo' => $smsinfo,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function acceptedFor(string $msisdn): array
    {
        return [
            'sms_status' => 'SUCCESS',
            'status_message' => 'Success',
            'msisdn' => $msisdn,
            'sms_type' => 'EN',
            'reference_id' => '5da2f0b5ba3a2248110',
        ];
    }

    public function test_sends_sms_to_a_single_number(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        $sent = (new SslWireless)->send('01912345678', 'Hello world');

        $this->assertSame(1, $sent);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://smsplus.sslwireless.com/api/v3/send-sms'
                && $request->method() === 'POST'
                && $request['api_token'] === 'test-api-token'
                && $request['sid'] === 'ENZAIME'
                // The gateway wants an MSISDN carrying the country code; a
                // shop types the number the local way.
                && $request['msisdn'] === '8801912345678'
                && $request['sms'] === 'Hello world'
                && is_string($request['csms_id'])
                && strlen($request['csms_id']) === SslWireless::CSMS_ID_LENGTH;
        });
    }

    public function test_a_list_goes_to_the_bulk_endpoint_in_one_request(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
                $this->acceptedFor('8801812345678'),
            ]), 200),
        ]);

        $sent = (new SslWireless)->send(['01912345678', '+8801812345678'], 'Hello world');

        $this->assertSame(2, $sent);

        Http::assertSentCount(1);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://smsplus.sslwireless.com/api/v3/send-sms/bulk'
                && $request['msisdn'] === ['8801912345678', '8801812345678']
                && is_string($request['batch_csms_id']);
        });
    }

    /**
     * The gateway refuses a batch outright above its recipient cap (code
     * 4030), so a longer list has to be split rather than handed over whole.
     */
    public function test_a_list_longer_than_the_cap_is_split_across_requests(): void
    {
        $numbers = [];
        for ($i = 0; $i < SslWireless::MAX_RECIPIENTS_PER_REQUEST + 5; $i++) {
            $numbers[] = '019'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);
        }

        Http::fake(function (Request $request) {
            $info = [];
            foreach ($request['msisdn'] as $msisdn) {
                $info[] = $this->acceptedFor($msisdn);
            }

            return Http::response($this->successBody($info), 200);
        });

        $sent = (new SslWireless)->send($numbers, 'Hello world');

        $this->assertSame(count($numbers), $sent);
        Http::assertSentCount(2);
    }

    public function test_a_repeated_number_is_only_sent_once(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        $sent = (new SslWireless)->send(['01912345678', '+8801912345678'], 'Hello world');

        $this->assertSame(1, $sent, 'a duplicate MSISDN is refused by the gateway, not delivered twice');

        Http::assertSent(fn (Request $request) => $request['msisdn'] === ['8801912345678']);
    }

    public function test_returns_zero_when_the_request_fails(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response('Server Error', 500),
        ]);

        $this->assertSame(0, (new SslWireless)->send('01912345678', 'Hello world'));
    }

    /**
     * The gateway answers 200 and puts the real verdict in the body, so a
     * refusal — "Invalid MSISDN", "IP not whitelisted" — arrives as a
     * perfectly successful HTTP response.
     */
    public function test_a_failed_status_in_a_200_body_is_not_a_delivery(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response([
                'status' => 'FAILED',
                'status_code' => 4003,
                'error_message' => 'IP Blacklisted',
                'smsinfo' => [],
            ], 200),
        ]);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $this->assertSame(0, (new SslWireless)->send('01912345678', 'Hello world'));

        $this->assertCount(1, $logged);
        $this->assertSame(4003, $logged[0]->context['code']);
        $this->assertSame('IP not whitelisted', $logged[0]->context['meaning']);
    }

    /**
     * A batch is accounted for per recipient: the top level can say SUCCESS
     * while individual numbers inside it are refused.
     */
    public function test_only_the_accepted_entries_of_a_batch_are_counted(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
                [
                    'sms_status' => 'INVALID',
                    'status_message' => 'Invalid MSISDN',
                    'msisdn' => '8801812345678',
                    'sms_type' => 'EN',
                ],
            ]), 200),
        ]);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $sent = (new SslWireless)->send(['01912345678', '01812345678'], 'Hello world');

        $this->assertSame(1, $sent, 'one of the two recipients was refused');

        $this->assertCount(1, $logged, 'only the refused recipient is logged');
        $this->assertSame('8801812345678', $logged[0]->context['number']);
        $this->assertSame('INVALID', $logged[0]->context['sms_status']);
    }

    /**
     * Not every response is readable, and a body we cannot parse must not be
     * turned into a failure — the HTTP status remains the fallback verdict.
     */
    public function test_a_body_without_a_verdict_falls_back_to_the_http_status(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response('OK', 200),
        ]);

        $this->assertSame(1, (new SslWireless)->send('01912345678', 'Hello world'));
    }

    /**
     * This gateway echoes the accepted message back in `sms_body`, so the
     * response itself carries the one-time code straight into the log.
     */
    public function test_a_refusal_is_logged_without_the_message_or_the_token(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response(
                '{"status":"FAILED","status_code":4032,"error_message":"Invalid SMS",'
                .'"api_token":"REAL-SECRET-TOKEN","sms_body":"Your code is 55555"}',
                503
            ),
        ]);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $this->assertSame(0, (new SslWireless)->send('01912345678', 'Your code is 55555'));

        $context = json_encode($logged[0]->context);

        $this->assertStringNotContainsString('REAL-SECRET-TOKEN', $context, 'the API token must never reach the log');
        $this->assertStringNotContainsString('55555', $context, 'the one-time code must never reach the log');
        $this->assertStringContainsString('Invalid SMS', $context, 'what went wrong still has to survive');
        $this->assertStringContainsString('[redacted]', $context);
    }

    public function test_a_transport_failure_logs_neither_the_token_nor_the_code(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out for https://smsplus.sslwireless.com/api/v3/send-sms'
            .' with body {"api_token":"REAL-SECRET-TOKEN","sms":"Verification code: 55555."}'
        ));

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $this->assertSame(0, (new SslWireless)->send('01912345678', 'Verification code: 55555.'));

        $context = json_encode($logged[0]->context);

        $this->assertStringNotContainsString('REAL-SECRET-TOKEN', $context);
        $this->assertStringNotContainsString('55555', $context);
        $this->assertStringContainsString('Operation timed out', $context);
    }

    /**
     * A response body is truncated before it reaches the log, and the cut can
     * land inside the very value that must not be logged. A pattern needing
     * the closing quote would find none and leave the partial code in place.
     */
    public function test_a_body_truncated_mid_code_is_still_redacted(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response(
                // Sized so the 500-character cut lands just past the code,
                // leaving the value open: `"sms_body":"Your code is 55555`.
                '{"status":"FAILED","status_code":4032,"error_message":"Invalid SMS","detail":"'
                .str_repeat('x', 390).'","sms_body":"Your code is 55555"}',
                503
            ),
        ]);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $this->assertSame(0, (new SslWireless)->send('01912345678', 'Your code is 55555'));

        $context = json_encode($logged[0]->context);

        $this->assertStringContainsString('sms_body', $context, 'the cut has to fall inside the value');
        $this->assertStringNotContainsString('55555', $context, 'a truncated code must not survive');
        $this->assertStringContainsString('Invalid SMS', $context);
    }

    /**
     * The gateway may echo the request back encoded inside a string of its
     * own, which spells the keys `\"api_token\"` rather than `"api_token"`.
     */
    public function test_a_re_encoded_body_is_redacted(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response(
                '{"status":"FAILED","error_message":"Invalid SMS","request":'
                .'"{\\"api_token\\":\\"REAL-SECRET-TOKEN\\",\\"sms\\":\\"Your code is 55555\\"}"}',
                503
            ),
        ]);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $this->assertSame(0, (new SslWireless)->send('01912345678', 'Your code is 55555'));

        $context = json_encode($logged[0]->context);

        $this->assertStringNotContainsString('REAL-SECRET-TOKEN', $context);
        $this->assertStringNotContainsString('55555', $context);
        $this->assertStringContainsString('Invalid SMS', $context, 'what went wrong still has to survive');
    }

    /**
     * A repeated CSMS ID is refused by the gateway (code 4023), so the id has
     * to be fresh even when the same text goes to the same number twice.
     */
    public function test_each_request_carries_a_fresh_message_id(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        $driver = new SslWireless;
        $driver->send('01912345678', 'Hello world');
        $driver->send('01912345678', 'Hello world');

        $ids = [];
        Http::assertSent(function (Request $request) use (&$ids) {
            $ids[] = $request['csms_id'];

            return true;
        });

        $this->assertCount(2, array_unique($ids));
    }

    public function test_an_unusable_number_is_never_sent(): void
    {
        Http::fake();

        $this->assertSame(0, (new SslWireless)->send('', 'Hello world'));

        Http::assertNothingSent();
    }

    public function test_the_driver_is_resolvable_by_name(): void
    {
        $this->assertInstanceOf(
            SslWireless::class,
            (new \Enzaime\Sms\SmsService)->getDriver('ssl_wireless')
        );
    }

    /**
     * Undo the documented envelope, following the specification rather than
     * the driver: base64 off, 16 raw IV bytes off the front, and what is left
     * is the base64 ciphertext under a key that is the first 32 characters of
     * the SHA-256 hex digest of the Secret Key.
     */
    protected function decryptOtpText(string $encrypted, string $secretKey = 'test-secret-key'): string
    {
        $envelope = base64_decode($encrypted, true);

        $this->assertNotFalse($envelope, 'the OTP body must be a base64 envelope');

        $iv = substr($envelope, 0, 16);
        $cipher = substr($envelope, 16);

        return (string) openssl_decrypt(
            $cipher,
            'aes-256-cbc',
            substr(hash('sha256', $secretKey), 0, 32),
            0,
            $iv
        );
    }

    /**
     * Rebuild the signature straight from the documented steps: the four
     * signed parameters in alphabetical order, URL-encoded, HMAC-SHA256 under
     * the raw Secret Key, lowercase hex.
     */
    protected function expectedSignature(Request $request, string $secretKey = 'test-secret-key'): string
    {
        $canonical = sprintf(
            'csms_id=%s&msisdn=%s&sid=%s&sms=%s',
            rawurlencode($request['csms_id']),
            rawurlencode($request['msisdn']),
            rawurlencode($request['sid']),
            rawurlencode($request['sms'])
        );

        return hash_hmac('sha256', $canonical, $secretKey);
    }

    public function test_an_otp_goes_to_the_secure_route_with_an_encrypted_body(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        $sent = (new SslWireless)->sendOtp('01912345678', 'Your code is 55555');

        $this->assertSame(1, $sent);

        Http::assertSent(function (Request $request) {
            $this->assertSame('https://smsplus.sslwireless.com/api/v3/secure/otp-sms', $request->url());
            $this->assertSame('test-api-token', $request['api_token']);
            $this->assertSame('8801912345678', $request['msisdn']);

            // The whole point of this route: the code is not on the wire.
            $this->assertStringNotContainsString('55555', $request['sms']);
            $this->assertSame('Your code is 55555', $this->decryptOtpText($request['sms']));

            return true;
        });
    }

    public function test_the_otp_request_is_signed_as_documented(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        (new SslWireless)->sendOtp('01912345678', 'Your code is 55555');

        Http::assertSent(function (Request $request) {
            $signature = $request->header('X-Signature')[0] ?? '';

            $this->assertSame($this->expectedSignature($request), $signature);
            $this->assertSame(strtolower($signature), $signature, 'the digest is a lowercase hex string');
            $this->assertSame(64, strlen($signature));

            return true;
        });
    }

    /**
     * A fresh IV per message is the only thing stopping the same code to the
     * same number from producing identical ciphertext twice — which would
     * make the codes comparable to anyone watching the traffic.
     */
    public function test_the_same_code_encrypts_differently_each_time(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        $driver = new SslWireless;
        $driver->sendOtp('01912345678', 'Your code is 55555');
        $driver->sendOtp('01912345678', 'Your code is 55555');

        $bodies = [];
        Http::assertSent(function (Request $request) use (&$bodies) {
            $bodies[] = $request['sms'];

            return true;
        });

        $this->assertCount(2, array_unique($bodies), 'each message needs its own IV');
        $this->assertSame('Your code is 55555', $this->decryptOtpText($bodies[0]));
        $this->assertSame('Your code is 55555', $this->decryptOtpText($bodies[1]));
    }

    /**
     * The secure route takes one recipient at a time.
     */
    public function test_an_otp_to_a_list_is_one_request_per_recipient(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        $sent = (new SslWireless)->sendOtp(['01912345678', '01812345678'], 'Your code is 55555');

        $this->assertSame(2, $sent);
        Http::assertSentCount(2);
    }

    /**
     * Without the Secret Key the body cannot be encrypted and the request
     * cannot be signed. Quietly falling back to the plain route would put the
     * code on the wire in clear text.
     */
    public function test_an_otp_is_refused_when_no_secret_key_is_configured(): void
    {
        config()->set('sms.drivers.sslwireless.secret_key', null);

        Http::fake();

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $this->assertSame(0, (new SslWireless)->sendOtp('01912345678', 'Your code is 55555'));

        Http::assertNothingSent();
        $this->assertStringContainsString('secret key', $logged[0]->context['error_message']);
    }

    public function test_a_signature_mismatch_is_reported_with_its_meaning(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response([
                'status' => 'FAILED',
                'error_message' => 'Client signature mismatch',
                'status_code' => 4035,
                'smsinfo' => [],
            ], 200),
        ]);

        $logged = [];
        Log::listen(function ($event) use (&$logged) {
            $logged[] = $event;
        });

        $this->assertSame(0, (new SslWireless)->sendOtp('01912345678', 'Your code is 55555'));

        $this->assertSame(4035, $logged[0]->context['code']);
        $this->assertSame('client signature mismatch', $logged[0]->context['meaning']);
        $this->assertStringNotContainsString('55555', json_encode($logged[0]->context));
    }

    /**
     * The plain send path must never be quietly upgraded to the secure route,
     * nor the reverse: they are different endpoints with different limits.
     */
    public function test_a_plain_send_does_not_use_the_secure_route(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        (new SslWireless)->send('01912345678', 'Hello world');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://smsplus.sslwireless.com/api/v3/send-sms'
                && $request->header('X-Signature') === [];
        });
    }

    public function test_the_driver_offers_the_otp_contract(): void
    {
        $this->assertInstanceOf(\Enzaime\Sms\Contracts\OtpContract::class, new SslWireless);
    }

    /**
     * `SmsService` forwards an unknown method to the resolved driver, which is
     * how a caller reaches the secure route through the facade.
     */
    public function test_the_otp_route_is_reachable_through_the_service(): void
    {
        Http::fake([
            'smsplus.sslwireless.com/*' => Http::response($this->successBody([
                $this->acceptedFor('8801912345678'),
            ]), 200),
        ]);

        $sent = (new \Enzaime\Sms\SmsService)->driver('ssl_wireless')->sendOtp('01912345678', 'Your code is 55555');

        $this->assertSame(1, $sent);
    }
}
