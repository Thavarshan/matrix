<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event fired when a promise is created.
 */
class PromiseCreated extends Event
{
    /**
     * Create a new PromiseCreated event.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $promiseId, string $type = 'promise', array $context = [])
    {
        parent::__construct(array_merge([
            'promise_id' => $promiseId,
            'type'       => $type,
        ], $context));
    }

    /**
     * Get the event name.
     */
    public function getName(): string
    {
        return 'promise.created';
    }

    /**
     * Get the promise ID.
     */
    public function getPromiseId(): string
    {
        return $this->get('promise_id');
    }

    /**
     * Get the promise type.
     */
    public function getType(): string
    {
        return $this->get('type', 'promise');
    }
}
