<?php

declare(strict_types=1);

namespace Matrix\Exceptions;

use Exception;

class TimeoutException extends Exception
{
    /**
     * Create a new TimeoutException instance.
     *
     *
     * @return void
     */
    public function __construct(
        string $message = 'Operation timed out',
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
