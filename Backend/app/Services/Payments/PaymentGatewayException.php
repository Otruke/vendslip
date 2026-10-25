<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Exceptions\DomainRuleException;

final class PaymentGatewayException extends DomainRuleException
{
    public static function notConfigured(string $gateway): self
    {
        return new self("{$gateway} is not set up yet.", 'gateway_not_configured', 503);
    }

    public static function failed(string $gateway, string $detail = ''): self
    {
        return new self(trim("{$gateway} could not start the payment. {$detail}"), 'gateway_error', 502);
    }
}
