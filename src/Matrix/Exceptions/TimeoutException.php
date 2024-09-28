<?php

namespace Matrix\Exceptions;

use Exception;

class TimeoutException extends Exception
{
    /**
     * Create a new TimeoutException instance.
     *
     * @param string         $message
     * @param int            $code
     * @param Exception|null $previous
     *
     * @return void
     */
    public function __construct(
        string $message = 'Operation timed out',
        int $code = 0,
        Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
