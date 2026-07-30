<?php

declare(strict_types=1);

namespace Matrix\Support;

use Matrix\Async;
use Matrix\Events\EventDispatcher;
use Matrix\Metrics\MetricsCollector;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

function getLoop(): LoopInterface
{
    return Async::loop();
}

/** @return PromiseInterface<mixed> */
function async(callable $callable): PromiseInterface
{
    return Async::coro($callable);
}

/** @param PromiseInterface<mixed> $promise */
function await(PromiseInterface $promise, ?float $timeout = null): mixed
{
    return Async::await($promise, $timeout);
}

/** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
function all(iterable $promisesOrValues): PromiseInterface
{
    return Async::all($promisesOrValues);
}

/** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
function race(iterable $promisesOrValues): PromiseInterface
{
    return Async::race($promisesOrValues);
}

/** @param iterable<array-key, mixed> $promisesOrValues @return PromiseInterface<mixed> */
function any(iterable $promisesOrValues): PromiseInterface
{
    return Async::any($promisesOrValues);
}

/** @return PromiseInterface<mixed> */
function reject(\Throwable $reason): PromiseInterface
{
    return Async::reject($reason);
}

/** @return PromiseInterface<mixed> */
function resolve(mixed $value): PromiseInterface
{
    return Async::resolve($value);
}

/** @return PromiseInterface<mixed> */
function delay(float $seconds, mixed $value = null): PromiseInterface
{
    return Async::delay($seconds, $value);
}

/** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
function timeout(PromiseInterface $promise, float $seconds, string $message = 'Operation timed out'): PromiseInterface
{
    return Async::timeout($promise, $seconds, $message);
}

/** @param iterable<array-key, mixed> $items @return PromiseInterface<mixed> */
function map(iterable $items, callable $callback, int $concurrency = 0, ?callable $onProgress = null): PromiseInterface
{
    return Async::map($items, $callback, $concurrency, $onProgress);
}

/** @param iterable<array-key, mixed> $items @return PromiseInterface<mixed> */
function batch(iterable $items, callable $batchCallback, int $batchSize = 10, int $concurrency = 1): PromiseInterface
{
    return Async::batch($items, $batchCallback, $batchSize, $concurrency);
}

/** @return PromiseInterface<mixed> */
function retry(callable $factory, int $maxAttempts = 3, ?callable $backoffStrategy = null): PromiseInterface
{
    return Async::retry($factory, $maxAttempts, $backoffStrategy);
}

/** @param iterable<array-key, callable(): mixed> $callables @return PromiseInterface<mixed> */
function pool(iterable $callables, int $concurrency = 5, ?callable $onProgress = null): PromiseInterface
{
    return Async::pool($callables, $concurrency, $onProgress);
}

/** @param iterable<callable(mixed): mixed> $callables @return PromiseInterface<mixed> */
function waterfall(iterable $callables, mixed $initialValue = null): PromiseInterface
{
    return Async::waterfall($callables, $initialValue);
}

/** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
function cancellable(PromiseInterface $promise, callable $onCancel): PromiseInterface
{
    return Async::cancellable($promise, $onCancel);
}

/** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
function withErrorContext(PromiseInterface $promise, string $context): PromiseInterface
{
    return Async::withErrorContext($promise, $context);
}

function rateLimit(callable $fn, int $maxCalls, float $period): callable
{
    return Async::rateLimit($fn, $maxCalls, $period);
}

function eventDispatcher(): EventDispatcher
{
    return Async::eventDispatcher();
}

function metricsCollector(): MetricsCollector
{
    return Async::metricsCollector();
}

function listen(string $eventName, callable $listener): void
{
    Async::eventDispatcher()->listen($eventName, $listener);
}

/** @return array<string, mixed> */
function getMetrics(): array
{
    return Async::metricsCollector()->getMetrics();
}
