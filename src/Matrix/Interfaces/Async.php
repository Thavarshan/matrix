<?php

declare(strict_types=1);

namespace Matrix\Interfaces;

use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/**
 * Interface for the Async class.
 * Provides a contract for async operations with promises.
 */
interface Async
{
    /**
     * Get the process-wide event loop instance.
     *
     * @return LoopInterface The event loop instance.
     */
    public static function loop(): LoopInterface;

    /**
     * Run a callable "coroutine-style".
     *
     * @template T
     *
     * @param  callable(): (T|PromiseInterface<T>)  $callable  The callable to execute.
     * @return PromiseInterface<T> A promise that resolves with the callable's result.
     */
    public static function coro(callable $callable): PromiseInterface;

    /**
     * Block the current fibre / CLI process until the promise settles.
     *
     * @template T
     *
     * @param  PromiseInterface<T>  $promise  The promise to await.
     * @param  float|null  $timeout  Optional timeout in seconds.
     * @return T The resolved value of the promise.
     *
     * @throws \RuntimeException If the promise times out.
     * @throws \Throwable If the promise is rejected.
     */
    public static function await(PromiseInterface $promise, ?float $timeout = null);

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
    public static function map(
        array $items,
        callable $callback,
        int $concurrency = 0
    ): PromiseInterface;

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
    public static function batch(
        array $items,
        callable $batchCallback,
        int $batchSize,
        int $concurrency
    ): PromiseInterface;

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
    public static function retry(
        callable $factory,
        int $maxAttempts,
        ?callable $backoffStrategy
    ): PromiseInterface;

    /**
     * Create a promise that resolves after a specified delay.
     *
     * @template T
     *
     * @param  float  $seconds  Delay in seconds.
     * @param  T  $value  Value to resolve with (optional).
     * @return PromiseInterface<T> A promise that resolves after the delay.
     */
    public static function delay(float $seconds, mixed $value): PromiseInterface;

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
    public static function timeout(
        PromiseInterface $promise,
        float $seconds,
        string $message
    ): PromiseInterface;
}
