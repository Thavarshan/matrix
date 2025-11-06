<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event fired when a promise is rejected.
 */
class PromiseRejected extends Event
{
    /**
     * Create a new PromiseRejected event.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $promiseId, \Throwable $reason, float $duration, array $context = [])
    {
        parent::__construct(array_merge([
            'promise_id'    => $promiseId,
            'reason'        => $reason,
            'duration'      => $duration,
            'error_message' => $reason->getMessage(),
            'error_class'   => get_class($reason),
        ], $context));
    }

    /**
     * Get the event name.
     */
    public function getName(): string
    {
        return 'promise.rejected';
    }

    /**
     * Get the promise ID.
     */
    public function getPromiseId(): string
    {
        return $this->get('promise_id');
    }

    /**
     * Get the rejection reason.
     */
    public function getReason(): \Throwable
    {
        return $this->get('reason');
    }

    /**
     * Get the duration in seconds.
     */
    public function getDuration(): float
    {
        return $this->get('duration', 0.0);
    }

    /**
     * Get the error message.
     */
    public function getErrorMessage(): string
    {
        return $this->get('error_message', '');
    }

    /**
     * Get the error class name.
     */
    public function getErrorClass(): string
    {
        return $this->get('error_class', '');
    }
}
