<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Abstract base class for all Matrix events.
 */
abstract class Event implements EventInterface
{
    /**
     * @var float The timestamp when the event was created
     */
    protected float $timestamp;

    /**
     * @var array<string, mixed> Event data
     */
    protected array $data;

    /**
     * Create a new event.
     *
     * @param  array<string, mixed>  $data  Event data
     */
    public function __construct(array $data = [])
    {
        $this->timestamp = microtime(true);
        $this->data = $data;
    }

    /**
     * Get the event timestamp.
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    /**
     * Get event data.
     *
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get a specific data value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Set a data value.
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}
