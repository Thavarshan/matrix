<?php

declare(strict_types=1);

namespace Matrix\Support;

use Matrix\Exceptions\CancellationException;
use React\EventLoop\TimerInterface;
use React\Promise;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/** Utility for limiting calls in a sliding time window. */
final class RateLimiter
{
    private int $maxCalls;

    private float $period;

    /** @var list<float> Monotonic timestamps in seconds. */
    private array $timeWindow = [];

    /** @var array<int, RateLimiterEntry> */
    private array $queue = [];

    private int $queueHead = 0;

    private ?TimerInterface $wakeTimer = null;

    private bool $tickScheduled = false;

    public function __construct(int $maxCalls, float $period)
    {
        if ($maxCalls < 1 || ! is_finite($period) || $period <= 0) {
            throw new \InvalidArgumentException('Rate limit calls and finite period must be positive.');
        }

        $this->maxCalls = $maxCalls;
        $this->period = $period;
    }

    public static function create(int $maxCalls, float $period): self
    {
        return new self($maxCalls, $period);
    }

    /**
     * @return callable(mixed...): PromiseInterface<mixed>
     */
    public function limit(callable $fn): callable
    {
        return function (...$args) use ($fn): PromiseInterface {
            $entry = new RateLimiterEntry($args, $fn);
            $deferred = new Deferred(function () use ($entry): void {
                $this->cancel($entry);
            });
            $entry->deferred = $deferred;
            $this->enqueue($entry);
            $this->scheduleQueue();

            return Lifecycle::track($deferred->promise(), 'rate_limit');
        };
    }

    private function cancel(RateLimiterEntry $entry): void
    {
        if ($entry->isCancelled() || $entry->settled) {
            return;
        }

        $entry->markCancelled();
        $entry->args = [];
        $this->removeQueued($entry);

        if ($entry->active !== null) {
            $entry->active->cancel();
            $entry->active = null;
        }

        if ($this->queueIsEmpty()) {
            $this->cancelWakeTimer();
        } else {
            $this->scheduleQueue();
        }

        throw new CancellationException;
    }

    private function removeQueued(RateLimiterEntry $cancelled): void
    {
        if ($cancelled->started || $this->queue === []) {
            return;
        }

        $remaining = [];

        foreach ($this->queue as $entry) {
            if ($entry !== $cancelled && ! $entry->isCancelled()) {
                $remaining[] = $entry;
            }
        }
        $this->queue = $remaining;
        $this->queueHead = 0;
    }

    private function scheduleQueue(): void
    {
        if ($this->tickScheduled || $this->queueIsEmpty()) {
            return;
        }
        $this->tickScheduled = true;
        LoopManager::nextTick(function (): void {
            $this->tickScheduled = false;
            $this->processQueueStep();
        });
    }

    private function processQueueStep(): void
    {
        $now = hrtime(true) * 1e-9;
        $this->timeWindow = array_values(array_filter(
            $this->timeWindow,
            fn (float $time): bool => $now - $time < $this->period
        ));

        while (! $this->queueIsEmpty() && count($this->timeWindow) < $this->maxCalls) {
            $entry = $this->dequeue();

            if ($entry->isCancelled()) {
                continue;
            }

            $entry->started = true;
            $this->timeWindow[] = hrtime(true) * 1e-9;

            try {
                $promise = Promise\resolve(($entry->fn)(...$entry->args));
            } catch (\Throwable $error) {
                $entry->settled = true;
                $entry->deferred?->reject($error);

                continue;
            }

            if ($entry->isCancelled()) {
                $promise->cancel();

                continue;
            }
            $entry->active = $promise;
            $promise->then(
                static function (mixed $value) use ($entry): void {
                    $entry->active = null;
                    $entry->settled = true;
                    $entry->deferred?->resolve($value);
                    $entry->args = [];
                },
                static function (\Throwable $error) use ($entry): void {
                    $entry->active = null;
                    $entry->settled = true;
                    $entry->deferred?->reject($error);
                    $entry->args = [];
                }
            );
        }

        if ($this->queueIsEmpty()) {
            $this->cancelWakeTimer();

            return;
        }
        $this->scheduleWakeTimer();
    }

    private function scheduleWakeTimer(): void
    {
        $now = hrtime(true) * 1e-9;
        $this->timeWindow = array_values(array_filter(
            $this->timeWindow,
            fn (float $time): bool => $now - $time < $this->period
        ));

        if ($this->timeWindow === []) {
            $this->cancelWakeTimer();
            $this->scheduleQueue();

            return;
        }

        if ($this->wakeTimer !== null) {
            return;
        }

        $wait = max(0.000001, ($this->timeWindow[0] + $this->period) - $now);
        $this->wakeTimer = LoopManager::delay($wait, function (): void {
            $this->wakeTimer = null;
            $this->processQueueStep();
        });
    }

    private function cancelWakeTimer(): void
    {
        if ($this->wakeTimer !== null) {
            LoopManager::cancelTimer($this->wakeTimer);
            $this->wakeTimer = null;
        }
    }

    private function enqueue(RateLimiterEntry $entry): void
    {
        if ($this->queue === []) {
            // Reset PHP's next numeric index as well as the head after draining.
            $this->queue = [];
            $this->queueHead = 0;
        }
        $this->queue[] = $entry;
    }

    private function dequeue(): RateLimiterEntry
    {
        $entry = $this->queue[$this->queueHead];
        unset($this->queue[$this->queueHead]);
        $this->queueHead++;

        if ($this->queueHead > 64 && $this->queueHead > count($this->queue)) {
            $this->queue = array_values($this->queue);
            $this->queueHead = 0;
        }

        return $entry;
    }

    private function queueIsEmpty(): bool
    {
        return ! isset($this->queue[$this->queueHead]);
    }
}

/** @internal Per-invocation state; never shared between completion closures. */
final class RateLimiterEntry
{
    /** @var array<int|string, mixed> */
    public array $args;

    /** @var callable */
    public $fn;

    /** @var Deferred<mixed>|null */
    public ?Deferred $deferred = null;

    /** @var PromiseInterface<mixed>|null */
    public ?PromiseInterface $active = null;

    public bool $settled = false;

    public bool $started = false;

    private OperationState $cancellation;

    /**
     * @param  array<int|string, mixed>  $args
     */
    public function __construct(array $args, callable $fn)
    {
        $this->cancellation = new OperationState;
        $this->args = $args;
        $this->fn = $fn;
    }

    public function markCancelled(): void
    {
        $this->cancellation->settle();
    }

    public function isCancelled(): bool
    {
        return $this->cancellation->isSettled();
    }
}
