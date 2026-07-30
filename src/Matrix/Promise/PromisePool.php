<?php

declare(strict_types=1);

namespace Matrix\Promise;

use Matrix\Support\Lifecycle;
use Matrix\Support\LoopManager;
use React\Promise;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/** @template T A keyed, fail-fast pool of promise-returning tasks. */
final class PromisePool
{
    /** @var array<array-key, callable(): mixed> */
    private array $tasks;

    private int $concurrency;

    /** @var (callable(int, int): void)|null */
    private $progressCallback;

    public function __construct(iterable $tasks, int $concurrency = 5, ?callable $progressCallback = null)
    {
        if ($concurrency < 1) {
            throw new \InvalidArgumentException('Pool concurrency must be positive.');
        }
        /** @var array<array-key, callable(): mixed> $normalized */
        $normalized = is_array($tasks) ? $tasks : iterator_to_array($tasks, true);
        $this->tasks = $normalized;
        $this->concurrency = $concurrency;
        $this->progressCallback = $progressCallback;
    }

    public static function create(iterable $tasks, int $concurrency = 5, ?callable $progressCallback = null): PromiseInterface
    {
        return (new self($tasks, $concurrency, $progressCallback))->run();
    }

    /** @return PromiseInterface<array<T>> */
    public function run(): PromiseInterface
    {
        if ($this->tasks === []) {
            return Lifecycle::track(Promise\resolve([]), 'pool');
        }

        $keys = array_keys($this->tasks);
        $total = count($keys);
        $position = 0;
        $pending = 0;
        $completed = 0;
        $results = [];
        $active = [];
        $settled = false;
        $scheduled = false;
        $tasks = $this->tasks;
        $concurrency = $this->concurrency;
        $progressCallback = $this->progressCallback;
        $deferred = new Deferred(static function () use (&$settled, &$active): void {
            $settled = true;

            foreach ($active as $promise) {
                $promise->cancel();
            }
            $active = [];
        });

        $pump = static function (): void {};
        $schedule = static function () use (&$scheduled, &$pump): void {
            if ($scheduled) {
                return;
            }
            $scheduled = true;
            LoopManager::nextTick(static function () use (&$scheduled, &$pump): void {
                $scheduled = false;
                $pump();
            });
        };
        $fail = static function (\Throwable $error) use (&$settled, $deferred): void {
            if (! $settled) {
                $settled = true;
                $deferred->reject($error);
            }
        };
        $pump = static function () use ($schedule, &$settled, &$position, &$pending, &$completed, &$results, &$active, $keys, $total, $deferred, $fail, $tasks, $concurrency, $progressCallback): void {
            if ($settled) {
                return;
            }

            while (! $settled && $pending < $concurrency && $position < $total) {
                $index = $position++;
                $key = $keys[$index];

                try {
                    $promise = Promise\resolve(($tasks[$key])());
                } catch (\Throwable $error) {
                    $fail($error);

                    return;
                }
                $active[$index] = $promise;
                $pending++;
                $promise->then(
                    static function (mixed $value) use (&$pending, &$completed, &$results, &$active, $index, $key, $total, $schedule, $fail, &$settled, $progressCallback): void {
                        unset($active[$index]);
                        $pending--;
                        $completed++;
                        $results[$key] = $value;

                        if ($progressCallback !== null) {
                            try {
                                $progressCallback($completed, $total);
                            } catch (\Throwable $error) {
                                $fail($error);

                                return;
                            }
                        }
                        $schedule();
                    },
                    static function (\Throwable $error) use ($fail): void {
                        $fail($error);
                    }
                );
            }

            if (! $settled && $position >= $total && $pending === 0) {
                $settled = true;
                $deferred->resolve($results);
            }
        };

        $schedule();

        return Lifecycle::track($deferred->promise(), 'pool');
    }
}
