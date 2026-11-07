<?php

namespace App\Services\Payments;

/**
 * Gateway-agnostic description of what the customer is paying for.
 */
final readonly class PaymentIntent
{
    public function __construct(
        public string $reference,
        public int $amountInMinorUnits,
        public string $description,
        public string $returnUrl,
        public string $notificationUrl,
        public ?string $customerPhone = null,
    ) {}
}
