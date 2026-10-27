<?php

namespace App\Services\Payments;

final readonly class CreatedPayment
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $externalId,
        public string $confirmationUrl,
        public array $payload = [],
    ) {}
}
