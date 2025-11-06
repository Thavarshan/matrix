<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event fired when a promise timeout occurs.
 */
class PromiseTimeout extends Event
{
    /**
     * Create a new PromiseTimeout event.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $promiseId, float $timeoutDuration, string $message = 'Operation timed out', array $context = [])
    {
        parent::__construct(array_merge([
            'promise_id'       => $promiseId,
            'timeout_duration' => $timeoutDuration,
            'message'          => $message,
        ], $context));
    }

    /**
     * Get the event name.
     */
    public function getName(): string
    {
        return 'promise.timeout';
    }

    /**
     * Get the promise ID.
     */
    public function getPromiseId(): string
    {
        return $this->get('promise_id');
    }

    /**
     * Get the timeout duration in seconds.
     */
    public function getTimeoutDuration(): float
    {
        return $this->get('timeout_duration', 0.0);
    }

    /**
     * Get the timeout message.
     */
    public function getMessage(): string
    {
        return $this->get('message', 'Operation timed out');
    }
}
