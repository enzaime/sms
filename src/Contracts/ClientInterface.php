<?php

namespace Enzaime\Sms\Contracts;

/**
 * Interface ClientInterface
 *
 * For drivers that expose an underlying client instance.
 */
interface ClientInterface
{
    /**
     * Get the underlying client instance.
     */
    public function getClient(): mixed;
}
