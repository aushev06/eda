<?php

namespace App\Services\Payments;

use App\Enums\OnlinePaymentStatus;

final readonly class GatewayPaymentState
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public OnlinePaymentStatus $status,
        public array $payload = [],
        public ?string $errorMessage = null,
    ) {}
}
