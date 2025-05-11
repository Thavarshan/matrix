<?php

declare(strict_types=1);

namespace Matrix\Support;

use Matrix\Async;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * Utility class for rate limiting async operations.
 */
class RateLimiter
{
    /**
     * @var int Maximum number of calls per time period
     */
    private int $maxCalls;

    /**
     * @var float Time period in seconds
     */
    private float $period;

    /**
     * @var array<float> Timestamps of recent calls
     */
    private array $timeWindow = [];

    /**
     * @var array<array{0: array<mixed>, 1: Deferred, 2: callable}> Queue of pending calls
     */
    private array $queue = [];

    /**
     * @var bool Whether the queue is currently being processed
     */
    private bool $processing = false;

    /**
     * Create a new rate limiter.
     *
     * @param  int  $maxCalls  Maximum number of calls per time period
     * @param  float  $period  Time period in seconds
     */
    public function __construct(int $maxCalls, float $period)
    {
        $this->maxCalls = $maxCalls;
        $this->period = $period;
    }

    /**
     * Create a rate limiter with the specified parameters.
     *
     * @param  int  $maxCalls  Maximum number of calls per time period
     * @param  float  $period  Time period in seconds
     * @return self The rate limiter
     */
    public static function create(int $maxCalls, float $period): self
    {
        return new self($maxCalls, $period);
    }

    /**
     * Create a rate-limited version of an async function.
     *
     * @template T
     *
     * @param callable(...mixed): PromiseInterface<T> $fn Function to rate limit
     * @return callable(...mixed): PromiseInterface<T> Rate-limited function
     */
    public function limit(callable $fn): callable
    {
        return function (...$args) use ($fn): PromiseInterface {
            $deferred = new Deferred;
            $this->queue[] = [$args, $deferred, $fn];

            $this->processQueue();

            return $deferred->promise();
        };
    }

    /**
     * Process the queue of pending calls.
     */
    private function processQueue(): void
    {
        if ($this->processing) {
            return;
        }

        $this->processing = true;

        Async::loop()->futureTick(function (): void {
            $this->processQueueStep();
        });
    }

    /**
     * Process a single step of the queue.
     */
    private function processQueueStep(): void
    {
        // Remove timestamps outside the current time window
        $now = microtime(true);
        $this->timeWindow = array_filter(
            $this->timeWindow,
            fn ($time) => $now - $time <= $this->period
        );

        // Process queue if we haven't hit the rate limit
        while (! empty($this->queue) && count($this->timeWindow) < $this->maxCalls) {
            [$args, $deferred, $fn] = array_shift($this->queue);
            $this->timeWindow[] = microtime(true);

            try {
                $fn(...$args)->then(
                    fn ($result) => $deferred->resolve($result),
                    fn ($error) => $deferred->reject($error)
                );
            } catch (\Throwable $e) {
                $deferred->reject($e);
            }
        }

        // If there are still items in the queue, schedule next processing
        if (! empty($this->queue)) {
            $nextProcessTime = $this->period / $this->maxCalls;

            Async::loop()->addTimer($nextProcessTime, function (): void {
                $this->processQueueStep();
            });
        } else {
            $this->processing = false;
        }
    }
}
