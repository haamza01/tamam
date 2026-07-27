<?php

namespace App\Domain\Profile\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class ProfileException extends Exception
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status = Response::HTTP_UNPROCESSABLE_ENTITY,
        private readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        if ($this->errors !== []) {
            return $this->errors;
        }

        return ['profile' => [$this->errorCode]];
    }
}
