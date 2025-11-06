<?php

declare(strict_types=1);

namespace Matrix\Events;

/**
 * Event dispatcher for managing listeners and firing events.
 */
class EventDispatcher
{
    /**
     * @var array<string, array<callable>> Registered event listeners
     */
    private array $listeners = [];

    /**
     * @var bool Whether events are enabled
     */
    private bool $enabled = true;

    /**
     * Register an event listener.
     */
    public function listen(string $eventName, callable $listener): void
    {
        if (! isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        $this->listeners[$eventName][] = $listener;
    }

    /**
     * Remove an event listener.
     */
    public function removeListener(string $eventName, callable $listener): void
    {
        if (! isset($this->listeners[$eventName])) {
            return;
        }

        $this->listeners[$eventName] = array_filter(
            $this->listeners[$eventName],
            fn ($l) => $l !== $listener
        );
    }

    /**
     * Remove all listeners for an event.
     */
    public function removeAllListeners(string $eventName): void
    {
        unset($this->listeners[$eventName]);
    }

    /**
     * Get all listeners for an event.
     *
     * @return array<callable>
     */
    public function getListeners(string $eventName): array
    {
        return $this->listeners[$eventName] ?? [];
    }

    /**
     * Fire an event.
     */
    public function dispatch(EventInterface $event): void
    {
        if (! $this->enabled) {
            return;
        }

        $listeners = $this->getListeners($event->getName());

        foreach ($listeners as $listener) {
            try {
                $listener($event);
            } catch (\Throwable $e) {
                // Silently ignore listener exceptions to prevent breaking the main flow
                // In production, you might want to log these errors
            }
        }
    }

    /**
     * Fire an event by name and data.
     *
     * @param  array<string, mixed>  $data
     */
    public function fire(string $eventName, array $data = []): void
    {
        $event = new class($eventName, $data) extends Event
        {
            private string $name;

            public function __construct(string $name, array $data = [])
            {
                parent::__construct($data);
                $this->name = $name;
            }

            public function getName(): string
            {
                return $this->name;
            }
        };

        $this->dispatch($event);
    }

    /**
     * Enable or disable event dispatching.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if event dispatching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Clear all listeners.
     */
    public function clear(): void
    {
        $this->listeners = [];
    }
}
