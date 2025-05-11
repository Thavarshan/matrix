<?php

declare(strict_types=1);

namespace Matrix\Exceptions;

/**
 * Exception thrown when an async operation times out.
 */
class TimeoutException extends AsyncException
{
    /**
     * @var float The timeout duration that was exceeded
     */
    private float $duration;

    /**
     * Create a new timeout exception.
     *
     * @param  float  $duration  The timeout duration in seconds
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  \Throwable|null  $previous  The previous throwable
     */
    public function __construct(
        float $duration,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->duration = $duration;

        $message = $message ?: "Operation timed out after {$duration} seconds";

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the timeout duration.
     *
     * @return float The timeout duration in seconds
     */
    public function getDuration(): float
    {
        return $this->duration;
    }
}
