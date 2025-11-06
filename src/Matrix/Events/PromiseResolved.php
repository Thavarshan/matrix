<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event fired when a promise is resolved.
 */
class PromiseResolved extends Event
{
    /**
     * Create a new PromiseResolved event.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $promiseId, mixed $value, float $duration, array $context = [])
    {
        parent::__construct(array_merge([
            'promise_id' => $promiseId,
            'value'      => $value,
            'duration'   => $duration,
        ], $context));
    }

    /**
     * Get the event name.
     */
    public function getName(): string
    {
        return 'promise.resolved';
    }

    /**
     * Get the promise ID.
     */
    public function getPromiseId(): string
    {
        return $this->get('promise_id');
    }

    /**
     * Get the resolved value.
     */
    public function getValue(): mixed
    {
        return $this->get('value');
    }

    /**
     * Get the duration in seconds.
     */
    public function getDuration(): float
    {
        return $this->get('duration', 0.0);
    }
}
