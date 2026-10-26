<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class PayMongoException extends RuntimeException
{
    public static function fromResponse(Response $response): self
    {
        $message = $response->json('errors.0.detail')
            ?? $response->json('errors.0.title')
            ?? $response->body();

        return new self(
            'PayMongo request failed: '.($message ?: 'Unknown error'),
            $response->status()
        );
    }

    public static function missingApiKey(): self
    {
        return new self('PAYMONGO_SECRET_KEY is not configured.');
    }
}
