<?php

declare(strict_types=1);

namespace Matrix\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception class for Matrix async operations.
 */
class AsyncException extends RuntimeException
{
    /**
     * Create a new async exception.
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  \Throwable|null  $previous  The previous throwable
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
