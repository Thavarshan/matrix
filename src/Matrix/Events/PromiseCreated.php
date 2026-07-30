<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event fired when a promise is created.
 */
class PromiseCreated extends Event
{
    public const NAME = 'promise.created';

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
     * Get the promise type.
     */
    public function getType(): string
    {
        $type = $this->get('type', 'promise');

        return is_string($type) ? $type : 'promise';
    }

    public function getOperationType(): string
    {
        return $this->getType();
    }
}
