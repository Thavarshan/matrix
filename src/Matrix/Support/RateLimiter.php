<?php

declare(strict_types=1);

namespace Matrix\Support;

use React\Promise;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/** Utility for limiting calls in a sliding time window. */
final class RateLimiter
{
    private int $maxCalls;

    private float $period;

    /** @var list<float> */
    private array $timeWindow = [];

    /** @var list<array{args: array<int|string, mixed>, deferred: Deferred<mixed>, fn: callable, cancelled: bool, active: PromiseInterface<mixed>|null}> */
    private array $queue = [];

    private bool $processing = false;

    public function __construct(int $maxCalls, float $period)
    {
        if ($maxCalls < 1 || $period <= 0) {
            throw new \InvalidArgumentException('Rate limit calls and period must be positive.');
        }
        $this->maxCalls = $maxCalls;
        $this->period = $period;
    }

    public static function create(int $maxCalls, float $period): self
    {
        return new self($maxCalls, $period);
    }

    public function limit(callable $fn): callable
    {
        return function (...$args) use ($fn): PromiseInterface {
            $cancelled = false;
            $active = null;
            $deferred = new Deferred(function () use (&$cancelled, &$active): void {
                $cancelled = true;
                $active?->cancel();
            });
            $entry = ['args' => $args, 'deferred' => $deferred, 'fn' => $fn, 'cancelled' => &$cancelled, 'active' => &$active];
            $this->queue[] = $entry;
            $this->processQueue();

            return Lifecycle::track($deferred->promise(), 'rate_limit');
        };
    }

    private function processQueue(): void
    {
        if ($this->processing) {
            return;
        }
        $this->processing = true;
        LoopManager::nextTick(function (): void {
            $this->processQueueStep();
        });
    }

    private function processQueueStep(): void
    {
        $now = microtime(true);
        $this->timeWindow = array_values(array_filter(
            $this->timeWindow,
            fn (float $time): bool => $now - $time < $this->period
        ));

        while ($this->queue !== [] && count($this->timeWindow) < $this->maxCalls) {
            $entry = array_shift($this->queue);
            $args = $entry['args'];
            $deferred = $entry['deferred'];
            $fn = $entry['fn'];

            if ($entry['cancelled']) {
                continue;
            }
            $this->timeWindow[] = microtime(true);

            try {
                $entry['active'] = Promise\resolve($fn(...$args));
                $entry['active']->then(
                    [$deferred, 'resolve'],
                    [$deferred, 'reject']
                )->then(
                    static function () use (&$entry): void {
                        $entry['active'] = null;
                    },
                    static function () use (&$entry): void {
                        $entry['active'] = null;
                    }
                );
            } catch (\Throwable $error) {
                $deferred->reject($error);
            }
        }

        if ($this->queue === []) {
            $this->processing = false;

            return;
        }

        $wait = max(0.0, ($this->timeWindow[0] + $this->period) - microtime(true));
        LoopManager::delay($wait, function (): void {
            $this->processQueueStep();
        });
    }
}
