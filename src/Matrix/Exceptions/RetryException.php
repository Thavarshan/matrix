<?php

declare(strict_types=1);

namespace Matrix\Exceptions;

/**
 * Exception thrown when an async operation fails after multiple retries.
 */
class RetryException extends AsyncException
{
    /**
     * @var int The number of attempts that were made
     */
    private int $attempts;

    /**
     * @var array<\Throwable> Array of exceptions from each retry attempt
     */
    private array $failures;

    /**
     * Create a new retry exception.
     *
     * @param  int  $attempts  The number of attempts that were made
     * @param  array<\Throwable>  $failures  Array of exceptions from each attempt
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  \Throwable|null  $previous  The previous throwable
     */
    public function __construct(
        int $attempts,
        array $failures,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->attempts = $attempts;
        $this->failures = $failures;

        $message = $message ?: "Operation failed after {$attempts} attempts";

        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the number of attempts.
     *
     * @return int The number of attempts
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Get the array of exceptions from each attempt.
     *
     * @return array<\Throwable> The exceptions
     */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
