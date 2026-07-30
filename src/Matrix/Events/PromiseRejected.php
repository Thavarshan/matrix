<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event fired when a promise is rejected.
 */
class PromiseRejected extends Event
{
    public const NAME = 'promise.rejected';

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
     * Get the rejection reason.
     */
    public function getReason(): \Throwable
    {
        $reason = $this->get('reason');

        if (! $reason instanceof \Throwable) {
            throw new \UnexpectedValueException('Promise rejection reason is not a Throwable.');
        }

        return $reason;
    }

    /**
     * Get the duration in seconds.
     */
    public function getDuration(): float
    {
        $duration = $this->get('duration', 0.0);

        return is_float($duration) || is_int($duration) ? (float) $duration : 0.0;
    }

    /**
     * Get the error message.
     */
    public function getErrorMessage(): string
    {
        $message = $this->get('error_message', '');

        return is_string($message) ? $message : '';
    }

    /**
     * Get the error class name.
     */
    public function getErrorClass(): string
    {
        $class = $this->get('error_class', '');

        return is_string($class) ? $class : '';
    }

    public function getOperationType(): string
    {
        $type = $this->get('operation_type', 'promise');

        return is_string($type) ? $type : 'promise';
    }
}
