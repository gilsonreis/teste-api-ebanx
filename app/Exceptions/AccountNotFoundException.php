<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class AccountNotFoundException extends Exception
{
    public function __construct(string $accountId)
    {
        parent::__construct(sprintf('Account with ID "%s" not found.', $accountId));
    }
}
