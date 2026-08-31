<?php

namespace Enzaime\Sms\Contracts;

/**
 * Interface OtpContract
 *
 * For gateways that offer a hardened route for one-time codes, separate from
 * ordinary traffic. A driver implements this only when the gateway has such a
 * route; callers should check for it rather than assume it.
 */
interface OtpContract
{
    /**
     * Send a one-time code over the gateway's secure route.
     *
     * Returns the number of recipients the gateway accepted.
     */
    public function sendOtp(string|array $numberOrList, string $text): int;
}
