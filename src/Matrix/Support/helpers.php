<?php

declare(strict_types=1);

namespace Matrix\Support;

use Matrix\Async;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

if (! function_exists('getLoop')) {
    /**
     * Internal event loop instance.
     *
     * @return LoopInterface The ReactPHP event loop instance
     */
    function getLoop(): LoopInterface
    {
        return Async::loop();
    }
}

if (! function_exists('async')) {
    /**
     * Wraps a callable into an async function that returns a promise.
     * This is an alias for Async::coro()
     *
     * @template T
     *
     * @param  callable(): (T|PromiseInterface<T>)  $callable  The function to execute asynchronously
     * @return PromiseInterface<T> A promise that resolves with the callable's result
     */
    function async(callable $callable): PromiseInterface
    {
        return Async::coro($callable);
    }
}

if (! function_exists('await')) {
    /**
     * Awaits the resolution of a promise and returns the result.
     *
     * @template T
     *
     * @param  PromiseInterface<T>  $promise  The promise to await
     * @param  float|null  $timeout  Optional timeout in seconds
     * @return T The resolved value of the promise
     *
     * @throws \Throwable If the promise is rejected with an exception
     */
    function await(PromiseInterface $promise, ?float $timeout = null)
    {
        return Async::await($promise, $timeout);
    }
}

if (! function_exists('all')) {
    /**
     * Runs multiple promises concurrently and returns a promise that resolves
     * with an array of all results.
     *
     * @template T
     *
     * @param  array<PromiseInterface<T>>  $promises  An array of promises to run concurrently
     * @return PromiseInterface<array<T>> A promise that resolves with an array of results
     */
    function all(array $promises): PromiseInterface
    {
        return Async::all($promises);
    }
}

if (! function_exists('race')) {
    /**
     * Runs multiple promises concurrently and returns a promise that resolves
     * with the result of the first resolved promise.
     *
     * @template T
     *
     * @param  array<PromiseInterface<T>>  $promises  An array of promises to run concurrently
     * @return PromiseInterface<T> A promise that resolves with the first result
     */
    function race(array $promises): PromiseInterface
    {
        return Async::race($promises);
    }
}

if (! function_exists('any')) {
    /**
     * Runs multiple promises concurrently and returns a promise that resolves
     * when any promise resolves or all promises reject.
     *
     * @template T
     *
     * @param  array<PromiseInterface<T>>  $promises  An array of promises to run concurrently
     * @return PromiseInterface<T> A promise that resolves with the first successful result
     */
    function any(array $promises): PromiseInterface
    {
        return Async::any($promises);
    }
}

if (! function_exists('reject')) {
    /**
     * Reject a promise with a reason.
     *
     * @template T
     *
     * @param  mixed  $reason  The reason for rejection.
     * @return PromiseInterface<T> A promise rejected with the given reason.
     */
    function reject($reason): PromiseInterface
    {
        return Async::reject($reason);
    }
}

if (! function_exists('resolve')) {
    /**
     * Resolves a value into a promise.
     *
     * @template T
     *
     * @param  T  $value  The value to resolve
     * @return PromiseInterface<T> A promise that resolves with the value
     */
    function resolve($value): PromiseInterface
    {
        return Async::resolve($value);
    }
}

if (! function_exists('delay')) {
    /**
     * Create a promise that resolves after a specified delay.
     *
     * @template T
     *
     * @param  float  $seconds  Delay in seconds.
     * @param  T  $value  Value to resolve with (optional).
     * @return PromiseInterface<T> A promise that resolves after the delay.
     */
    function delay(float $seconds, mixed $value = null): PromiseInterface
    {
        return Async::delay($seconds, $value);
    }
}

if (! function_exists('timeout')) {
    /**
     * Create a promise that times out after a specified period.
     *
     * @template T
     *
     * @param  PromiseInterface<T>  $promise  The promise to add a timeout to.
     * @param  float  $seconds  Timeout in seconds.
     * @param  string  $message  Custom timeout message.
     * @return PromiseInterface<T> A promise that rejects with a timeout error if the original promise doesn't settle in time.
     */
    function timeout(
        PromiseInterface $promise,
        float $seconds,
        string $message = 'Operation timed out'
    ): PromiseInterface {
        return Async::timeout($promise, $seconds, $message);
    }
}

if (! function_exists('map')) {
    /**
     * Map an array of items through an async function.
     *
     * @template TIn
     * @template TOut
     *
     * @param  array<TIn>  $items  Array of items to process.
     * @param  callable(TIn): PromiseInterface<TOut>  $callback  Async callback function.
     * @param  int  $concurrency  Maximum number of concurrent promises. Set to 0 for unlimited.
     * @return PromiseInterface<array<TOut>> Promise resolving to array of results.
     */
    function map(
        array $items,
        callable $callback,
        int $concurrency = 0
    ): PromiseInterface {
        return Async::map($items, $callback, $concurrency);
    }
}

if (! function_exists('batch')) {
    /**
     * Process items in batches rather than one at a time.
     *
     * @template TIn
     * @template TOut
     *
     * @param  array<TIn>  $items  Array of items to process.
     * @param  callable(array<TIn>): PromiseInterface<array<TOut>>  $batchCallback
     *                                                                              Callback that processes a batch of items
     * @param  int  $batchSize  Size of each batch
     * @param  int  $concurrency  Maximum number of concurrent batches
     * @return PromiseInterface<array<TOut>> Promise resolving to array of results.
     */
    function batch(
        array $items,
        callable $batchCallback,
        int $batchSize = 10,
        int $concurrency = 1
    ): PromiseInterface {
        return Async::batch($items, $batchCallback, $batchSize, $concurrency);
    }
}

if (! function_exists('retry')) {
    /**
     * Retry a promise-returning function multiple times until success or max attempts reached.
     *
     * @template T
     *
     * @param  callable(): PromiseInterface<T>  $factory  Function that returns a promise
     * @param  int  $maxAttempts  Maximum number of retry attempts
     * @param  callable(int $attempt, \Throwable $error): float|null  $backoffStrategy
     *                                                                                  Function that returns delay in seconds or null to stop retrying
     * @return PromiseInterface<T> A promise that resolves when the operation succeeds
     */
    function retry(
        callable $factory,
        int $maxAttempts = 3,
        ?callable $backoffStrategy = null
    ): PromiseInterface {
        return Async::retry($factory, $maxAttempts, $backoffStrategy);
    }
}
