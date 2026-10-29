<?php

declare(strict_types=1);

namespace Matrix\Interfaces;

use Matrix\Events\EventDispatcher;
use Matrix\Metrics\MetricsCollector;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

/** Contract for Matrix's static async façade. */
interface Async
{
    public static function loop(): LoopInterface;

    public static function eventDispatcher(): EventDispatcher;

    public static function metricsCollector(): MetricsCollector;

    /**
     * @template T
     *
     * @param  callable(): (T|PromiseInterface<T>)  $callable
     * @return PromiseInterface<T>
     */
    public static function coro(callable $callable): PromiseInterface;

    /**
     * @template T
     *
     * @param  PromiseInterface<T>  $promise
     * @return T
     */
    public static function await(PromiseInterface $promise, ?float $timeout = null): mixed;

    /**
     * @template T
     *
     * @param  T|null  $value
     * @return PromiseInterface<T|null>
     */
    public static function delay(float $seconds, mixed $value = null): PromiseInterface;

    /**
     * @template T
     *
     * @param  PromiseInterface<T>  $promise
     * @return PromiseInterface<T>
     */
    public static function timeout(PromiseInterface $promise, float $seconds, string $message = 'Operation timed out'): PromiseInterface;

    /**
     * @template T
     *
     * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
     * @return PromiseInterface<array<array-key, T>>
     */
    public static function all(iterable $promises): PromiseInterface;

    /**
     * @template T
     *
     * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
     * @return PromiseInterface<T>
     */
    public static function any(iterable $promises): PromiseInterface;

    /**
     * @template T
     *
     * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
     * @return PromiseInterface<T>
     */
    public static function race(iterable $promises): PromiseInterface;

    /**
     * @template T
     *
     * @param  T|PromiseInterface<T>  $value
     * @return PromiseInterface<T>
     */
    public static function resolve(mixed $value): PromiseInterface;

    /** @return PromiseInterface<never> */
    public static function reject(\Throwable $reason): PromiseInterface;

    /**
     * @template T
     * @template U
     *
     * @param  iterable<array-key, T>  $items
     * @param  callable(T): (U|PromiseInterface<U>)  $callback
     * @param  (callable(int, int|null): void)|null  $onProgress
     * @return PromiseInterface<array<array-key, U>>
     */
    public static function map(iterable $items, callable $callback, int $concurrency = 0, ?callable $onProgress = null): PromiseInterface;

    /**
     * @template T
     * @template U
     *
     * @param  iterable<array-key, T>  $items
     * @param  callable(list<T>): (iterable<array-key, U>|PromiseInterface<iterable<array-key, U>>)  $batchCallback
     * @return PromiseInterface<list<U>>
     */
    public static function batch(iterable $items, callable $batchCallback, int $batchSize = 10, int $concurrency = 1): PromiseInterface;

    /**
     * @template T
     *
     * @param  callable(): (T|PromiseInterface<T>)  $factory
     * @param  (callable(int, \Throwable): mixed)|null  $backoffStrategy
     * @return PromiseInterface<T>
     */
    public static function retry(callable $factory, int $maxAttempts = 3, ?callable $backoffStrategy = null): PromiseInterface;

    /**
     * @template T
     *
     * @param  iterable<array-key, callable(): (T|PromiseInterface<T>)>  $callables
     * @param  (callable(int, int|null): void)|null  $onProgress
     * @return PromiseInterface<array<array-key, T>>
     */
    public static function pool(iterable $callables, int $concurrency = 5, ?callable $onProgress = null): PromiseInterface;

    /**
     * @template T
     *
     * @param  iterable<callable(T|null): (T|PromiseInterface<T>)>  $callables
     * @param  T|null  $initialValue
     * @return PromiseInterface<T|null>
     */
    public static function waterfall(iterable $callables, mixed $initialValue = null): PromiseInterface;

    /**
     * @template T
     *
     * @param  PromiseInterface<T>  $promise
     * @return PromiseInterface<T>
     */
    public static function cancellable(PromiseInterface $promise, callable $onCancel): PromiseInterface;

    /**
     * @template T
     *
     * @param  PromiseInterface<T>  $promise
     * @return PromiseInterface<T>
     */
    public static function withErrorContext(PromiseInterface $promise, string $context): PromiseInterface;

    /**
     * @return callable(mixed...): PromiseInterface<mixed>
     */
    public static function rateLimit(callable $fn, int $maxCalls, float $period): callable;
}
