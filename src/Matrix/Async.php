<?php

declare(strict_types=1);

namespace Matrix;

use Matrix\Events\EventDispatcher;
use Matrix\Exceptions\AsyncException;
use Matrix\Exceptions\CancellationException;
use Matrix\Exceptions\RetryException;
use Matrix\Exceptions\TimeoutException;
use Matrix\Interfaces\Async as AsyncInterface;
use Matrix\Metrics\MetricsCollector;
use Matrix\Promise\PromisePool;
use Matrix\Promise\TaskScheduler;
use Matrix\Support\Lifecycle;
use Matrix\Support\LoopManager;
use Matrix\Support\OperationState;
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

    /**
     * @template T
     *
     * @param  callable(): (T|PromiseInterface<T>)  $callable
     * @return PromiseInterface<T>
     */
    public static function coro(callable $callable): PromiseInterface
    {
        $state = OperationState::create();
        /** @var PromiseInterface<mixed>|null $active */
        $active = null;
        $deferred = new Deferred(static function () use ($state, &$active): void {
            if ($state->isSettled()) {
                return;
            }
            $state->settle();
            $active?->cancel();

            throw new CancellationException;
        });

        LoopManager::nextTick(static function () use ($callable, $deferred, $state, &$active): void {
            if ($state->isSettled()) {
                return;
            }

            try {
                $active = Promise\resolve($callable());
            } catch (\Throwable $exception) {
                $state->settle();
                $deferred->reject($exception);

                return;
            }

            // @phpstan-ignore if.alwaysFalse (The factory may cancel its enclosing operation re-entrantly.)
            if ($state->isSettled()) {
                $active->cancel();

                return;
            }
            $active->then(
                static function (mixed $value) use ($deferred, $state): void {
                    // @phpstan-ignore booleanNot.alwaysTrue (Source settlement happens after this callback is registered.)
                    if (! $state->isSettled()) {
                        $state->settle();
                        $deferred->resolve($value);
                    }
                },
                static function (\Throwable $error) use ($deferred, $state): void {
                    // @phpstan-ignore booleanNot.alwaysTrue (Source settlement happens after this callback is registered.)
                    if (! $state->isSettled()) {
                        $state->settle();
                        $deferred->reject($error);
                    }
                }
            );
        });

        return Lifecycle::track($deferred->promise(), 'coro');
    }

    public static function generatePromiseId(): string
    {
        return Lifecycle::id();
    }

    /**
     * @template T
     *
     * @param  PromiseInterface<T>  $promise
     * @return T|null
     */
    public static function await(PromiseInterface $promise, ?float $timeout = null): mixed
    {
        if ($timeout !== null && (! is_finite($timeout) || $timeout <= 0)) {
            throw new \InvalidArgumentException('Await timeout must be finite and greater than zero.');
        }

        if (LoopManager::isRunning()) {
            throw new AsyncException('await() cannot be called while the Matrix event loop is running.');
        }

        $value = null;
        $error = null;
        $state = OperationState::create();
        /** @var TimerInterface|null $timer */
        $timer = null;
        $finish = static function () use (&$timer): void {
            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }

            if (LoopManager::isRunning()) {
                LoopManager::stop();
            }
        };

        $promise->then(
            static function (mixed $result) use (&$value, $state, $finish): void {
                if ($state->isSettled()) {
                    return;
                }
                $value = $result;
                $state->settle();
                $finish();
            },
            static function (\Throwable $reason) use (&$error, $state, $finish): void {
                if ($state->isSettled()) {
                    return;
                }
                $error = $reason;
                $state->settle();
                $finish();
            }
        );

        if (! $state->isSettled() && $timeout !== null) {
            $timer = LoopManager::delay($timeout, static function () use (&$error, $state, &$timer, $finish, $promise, $timeout): void {
                $state->settle();
                $timer = null;
                $error = new TimeoutException($timeout);
                $promise->cancel();
                $finish();
            });
        }

        if (! $state->isSettled()) {
            try {
                LoopManager::run();
            } catch (\Throwable $loopError) {
                $state->settle();
                $finish();

                try {
                    $promise->cancel();
                } catch (\Throwable) {
                    // Preserve the event-loop error that ended this wait.
                }

                throw $loopError;
            }
        }

        if (! $state->isSettled()) {
            $state->settle();

            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }

            throw new AsyncException('Promise did not settle before the event loop became idle.');
        }

        if ($error !== null) {
            throw $error;
        }

        return $value;
    }

    /**
     * @template T
     *
     * @param  T|null  $value
     * @return PromiseInterface<T|null>
     */
    public static function delay(float $seconds, mixed $value = null): PromiseInterface
    {
        if (! is_finite($seconds) || $seconds < 0) {
            throw new \InvalidArgumentException('Delay must be finite and not negative.');
        }

        /** @var TimerInterface|null $timer */
        $timer = null;
        $state = OperationState::create();
        $deferred = new Deferred(static function () use (&$timer, $state): void {
            if ($state->isSettled()) {
                return;
            }
            $state->settle();

            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }

            throw new CancellationException;
        });
        $timer = LoopManager::delay($seconds, static function () use ($deferred, $value, $state, &$timer): void {
            if ($state->isSettled()) {
                return;
            }
            $state->settle();
            $timer = null;
            $deferred->resolve($value);
        });

        return Lifecycle::track($deferred->promise(), 'delay');
    }

    /**
     * @template T
     *
     * @param  PromiseInterface<T>  $promise
     * @return PromiseInterface<T>
     */
    public static function timeout(PromiseInterface $promise, float $seconds, string $message = 'Operation timed out'): PromiseInterface
    {
        if (! is_finite($seconds) || $seconds <= 0) {
            throw new \InvalidArgumentException('Timeout must be finite and greater than zero.');
        }

        $id = Lifecycle::id();
        /** @var TimerInterface|null $timer */
        $timer = null;
        $state = OperationState::create();
        $deferred = new Deferred(static function () use (&$timer, $state, $promise): void {
            if ($state->isSettled()) {
                return;
            }
            $state->settle();

            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }
            $promise->cancel();

            throw new CancellationException;
        });
        $finish = static function () use (&$timer): void {
            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }
        };

        $promise->then(
            static function ($value) use ($deferred, $state, $finish): void {
                if ($state->isSettled()) {
                    return;
                }
                $state->settle();
                $finish();
                $deferred->resolve($value);
            },
            static function (\Throwable $reason) use ($deferred, $state, $finish): void {
                if ($state->isSettled()) {
                    return;
                }
                $state->settle();
                $finish();
                $deferred->reject($reason);
            }
        );

        if (! $state->isSettled()) {
            $timer = LoopManager::delay($seconds, static function () use ($deferred, $state, &$timer, $promise, $seconds, $message, $id): void {
                $state->settle();
                $timer = null;
                Lifecycle::timeout($id, 'timeout', $seconds, $message);
                $promise->cancel();
                $deferred->reject(new TimeoutException($seconds, $message));
            });
        }

        return Lifecycle::track($deferred->promise(), 'timeout', $id);
    }

    /**
     * @template T
     *
     * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
     * @return PromiseInterface<array<array-key, T>>
     */
    public static function all(iterable $promises): PromiseInterface
    {
        return Lifecycle::track(Promise\all($promises), 'all');
    }

    /**
     * @template T
     *
     * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
     * @return PromiseInterface<T>
     */
    public static function any(iterable $promises): PromiseInterface
    {
        return Lifecycle::track(Promise\any($promises), 'any');
    }

    /**
     * @template T
     *
     * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
     * @return PromiseInterface<T>
     */
    public static function race(iterable $promises): PromiseInterface
    {
        return Lifecycle::track(Promise\race($promises), 'race');
    }

    /**
     * @template T
     *
     * @param  T|PromiseInterface<T>  $value
     * @return PromiseInterface<T>
     */
    public static function resolve(mixed $value): PromiseInterface
    {
        return Lifecycle::track(Promise\resolve($value), 'resolve');
    }

    /** @return PromiseInterface<mixed> */
    public static function reject(\Throwable $reason): PromiseInterface
    {
        return Lifecycle::track(Promise\reject($reason), 'reject');
    }

    /**
     * @template T
     * @template U
     *
     * @param  iterable<array-key, T>  $items
     * @param  callable(T): (U|PromiseInterface<U>)  $callback
     * @param  (callable(int, int|null): void)|null  $onProgress
     * @return PromiseInterface<array<array-key, U>>
     */
    public static function map(iterable $items, callable $callback, int $concurrency = 0, ?callable $onProgress = null): PromiseInterface
    {
        if ($concurrency < 0) {
            throw new \InvalidArgumentException('Map concurrency must be zero or greater.');
        }

        return Lifecycle::track(TaskScheduler::run($items, $callback, $concurrency, $onProgress), 'map');
    }

    /**
     * @template T
     * @template U
     *
     * @param  iterable<array-key, T>  $items
     * @param  callable(list<T>): (iterable<array-key, U>|PromiseInterface<iterable<array-key, U>>)  $batchCallback
     * @return PromiseInterface<list<U>>
     */
    public static function batch(iterable $items, callable $batchCallback, int $batchSize = 10, int $concurrency = 1): PromiseInterface
    {
        if ($batchSize < 1 || $concurrency < 1) {
            throw new \InvalidArgumentException('Batch size and concurrency must be positive.');
        }

        $batches = static function () use ($items, $batchSize): \Generator {
            $batch = [];

            foreach ($items as $item) {
                $batch[] = $item;

                if (count($batch) === $batchSize) {
                    yield $batch;
                    $batch = [];
                }
            }

            if ($batch !== []) {
                yield $batch;
            }
        };

        $promise = TaskScheduler::run($batches(), $batchCallback, $concurrency)->then(
            static function (array $results): array {
                $flattened = [];

                foreach ($results as $batchResult) {
                    foreach (self::assertBatchIterable($batchResult) as $value) {
                        $flattened[] = $value;
                    }
                }

                return $flattened;
            }
        );

        return Lifecycle::track($promise, 'batch');
    }

    /**
     * @template T
     *
     * @param  callable(): (T|PromiseInterface<T>)  $factory
     * @param  (callable(int, \Throwable): mixed)|null  $backoffStrategy
     * @return PromiseInterface<T>
     */
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
        $state = OperationState::create();
        $deferred = new Deferred(static function () use (&$active, &$timer, $state): void {
            if ($state->isSettled()) {
                return;
            }
            $state->settle();

            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }

            if ($active !== null) {
                $active->cancel();
            }

            throw new CancellationException;
        });

        $try = null;
        $try = static function () use (&$try, &$attempt, &$failures, &$active, &$timer, $state, $factory, $maxAttempts, $backoffStrategy, $deferred): void {
            if ($state->isSettled()) {
                return;
            }
            $attempt++;

            try {
                $active = Promise\resolve($factory());
            } catch (\Throwable $exception) {
                $active = Promise\reject($exception);
            }

            // The factory can cancel this retry before returning its source.
            // @phpstan-ignore if.alwaysFalse (The factory can cancel the enclosing operation.)
            if ($state->isSettled()) {
                $active->then(null, static function (\Throwable $error): void {});
                $active->cancel();
                $active = null;

                return;
            }
            $active->then(
                static function (mixed $value) use ($deferred, $state): void {
                    // @phpstan-ignore booleanNot.alwaysTrue (Source settlement can happen after a retry is cancelled.)
                    if (! $state->isSettled()) {
                        $state->settle();
                        $deferred->resolve($value);
                    }
                },
                static function (\Throwable $error) use (&$try, &$attempt, &$failures, &$active, &$timer, $state, $maxAttempts, $backoffStrategy, $deferred): void {
                    // @phpstan-ignore if.alwaysFalse (Retry cancellation can precede a late source rejection.)
                    if ($state->isSettled()) {
                        return;
                    }
                    $active = null;
                    $failures[] = $error;

                    if ($attempt >= $maxAttempts) {
                        $state->settle();
                        $deferred->reject(new RetryException($attempt, $failures, "All {$maxAttempts} retry attempts failed", 0, $error));

                        return;
                    }

                    try {
                        $delay = $backoffStrategy !== null
                            ? $backoffStrategy($attempt, $error)
                            : min(2 ** ($attempt - 1) * 0.1, 5.0);
                    } catch (\Throwable $backoffError) {
                        $state->settle();
                        $deferred->reject($backoffError);

                        return;
                    }

                    // @phpstan-ignore if.alwaysFalse (The backoff callback can cancel the retry.)
                    if ($state->isSettled()) {
                        return;
                    }

                    if ($delay === null) {
                        $state->settle();
                        $deferred->reject($error);

                        return;
                    }

                    if (! is_int($delay) && ! is_float($delay)) {
                        $state->settle();
                        $deferred->reject(new \InvalidArgumentException('Retry backoff must be a number or null.', 0, $error));

                        return;
                    }
                    $delay = (float) $delay;

                    if (! is_finite($delay) || $delay < 0) {
                        $state->settle();
                        $deferred->reject(new \InvalidArgumentException('Retry backoff must be finite and not negative.', 0, $error));

                        return;
                    }

                    try {
                        $timer = LoopManager::delay($delay, static function () use (&$timer, $try): void {
                            $timer = null;
                            $try();
                        });
                    } catch (\Throwable $timerError) {
                        $state->settle();
                        $deferred->reject($timerError);
                    }
                }
            );
        };

        LoopManager::nextTick($try);

        return Lifecycle::track($deferred->promise(), 'retry');
    }

    /**
     * @template T
     *
     * @param  iterable<array-key, callable(): (T|PromiseInterface<T>)>  $callables
     * @param  (callable(int, int|null): void)|null  $onProgress
     * @return PromiseInterface<array<array-key, T>>
     */
    public static function pool(iterable $callables, int $concurrency = 5, ?callable $onProgress = null): PromiseInterface
    {
        if ($concurrency < 1) {
            throw new \InvalidArgumentException('Pool concurrency must be positive.');
        }

        return PromisePool::create($callables, $concurrency, $onProgress);
    }

    /**
     * @template T
     *
     * @param  iterable<callable(T|null): (T|PromiseInterface<T>)>  $callables
     * @param  T|null  $initialValue
     * @return PromiseInterface<T|null>
     */
    public static function waterfall(iterable $callables, mixed $initialValue = null): PromiseInterface
    {
        $promise = Promise\resolve($initialValue);

        foreach ($callables as $callable) {
            $promise = $promise->then(static fn (mixed $value): PromiseInterface => Promise\resolve($callable($value)));
        }

        return Lifecycle::track($promise, 'waterfall');
    }

    /**
     * @param  PromiseInterface<mixed>  $promise
     * @return PromiseInterface<mixed>
     */
    public static function cancellable(PromiseInterface $promise, callable $onCancel): PromiseInterface
    {
        $state = OperationState::create();
        $subscription = new class
        {
            /** @var PromiseInterface<mixed>|null */
            public ?PromiseInterface $promise = null;

            public function cancel(): void
            {
                $this->promise?->cancel();
            }
        };
        $deferred = new Deferred(static function () use ($state, $onCancel, $subscription): void {
            if ($state->isSettled()) {
                return;
            }
            $state->settle();

            try {
                $onCancel();
            } finally {
                $subscription->cancel();
            }

            throw new CancellationException;
        });
        $subscription->promise = $promise->then(
            static function (mixed $value) use ($state, $deferred): void {
                if (! $state->isSettled()) {
                    $state->settle();
                    $deferred->resolve($value);
                }
            },
            static function (\Throwable $error) use ($state, $deferred): void {
                if (! $state->isSettled()) {
                    $state->settle();
                    $deferred->reject($error);
                }
            }
        );

        return Lifecycle::track($deferred->promise(), 'cancellable');
    }

    /**
     * @param  PromiseInterface<mixed>  $promise
     * @return PromiseInterface<mixed>
     */
    public static function withErrorContext(PromiseInterface $promise, string $context): PromiseInterface
    {
        $result = $promise->then(
            static fn (mixed $value): mixed => $value,
            static function (\Throwable $error) use ($context): never {
                if ($error instanceof AsyncException) {
                    throw $error->withContext($context);
                }
                $code = $error->getCode();

                throw new AsyncException("{$context}: {$error->getMessage()}", is_int($code) ? $code : 0, $error);
            }
        );

        return Lifecycle::track($result, 'error_context');
    }

    /**
     * @return callable(mixed...): PromiseInterface<mixed>
     */
    public static function rateLimit(callable $fn, int $maxCalls, float $period): callable
    {
        return RateLimiter::create($maxCalls, $period)->limit($fn);
    }

    /** @return iterable<mixed, mixed> */
    private static function assertBatchIterable(mixed $result): iterable
    {
        if (! is_iterable($result)) {
            throw new \UnexpectedValueException('Each batch callback must return an iterable result.');
        }

        return $result;
    }
}
