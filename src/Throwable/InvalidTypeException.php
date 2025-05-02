<?php

declare(strict_types=1);

namespace App\Throwable;

use RuntimeException;

final class InvalidTypeException extends RuntimeException
{
    public function __construct(mixed $invalidValue, string $expectedType)
    {
        $wrongType = get_debug_type($invalidValue);
        parent::__construct(
            "Value was expected to be '{$expectedType}' but is '{$wrongType}'."
        );
    }
}
