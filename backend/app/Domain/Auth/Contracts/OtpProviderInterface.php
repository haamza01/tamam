<?php

namespace App\Domain\Auth\Contracts;

interface OtpProviderInterface
{
    /**
     * Deliver a one-time password to the given phone number.
     * Implementations must never expose the code through HTTP responses.
     */
    public function send(string $phone, string $code): void;
}
