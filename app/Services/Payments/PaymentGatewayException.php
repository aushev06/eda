<?php

namespace App\Services\Payments;

use RuntimeException;

/**
 * A provider refused or failed a request. The message is safe to show to
 * the customer or the operator.
 */
class PaymentGatewayException extends RuntimeException {}
