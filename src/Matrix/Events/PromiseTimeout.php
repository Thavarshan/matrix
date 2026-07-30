<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event fired when a promise timeout occurs.
 */
class PromiseTimeout extends Event
{
    public const NAME = 'promise.timeout';

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
        return self::NAME;
    }

    /**
     * Get the promise ID.
     */
    public function getPromiseId(): string
    {
        $id = $this->get('promise_id');

        return is_string($id) ? $id : '';
    }

    /**
     * Get the timeout duration in seconds.
     */
    public function getTimeoutDuration(): float
    {
        $duration = $this->get('timeout_duration', 0.0);

        return is_float($duration) || is_int($duration) ? (float) $duration : 0.0;
    }

    /**
     * Get the timeout message.
     */
    public function getMessage(): string
    {
        $message = $this->get('message', 'Operation timed out');

        return is_string($message) ? $message : 'Operation timed out';
    }

    public function getOperationType(): string
    {
        $type = $this->get('operation_type', 'timeout');

        return is_string($type) ? $type : 'timeout';
    }
}
