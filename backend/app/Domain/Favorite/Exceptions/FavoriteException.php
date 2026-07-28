<?php

namespace App\Domain\Favorite\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class FavoriteException extends \RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        private readonly string $errorCode,
        string $message,
        private readonly int $status = Response::HTTP_BAD_REQUEST,
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
        return $this->errors;
    }
}
