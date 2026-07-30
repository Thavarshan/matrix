<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event fired when a promise is resolved.
 */
class PromiseResolved extends Event
{
    public const NAME = 'promise.resolved';

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
        $duration = $this->get('duration', 0.0);

        return is_float($duration) || is_int($duration) ? (float) $duration : 0.0;
    }

    public function getOperationType(): string
    {
        $type = $this->get('operation_type', 'promise');

        return is_string($type) ? $type : 'promise';
    }
}
