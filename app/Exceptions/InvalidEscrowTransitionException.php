<?php

namespace App\Exceptions;

use App\Enums\EscrowStatus;
use DomainException;

class InvalidEscrowTransitionException extends DomainException
{
    public function __construct(?EscrowStatus $from, EscrowStatus $to, string $message = '')
    {
        $fromName = $from ? $from->value : 'NULL';
        $fullMessage = $message ?: "Transisi status escrow tidak sah dari [{$fromName}] ke [{$to->value}].";

        parent::__construct($fullMessage);
    }
}
