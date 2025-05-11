<?php

declare(strict_types=1);

namespace Matrix\Promise;

use React\Promise\PromiseInterface;

/**
 * A promise that can be cancelled.
 */
class CancellablePromise
{
    /**
     * @var PromiseInterface The wrapped promise
     */
    private PromiseInterface $promise;

    /**
     * @var callable The cancellation function
     */
    private $cancelFn;

    /**
     * @var bool Whether the promise has been cancelled
     */
    private bool $cancelled = false;

    /**
     * Create a new cancellable promise.
     *
     * @param  PromiseInterface  $promise  The promise to wrap
     * @param  callable  $cancelFn  Function to call when cancelled
     */
    public function __construct(PromiseInterface $promise, callable $cancelFn)
    {
        $this->promise = $promise;
        $this->cancelFn = $cancelFn;
    }

    /**
     * Get the wrapped promise.
     *
     * @return PromiseInterface The promise
     */
    public function getPromise(): PromiseInterface
    {
        return $this->promise;
    }

    /**
     * Cancel the promise.
     */
    public function cancel(): void
    {
        if (! $this->cancelled) {
            $this->cancelled = true;
            ($this->cancelFn)();
        }
    }

    /**
     * Check if the promise has been cancelled.
     *
     * @return bool True if cancelled, false otherwise
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
}
