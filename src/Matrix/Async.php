<?php

declare(strict_types=1);

namespace Matrix;

use Matrix\Events\EventDispatcher;
use Matrix\Events\PromiseCreated;
use Matrix\Events\PromiseRejected;
use Matrix\Events\PromiseResolved;
use Matrix\Events\PromiseTimeout;
use Matrix\Exceptions\AsyncException;
use Matrix\Exceptions\RetryException;
use Matrix\Exceptions\TimeoutException;
use Matrix\Interfaces\Async as AsyncInterface;
use Matrix\Metrics\MetricsCollector;
use Matrix\Promise\CancellablePromise;
use Matrix\Promise\PromisePool;
use Matrix\Support\LoopManager;
use Matrix\Support\RateLimiter;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise;
use React\Promise\Deferred;

/**
 * Lightweight async helpers for ReactPHP.
 *
 * Provides static methods to work with promises and event loops in a coroutine-style.
 */
class Async implements AsyncInterface
{
    /**
     * @var LoopInterface|null The process-wide event loop instance.
     */
    private static ?LoopInterface $loop = null;

    /**
     * @var EventDispatcher|null The process-wide event dispatcher instance.
     */
    private static ?EventDispatcher $eventDispatcher = null;

    /**
     * @var MetricsCollector|null The process-wide metrics collector instance.
     */
    private static ?MetricsCollector $metricsCollector = null;

    /**
     * Get (and memoize) the process-wide event loop instance.
     *
     * @return LoopInterface The event loop instance.
     */
    public static function loop(): LoopInterface
    {
        return self::$loop ??= Loop::get();
    }

    /**
     * Get (and memoize) the process-wide event dispatcher instance.
     *
     * @return EventDispatcher The event dispatcher instance.
     */
    public static function eventDispatcher(): EventDispatcher
    {
        return self::$eventDispatcher ??= new EventDispatcher;
    }

    /**
     * Get (and memoize) the process-wide metrics collector instance.
     *
     * @return MetricsCollector The metrics collector instance.
     */
    public static function metricsCollector(): MetricsCollector
    {
        return self::$metricsCollector ??= new MetricsCollector;
    }

    /**
     * Run a callable "coroutine-style".
     *
     * @template T
     *
     * @param  callable(): (T|Promise\PromiseInterface<T>)  $callable  The callable to execute.
     * @return Promise\PromiseInterface<T> A promise that resolves with the callable's result.
     */
    public static function coro(callable $callable): Promise\PromiseInterface
    {
        $promiseId = self::generatePromiseId();
        $startTime = microtime(true);
        $deferred = new Deferred;

        // Fire promise created event
        self::eventDispatcher()->dispatch(new PromiseCreated($promiseId, 'coro'));
        self::metricsCollector()->promiseCreated($promiseId, 'coro');

        $promise = $deferred->promise();

        // Wrap the promise to fire events on resolution/rejection
        $promise->then(
            function ($value) use ($promiseId, $startTime) {
                $duration = microtime(true) - $startTime;
                self::eventDispatcher()->dispatch(new PromiseResolved($promiseId, $value, $duration));
                self::metricsCollector()->promiseResolved($promiseId, $value);

                return $value;
            },
            function ($reason) use ($promiseId, $startTime) {
                $duration = microtime(true) - $startTime;
                self::eventDispatcher()->dispatch(new PromiseRejected($promiseId, $reason, $duration));
                self::metricsCollector()->promiseRejected($promiseId, $reason);

                throw $reason;
            }
        );

        LoopManager::nextTick(static function () use ($callable, $deferred): void {
            try {
                $result = $callable();

                ($result instanceof Promise\PromiseInterface)
                    ? $result->then([$deferred, 'resolve'], [$deferred, 'reject'])
                    : $deferred->resolve($result);
            } catch (\Throwable $e) {
                $deferred->reject($e);
            }
        });

        return $promise;
    }

    /**
     * Generate a unique promise ID.
     */
    public static function generatePromiseId(): string
    {
        return 'promise_' . uniqid() . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Block the current fibre / CLI process until the promise settles.
     *
     * @template T
     *
     * @param  Promise\PromiseInterface<T>  $promise  The promise to await.
     * @param  float|null  $timeout  Optional timeout in seconds.
     * @return T The resolved value of the promise.
     *
     * @throws TimeoutException If the promise times out.
     * @throws \Throwable If the promise is rejected.
     */
    public static function await(Promise\PromiseInterface $promise, ?float $timeout = null)
    {
        $value = null;
        $error = null;
        $settled = false;
        $timer = null;

        $cleanup = function () use (&$timer): void {
            if ($timer !== null) {
                LoopManager::cancelTimer($timer);
                $timer = null;
            }
        };

        $promise->then(
            static function ($result) use (&$value, &$settled, $cleanup): void {
                $value = $result;
                $settled = true;
                $cleanup();
                LoopManager::stop();
            },
            static function ($reason) use (&$error, &$settled, $cleanup): void {
                $error = $reason instanceof \Throwable
                       ? $reason
                       : new AsyncException((string)$reason);
                $settled = true;
                $cleanup();
                LoopManager::stop();
            }
        );

        // Set up timeout if specified
        if ($timeout !== null && $timeout > 0) {
            $timeoutValue = $timeout; // Create a copy for the closure
            $timer = LoopManager::delay($timeoutValue, static function () use (&$error, &$settled, $cleanup, $timeoutValue): void {
                $error = new TimeoutException($timeoutValue);
                $settled = true;
                $cleanup();
                LoopManager::stop();
            });
        }

        // Will return immediately if already settled
        if (! $settled) {
            LoopManager::run();
        }

        if ($error !== null) {
            throw $error;
        }

        /** @var T $value */
        return $value;
    }

    /**
     * Create a promise that resolves after a specified delay.
     *
     * @template T
     *
     * @param  float  $seconds  Delay in seconds.
     * @param  T  $value  Value to resolve with (optional).
     * @return Promise\PromiseInterface<T> A promise that resolves after the delay.
     */
    public static function delay(float $seconds, mixed $value = null): Promise\PromiseInterface
    {
        $deferred = new Deferred;

        LoopManager::delay($seconds, static function () use ($deferred, $value): void {
            $deferred->resolve($value);
        });

        return $deferred->promise();
    }

    /**
     * Create a promise that times out after a specified period.
     *
     * @template T
     *
     * @param  Promise\PromiseInterface<T>  $promise  The promise to add a timeout to.
     * @param  float  $seconds  Timeout in seconds.
     * @param  string  $message  Custom timeout message.
     * @return Promise\PromiseInterface<T> A promise that rejects with a timeout error if the original promise doesn't settle in time.
     */
    public static function timeout(
        Promise\PromiseInterface $promise,
        float $seconds,
        string $message = 'Operation timed out'
    ): Promise\PromiseInterface {
        $promiseId = self::generatePromiseId();

        $timeoutPromise = self::delay($seconds)->then(function () use ($message, $seconds, $promiseId): Promise\PromiseInterface {
            // Fire timeout event
            self::eventDispatcher()->dispatch(new PromiseTimeout($promiseId, $seconds, $message));
            self::metricsCollector()->promiseTimeout($promiseId, $seconds);

            return self::reject(new TimeoutException($seconds, $message));
        });

        return self::race([$promise, $timeoutPromise]);
    }

    /**
     * Wait for all promises to resolve.
     *
     * @template T
     *
     * @param  array<Promise\PromiseInterface<T>>  $promises  An array of promises.
     * @return Promise\PromiseInterface<array<T>> A promise that resolves with an array of results.
     */
    public static function all(array $promises): Promise\PromiseInterface
    {
        return Promise\all($promises);
    }

    /**
     * Wait for any promise to resolve.
     *
     * @template T
     *
     * @param  array<Promise\PromiseInterface<T>>  $promises  An array of promises.
     * @return Promise\PromiseInterface<T> A promise that resolves with the first resolved value.
     */
    public static function any(array $promises): Promise\PromiseInterface
    {
        return Promise\any($promises);
    }

    /**
     * Race multiple promises, resolving with the first to settle.
     *
     * @template T
     *
     * @param  array<Promise\PromiseInterface<T>>  $promises  An array of promises.
     * @return Promise\PromiseInterface<T> A promise that resolves or rejects with the first settled value.
     */
    public static function race(array $promises): Promise\PromiseInterface
    {
        return Promise\race($promises);
    }

    /**
     * Resolve a value into a promise.
     *
     * @template T
     *
     * @param  T  $value  The value to resolve.
     * @return Promise\PromiseInterface<T> A promise resolved with the given value.
     */
    public static function resolve(mixed $value): Promise\PromiseInterface
    {
        return Promise\resolve($value);
    }

    /**
     * Reject a promise with a reason.
     *
     * @template T
     *
     * @param  mixed  $reason  The reason for rejection.
     * @return Promise\PromiseInterface<T> A promise rejected with the given reason.
     */
    public static function reject(mixed $reason): Promise\PromiseInterface
    {
        return Promise\reject($reason);
    }

    /**
     * Map an array of items through an async function.
     *
     * @template TIn
     * @template TOut
     *
     * @param  array<TIn>  $items  Array of items to process.
     * @param  callable(TIn): Promise\PromiseInterface<TOut>  $callback  Async callback function.
     * @param  int  $concurrency  Maximum number of concurrent promises. Set to 0 for unlimited.
     * @param  callable(int $done, int $total): void  $onProgress  Optional progress callback.
     * @return Promise\PromiseInterface<array<TOut>> Promise resolving to array of results.
     */
    public static function map(
        array $items,
        callable $callback,
        int $concurrency = 0,
        ?callable $onProgress = null
    ): Promise\PromiseInterface {
        if (empty($items)) {
            return self::resolve([]);
        }

        if ($concurrency <= 0) {
            // Map all items concurrently
            $promises = array_map($callback, $items);

            // If progress callback is provided, wrap each promise to report progress
            if ($onProgress !== null) {
                $total = count($promises);
                $completed = 0;

                $promises = array_map(function ($promise) use (&$completed, $total, $onProgress) {
                    return $promise->then(function ($result) use (&$completed, $total, $onProgress) {
                        $completed++;
                        $onProgress($completed, $total);

                        return $result;
                    });
                }, $promises);
            }

            return self::all($promises);
        }

        // Process with limited concurrency
        $results = [];
        $pending = 0;
        $position = 0;
        $completed = 0;
        $itemCount = count($items);
        $deferred = new Deferred;

        $processNext = function () use (
            &$pending,
            &$position,
            &$completed,
            &$results,
            $items,
            $callback,
            $itemCount,
            $deferred,
            &$processNext,
            $concurrency,
            $onProgress
        ): void {
            if ($position >= $itemCount && $pending === 0) {
                ksort($results);
                $deferred->resolve($results);

                return;
            }

            while ($pending < $concurrency && $position < $itemCount) {
                $idx = $position++;
                $item = $items[$idx];
                $pending++;

                $promise = $callback($item);

                $promise->then(
                    function ($result) use (
                        $idx,
                        &$results,
                        &$pending,
                        &$completed,
                        $itemCount,
                        $processNext,
                        $onProgress
                    ): void {
                        $results[$idx] = $result;
                        $pending--;
                        $completed++;

                        if ($onProgress !== null) {
                            $onProgress($completed, $itemCount);
                        }

                        $processNext();
                    },
                    function ($reason) use ($deferred): void {
                        $deferred->reject($reason);
                    }
                );
            }
        };

        LoopManager::nextTick($processNext);

        return $deferred->promise();
    }

    /**
     * Process items in batches rather than one at a time.
     *
     * @template TIn
     * @template TOut
     *
     * @param  array<TIn>  $items  Array of items to process.
     * @param  callable(array<TIn>): Promise\PromiseInterface<array<TOut>>  $batchCallback
     *                                                                                      Callback that processes a batch of items
     * @param  int  $batchSize  Size of each batch
     * @param  int  $concurrency  Maximum number of concurrent batches
     * @return Promise\PromiseInterface<array<TOut>> Promise resolving to array of results.
     */
    public static function batch(
        array $items,
        callable $batchCallback,
        int $batchSize = 10,
        int $concurrency = 1
    ): Promise\PromiseInterface {
        if (empty($items)) {
            return self::resolve([]);
        }

        // Split into batches
        $batches = array_chunk($items, $batchSize);

        // Process each batch with the map function
        return self::map($batches, $batchCallback, $concurrency)
            ->then(function (array $results) {
                // Flatten the results from all batches
                if (empty($results)) {
                    return [];
                }

                return array_merge(...$results);
            });
    }

    /**
     * Retry a promise-returning function multiple times until success or max attempts reached.
     *
     * @template T
     *
     * @param  callable(): Promise\PromiseInterface<T>  $factory  Function that returns a promise
     * @param  int  $maxAttempts  Maximum number of retry attempts
     * @param  callable(int $attempt, \Throwable $error): float|null  $backoffStrategy
     *                                                                                  Function that returns delay in seconds or null to stop retrying
     * @return Promise\PromiseInterface<T> A promise that resolves when the operation succeeds
     */
    public static function retry(
        callable $factory,
        int $maxAttempts = 3,
        ?callable $backoffStrategy = null
    ): Promise\PromiseInterface {
        $backoffStrategy ??= fn (int $attempt, \Throwable $error): float => min(pow(2, $attempt - 1) * 0.1, 5.0); // Exponential backoff with 5s cap

        $attempt = 0;
        $failures = [];

        $deferred = new Deferred;

        $tryOperation = function () use (
            &$attempt,
            &$failures,
            $factory,
            $maxAttempts,
            $backoffStrategy,
            $deferred,
            &$tryOperation
        ): void {
            $attempt++;

            try {
                $factory()
                    ->then(
                        function ($result) use ($deferred): void {
                            $deferred->resolve($result);
                        },
                        function (\Throwable $error) use (
                            &$attempt,
                            &$failures,
                            $maxAttempts,
                            $backoffStrategy,
                            $deferred,
                            $tryOperation
                        ): void {
                            $failures[] = $error;

                            if ($attempt >= $maxAttempts) {
                                $deferred->reject(
                                    new RetryException(
                                        $attempt,
                                        $failures,
                                        "All {$maxAttempts} retry attempts failed",
                                        0,
                                        $error
                                    )
                                );

                                return;
                            }

                            $delay = $backoffStrategy($attempt, $error);

                            if ($delay === null) {
                                $deferred->reject($error);

                                return;
                            }

                            LoopManager::delay($delay, $tryOperation);
                        }
                    );
            } catch (\Throwable $e) {
                $failures[] = $e;

                if ($attempt >= $maxAttempts) {
                    $deferred->reject(
                        new RetryException(
                            $attempt,
                            $failures,
                            "All {$maxAttempts} retry attempts failed",
                            0,
                            $e
                        )
                    );

                    return;
                }

                $delay = $backoffStrategy($attempt, $e);

                if ($delay === null) {
                    $deferred->reject($e);

                    return;
                }

                LoopManager::delay($delay, $tryOperation);
            }
        };

        LoopManager::nextTick($tryOperation);

        return $deferred->promise();
    }

    /**
     * Execute an array of callables with limited concurrency.
     *
     * @template T
     *
     * @param  array<callable(): Promise\PromiseInterface<T>>  $callables  Functions that return promises
     * @param  int  $concurrency  Maximum number of concurrent executions
     * @param  callable(int $done, int $total): void|null  $onProgress  Progress callback
     * @return Promise\PromiseInterface<array<T>> Promise resolving to array of results
     */
    public static function pool(
        array $callables,
        int $concurrency = 5,
        ?callable $onProgress = null
    ): Promise\PromiseInterface {
        return PromisePool::create($callables, $concurrency, $onProgress);
    }

    /**
     * Execute promises in sequence, passing the result of each to the next.
     *
     * @template T
     *
     * @param  array<callable(mixed): Promise\PromiseInterface<T>>  $callables
     *                                                                          Array of callables that accept previous result and return a promise
     * @param  mixed  $initialValue  Initial value to pass to the first callable
     * @return Promise\PromiseInterface<T> Promise resolving to the final result
     */
    public static function waterfall(array $callables, mixed $initialValue = null): Promise\PromiseInterface
    {
        return array_reduce(
            $callables,
            fn (Promise\PromiseInterface $carry, callable $callable) => $carry->then($callable),
            self::resolve($initialValue)
        );
    }

    /**
     * Create a cancellable promise.
     *
     * @template T
     *
     * @param  Promise\PromiseInterface<T>  $promise  The promise to make cancellable
     * @param  callable(): void  $onCancel  Function to call on cancellation
     * @return CancellablePromise<T> A cancellable promise wrapper
     */
    public static function cancellable(
        Promise\PromiseInterface $promise,
        callable $onCancel
    ): CancellablePromise {
        return new CancellablePromise($promise, $onCancel);
    }

    /**
     * Add better error context to a promise.
     *
     * @template T
     *
     * @param  Promise\PromiseInterface<T>  $promise  The promise to enhance
     * @param  string  $context  Additional context for errors
     * @return Promise\PromiseInterface<T> Enhanced promise with better error handling
     */
    public static function withErrorContext(
        Promise\PromiseInterface $promise,
        string $context
    ): Promise\PromiseInterface {
        return $promise->then(
            fn ($result) => $result,
            function (\Throwable $error) use ($context) {
                if ($error instanceof AsyncException) {
                    throw $error;
                }

                $wrappedError = new AsyncException(
                    "{$context}: {$error->getMessage()}",
                    $error->getCode(),
                    $error
                );

                return self::reject($wrappedError);
            }
        );
    }

    /**
     * Create a rate-limited version of an async function.
     *
     * @template T
     *
     * @param callable(...mixed): Promise\PromiseInterface<T> $fn Function to rate limit
     * @param  int  $maxCalls  Maximum calls per time period
     * @param  float  $period  Time period in seconds
     * @return callable(...mixed): Promise\PromiseInterface<T> Rate-limited function
     */
    public static function rateLimit(
        callable $fn,
        int $maxCalls,
        float $period
    ): callable {
        $rateLimiter = RateLimiter::create($maxCalls, $period);

        return $rateLimiter->limit($fn);
    }
}
