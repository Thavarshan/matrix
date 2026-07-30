<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Base interface for all Matrix events.
 */
interface EventInterface
{
    /**
     * Get the event name.
     */
    public function getName(): string;

    public function getOperationType(): string;

    /**
     * Get the event timestamp.
     */
    public function getTimestamp(): float;

    /**
     * Get event data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array;
}
