<?php

declare(strict_types=1);

namespace Matrix;

use Matrix\Events\EventDispatcher;
use Matrix\Exceptions\AsyncException;
use Matrix\Exceptions\RetryException;
use Matrix\Exceptions\TimeoutException;
use Matrix\Interfaces\Async as AsyncInterface;
use Matrix\Metrics\MetricsCollector;
use Matrix\Promise\PromisePool;
use Matrix\Support\Lifecycle;
use Matrix\Support\LoopManager;
use Matrix\Support\RateLimiter;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/** Static async façade for ReactPHP. */
final class Async implements AsyncInterface
{
    private static ?EventDispatcher $eventDispatcher = null;

    private static ?MetricsCollector $metricsCollector = null;

    private function __construct() {}

    public static function loop(): LoopInterface
    {
        return Loop::get();
    }

    public static function eventDispatcher(): EventDispatcher
    {
        return self::$eventDispatcher ??= new EventDispatcher;
    }

    public static function metricsCollector(): MetricsCollector
    {
        return self::$metricsCollector ??= new MetricsCollector;
    }

    /** @param callable(): mixed $callable @return PromiseInterface<mixed> */
    public static function coro(callable $callable): PromiseInterface
    {
        $deferred = new Deferred;

        LoopManager::nextTick(static function () use ($callable, $deferred): void {
            try {
                Promise\resolve($callable())->then([$deferred, 'resolve'], [$deferred, 'reject']);
            } catch (\Throwable $exception) {
                $deferred->reject($exception);
            }
        });

        return Lifecycle::track($deferred->promise(), 'coro');
    }

    public static function generatePromiseId(): string
    {
        return Lifecycle::id();
    }

    /** @param PromiseInterface<mixed> $promise @return mixed */
    public static function await(PromiseInterface $promise, ?float $timeout = null): mixed
    {
        if ($timeout !== null && $timeout <= 0) {
            throw new \InvalidArgumentException('Await timeout must be greater than zero.');
        }

        if (LoopManager::isRunning()) {
            throw new AsyncException('await() cannot be called while the Matrix event loop is running.');
        }

        $value = null;
        $error = null;
        $settled = false;
        /** @var TimerInterface|null $timer */
        $timer = null;
        $finish = static function () use (&$timer): void {
            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }
            LoopManager::stop();
        };

        $promise->then(
            static function (mixed $result) use (&$value, &$settled, $finish): void {
                $value = $result;
                $settled = true;
                $finish();
            },
            static function (\Throwable $reason) use (&$error, &$settled, $finish): void {
                $error = $reason;
                $settled = true;
                $finish();
            }
        );

        if (! $settled && $timeout !== null) {
            $timer = LoopManager::delay($timeout, static function () use (&$error, &$settled, $finish, $promise, $timeout): void {
                if ($settled) {
                    return;
                }
                $settled = true;
                $error = new TimeoutException($timeout);
                $promise->cancel();
                $finish();
            });
        }

        if (! $settled) {
            LoopManager::run();
        }

        if (! $settled) {
            throw new AsyncException('Promise did not settle before the event loop became idle.');
        }

        if ($error !== null) {
            throw $error;
        }

        return $value;
    }

    /** @return PromiseInterface<mixed> */
    public static function delay(float $seconds, mixed $value = null): PromiseInterface
    {
        if ($seconds < 0) {
            throw new \InvalidArgumentException('Delay must not be negative.');
        }

        /** @var TimerInterface|null $timer */
        $timer = null;
        $deferred = new Deferred(static function () use (&$timer): void {
            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }
        });
        $timer = LoopManager::delay($seconds, static function () use ($deferred, $value): void {
            $deferred->resolve($value);
        });

        return Lifecycle::track($deferred->promise(), 'delay');
    }

    /** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
    public static function timeout(PromiseInterface $promise, float $seconds, string $message = 'Operation timed out'): PromiseInterface
    {
        if ($seconds <= 0) {
            throw new \InvalidArgumentException('Timeout must be greater than zero.');
        }

        $id = Lifecycle::id();
        /** @var TimerInterface|null $timer */
        $timer = null;
        $settled = false;
        $deferred = new Deferred(static function () use (&$timer, &$settled, $promise): void {
            if ($settled) {
                return;
            }
            $settled = true;

            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
            }
            $promise->cancel();
        });
        $finish = static function () use (&$timer): void {
            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }
        };

        $promise->then(
            static function (mixed $value) use ($deferred, &$settled, $finish): void {
                if ($settled) {
                    return;
                }
                $settled = true;
                $finish();
                $deferred->resolve($value);
            },
            static function (\Throwable $reason) use ($deferred, &$settled, $finish): void {
                if ($settled) {
                    return;
                }
                $settled = true;
                $finish();
                $deferred->reject($reason);
            }
        );

        $timer = LoopManager::delay($seconds, static function () use ($deferred, &$settled, $promise, $seconds, $message, $id): void {
            if ($settled) {
                return;
            }
            $settled = true;
            Lifecycle::timeout($id, 'timeout', $seconds, $message);
            $promise->cancel();
            $deferred->reject(new TimeoutException($seconds, $message));
        });

        return Lifecycle::track($deferred->promise(), 'timeout', $id);
    }

    /** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
    public static function all(iterable $promisesOrValues): PromiseInterface
    {
        return Lifecycle::track(Promise\all($promisesOrValues), 'all');
    }

    /** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
    public static function any(iterable $promisesOrValues): PromiseInterface
    {
        return Lifecycle::track(Promise\any($promisesOrValues), 'any');
    }

    /** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
    public static function race(iterable $promisesOrValues): PromiseInterface
    {
        return Lifecycle::track(Promise\race($promisesOrValues), 'race');
    }

    /** @return PromiseInterface<mixed> */
    public static function resolve(mixed $value): PromiseInterface
    {
        return Lifecycle::track(Promise\resolve($value), 'resolve');
    }

    /** @return PromiseInterface<mixed> */
    public static function reject(\Throwable $reason): PromiseInterface
    {
        return Lifecycle::track(Promise\reject($reason), 'reject');
    }

    /** @param iterable<array-key, mixed> $items @return PromiseInterface<mixed> */
    public static function map(iterable $items, callable $callback, int $concurrency = 0, ?callable $onProgress = null): PromiseInterface
    {
        if ($concurrency < 0) {
            throw new \InvalidArgumentException('Map concurrency must be zero or greater.');
        }

        return Lifecycle::track(self::mapInternal($items, $callback, $concurrency, $onProgress), 'map');
    }

    /** @param iterable<array-key, mixed> $items @return PromiseInterface<mixed> */
    public static function batch(iterable $items, callable $batchCallback, int $batchSize = 10, int $concurrency = 1): PromiseInterface
    {
        if ($batchSize < 1 || $concurrency < 1) {
            throw new \InvalidArgumentException('Batch size and concurrency must be positive.');
        }

        $batches = array_chunk(array_values(iterator_to_array($items, false)), $batchSize);

        return Lifecycle::track(self::mapInternal($batches, $batchCallback, $concurrency, null)->then(
            static fn (array $results): array => array_merge(...$results)
        ), 'batch');
    }

    /** @return PromiseInterface<mixed> */
    public static function retry(callable $factory, int $maxAttempts = 3, ?callable $backoffStrategy = null): PromiseInterface
    {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('Retry attempts must be positive.');
        }

        $attempt = 0;
        $failures = [];
        /** @var PromiseInterface<mixed>|null $active */
        $active = null;
        /** @var TimerInterface|null $timer */
        $timer = null;
        $settled = false;
        $deferred = new Deferred(static function () use (&$active, &$timer, &$settled): void {
            $settled = true;

            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
            }

            if ($active !== null) {
                $active->cancel();
            }
        });

        $try = null;
        $try = static function () use (&$try, &$attempt, &$failures, &$active, &$timer, &$settled, $factory, $maxAttempts, $backoffStrategy, $deferred): void {
            if ($settled) {
                return;
            }
            $attempt++;

            try {
                $active = Promise\resolve($factory());
            } catch (\Throwable $exception) {
                $active = Promise\reject($exception);
            }
            $active->then(
                static function (mixed $value) use ($deferred, &$settled): void {
                    if (! $settled) {
                        $settled = true;
                        $deferred->resolve($value);
                    }
                },
                static function (\Throwable $error) use (&$try, &$attempt, &$failures, &$active, &$timer, &$settled, $maxAttempts, $backoffStrategy, $deferred): void {
                    if ($settled) {
                        return;
                    }
                    $active = null;
                    $failures[] = $error;

                    if ($attempt >= $maxAttempts) {
                        $settled = true;
                        $deferred->reject(new RetryException($attempt, $failures, "All {$maxAttempts} retry attempts failed", 0, $error));

                        return;
                    }
                    $delay = $backoffStrategy !== null
                        ? $backoffStrategy($attempt, $error)
                        : min(2 ** ($attempt - 1) * 0.1, 5.0);

                    if ($delay === null) {
                        $settled = true;
                        $deferred->reject($error);

                        return;
                    }

                    if ($delay < 0) {
                        $settled = true;
                        $deferred->reject(new \InvalidArgumentException('Retry backoff must not be negative.', 0, $error));

                        return;
                    }
                    $timer = LoopManager::delay($delay, $try);
                }
            );
        };

        LoopManager::nextTick($try);

        return Lifecycle::track($deferred->promise(), 'retry');
    }

    /** @param iterable<array-key, callable(): mixed> $callables @return PromiseInterface<mixed> */
    public static function pool(iterable $callables, int $concurrency = 5, ?callable $onProgress = null): PromiseInterface
    {
        if ($concurrency < 1) {
            throw new \InvalidArgumentException('Pool concurrency must be positive.');
        }

        return PromisePool::create($callables, $concurrency, $onProgress);
    }

    /** @param iterable<callable(mixed): mixed> $callables @return PromiseInterface<mixed> */
    public static function waterfall(iterable $callables, mixed $initialValue = null): PromiseInterface
    {
        $promise = Promise\resolve($initialValue);

        foreach ($callables as $callable) {
            $promise = $promise->then(static fn (mixed $value): PromiseInterface => Promise\resolve($callable($value)));
        }

        return Lifecycle::track($promise, 'waterfall');
    }

    /** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
    public static function cancellable(PromiseInterface $promise, callable $onCancel): PromiseInterface
    {
        $cancelled = false;
        $wrapped = new Promise\Promise(
            static function (callable $resolve, callable $reject) use ($promise): void {
                $promise->then($resolve, $reject);
            },
            static function () use (&$cancelled, $onCancel, $promise): void {
                if ($cancelled) {
                    return;
                }
                $cancelled = true;
                $onCancel();
                $promise->cancel();
            }
        );

        return Lifecycle::track($wrapped, 'cancellable');
    }

    /** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
    public static function withErrorContext(PromiseInterface $promise, string $context): PromiseInterface
    {
        $result = $promise->then(
            static fn (mixed $value): mixed => $value,
            static function (\Throwable $error) use ($context): never {
                if ($error instanceof AsyncException) {
                    throw $error;
                }

                throw new AsyncException("{$context}: {$error->getMessage()}", $error->getCode(), $error);
            }
        );

        return Lifecycle::track($result, 'error_context');
    }

    public static function rateLimit(callable $fn, int $maxCalls, float $period): callable
    {
        return RateLimiter::create($maxCalls, $period)->limit($fn);
    }

    /** @param iterable<array-key, mixed> $items @return PromiseInterface<mixed> */
    private static function mapInternal(iterable $items, callable $callback, int $concurrency, ?callable $onProgress): PromiseInterface
    {
        $items = is_array($items) ? $items : iterator_to_array($items, true);

        if ($items === []) {
            return Promise\resolve([]);
        }

        $keys = array_keys($items);
        $total = count($keys);
        $position = 0;
        $pending = 0;
        $completed = 0;
        $results = [];
        $active = [];
        $settled = false;
        $scheduled = false;
        $deferred = new Deferred(static function () use (&$settled, &$active): void {
            $settled = true;

            foreach ($active as $promise) {
                $promise->cancel();
            }
            $active = [];
        });

        $schedule = null;
        $pump = static function (): void {};
        $fail = static function (\Throwable $error) use (&$settled, $deferred): void {
            if (! $settled) {
                $settled = true;
                $deferred->reject($error);
            }
        };
        $schedule = static function () use (&$schedule, &$scheduled, &$pump): void {
            if ($scheduled) {
                return;
            }
            $scheduled = true;
            LoopManager::nextTick(static function () use (&$scheduled, &$pump): void {
                $scheduled = false;
                $pump();
            });
        };
        $pump = static function () use ($schedule, &$settled, &$position, &$pending, &$completed, &$results, &$active, $items, $keys, $total, $concurrency, $callback, $onProgress, $deferred, $fail): void {
            if ($settled) {
                return;
            }
            $limit = $concurrency === 0 ? $total : $concurrency;

            while (! $settled && $pending < $limit && $position < $total) {
                $index = $position++;
                $key = $keys[$index];

                try {
                    $promise = Promise\resolve($callback($items[$key]));
                } catch (\Throwable $error) {
                    $fail($error);

                    return;
                }
                $active[$index] = $promise;
                $pending++;
                $promise->then(
                    static function (mixed $value) use (&$pending, &$completed, &$results, &$active, $index, $key, $total, $onProgress, $schedule, $fail, &$settled): void {
                        unset($active[$index]);
                        $pending--;
                        $completed++;
                        $results[$key] = $value;

                        if ($onProgress !== null) {
                            try {
                                $onProgress($completed, $total);
                            } catch (\Throwable $error) {
                                $fail($error);

                                return;
                            }
                        }

                        if (! $settled) {
                            $schedule();
                        }
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

        return $deferred->promise();
    }
}
