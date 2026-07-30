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

    /** @return PromiseInterface<mixed> */
    public static function coro(callable $callable): PromiseInterface;

    /** @param PromiseInterface<mixed> $promise */
    public static function await(PromiseInterface $promise, ?float $timeout = null): mixed;

    /** @return PromiseInterface<mixed> */
    public static function delay(float $seconds, mixed $value = null): PromiseInterface;

    /** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
    public static function timeout(PromiseInterface $promise, float $seconds, string $message = 'Operation timed out'): PromiseInterface;

    /** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
    public static function all(iterable $promisesOrValues): PromiseInterface;

    /** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
    public static function any(iterable $promisesOrValues): PromiseInterface;

    /** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
    public static function race(iterable $promisesOrValues): PromiseInterface;

    /** @return PromiseInterface<mixed> */
    public static function resolve(mixed $value): PromiseInterface;

    /** @return PromiseInterface<mixed> */
    public static function reject(\Throwable $reason): PromiseInterface;

    /** @param iterable<array-key, mixed> $items @return PromiseInterface<mixed> */
    public static function map(iterable $items, callable $callback, int $concurrency = 0, ?callable $onProgress = null): PromiseInterface;

    /** @param iterable<array-key, mixed> $items @return PromiseInterface<mixed> */
    public static function batch(iterable $items, callable $batchCallback, int $batchSize = 10, int $concurrency = 1): PromiseInterface;

    /** @return PromiseInterface<mixed> */
    public static function retry(callable $factory, int $maxAttempts = 3, ?callable $backoffStrategy = null): PromiseInterface;

    /** @param iterable<array-key, callable(): mixed> $callables @return PromiseInterface<mixed> */
    public static function pool(iterable $callables, int $concurrency = 5, ?callable $onProgress = null): PromiseInterface;

    /** @param iterable<callable(mixed): mixed> $callables @return PromiseInterface<mixed> */
    public static function waterfall(iterable $callables, mixed $initialValue = null): PromiseInterface;

    /** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
    public static function cancellable(PromiseInterface $promise, callable $onCancel): PromiseInterface;

    /** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
    public static function withErrorContext(PromiseInterface $promise, string $context): PromiseInterface;

    public static function rateLimit(callable $fn, int $maxCalls, float $period): callable;
}
