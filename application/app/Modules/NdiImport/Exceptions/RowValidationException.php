<?php

namespace App\Modules\NdiImport\Exceptions;

use RuntimeException;

class RowValidationException extends RuntimeException
{
    public function __construct(
        public readonly string $fieldName,
        string $reason,
    ) {
        parent::__construct($reason);
    }
}
