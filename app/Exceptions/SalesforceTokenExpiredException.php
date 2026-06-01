<?php

namespace App\Exceptions;

use Throwable;

class SalesforceTokenExpiredException extends \RuntimeException
{
    public function __construct(
        string $message = 'Salesforce OAuth token expired or revoked. Manual reconnection required.',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
