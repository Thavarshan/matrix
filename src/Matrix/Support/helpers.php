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

/**
 * @template T
 *
 * @param  callable(): (T|PromiseInterface<T>)  $callable
 * @return PromiseInterface<T>
 */
function async(callable $callable): PromiseInterface
{
    return Async::coro($callable);
}

/**
 * @template T
 *
 * @param  PromiseInterface<T>  $promise
 * @return T|null
 */
function await(PromiseInterface $promise, ?float $timeout = null): mixed
{
    return Async::await($promise, $timeout);
}

/**
 * @template T
 *
 * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
 * @return PromiseInterface<array<array-key, T>>
 */
function all(iterable $promises): PromiseInterface
{
    return Async::all($promises);
}

/**
 * @template T
 *
 * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
 * @return PromiseInterface<T>
 */
function race(iterable $promises): PromiseInterface
{
    return Async::race($promises);
}

/**
 * @template T
 *
 * @param  iterable<array-key, PromiseInterface<T>|T>  $promises
 * @return PromiseInterface<T>
 */
function any(iterable $promises): PromiseInterface
{
    return Async::any($promises);
}

/** @return PromiseInterface<never> */
function reject(\Throwable $reason): PromiseInterface
{
    return Async::reject($reason);
}

/**
 * @template T
 *
 * @param  T|PromiseInterface<T>  $value
 * @return PromiseInterface<T>
 */
function resolve(mixed $value): PromiseInterface
{
    return Async::resolve($value);
}

/**
 * @template T
 *
 * @param  T|null  $value
 * @return PromiseInterface<T|null>
 */
function delay(float $seconds, mixed $value = null): PromiseInterface
{
    return Async::delay($seconds, $value);
}

/**
 * @template T
 *
 * @param  PromiseInterface<T>  $promise
 * @return PromiseInterface<T>
 */
function timeout(PromiseInterface $promise, float $seconds, string $message = 'Operation timed out'): PromiseInterface
{
    return Async::timeout($promise, $seconds, $message);
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
function map(iterable $items, callable $callback, int $concurrency = 0, ?callable $onProgress = null): PromiseInterface
{
    return Async::map($items, $callback, $concurrency, $onProgress);
}

/**
 * @template T
 * @template U
 *
 * @param  iterable<array-key, T>  $items
 * @param  callable(list<T>): (iterable<array-key, U>|PromiseInterface<iterable<array-key, U>>)  $batchCallback
 * @return PromiseInterface<list<U>>
 */
function batch(iterable $items, callable $batchCallback, int $batchSize = 10, int $concurrency = 1): PromiseInterface
{
    return Async::batch($items, $batchCallback, $batchSize, $concurrency);
}

/**
 * @template T
 *
 * @param  callable(): (T|PromiseInterface<T>)  $factory
 * @param  (callable(int, \Throwable): mixed)|null  $backoffStrategy
 * @return PromiseInterface<T>
 */
function retry(callable $factory, int $maxAttempts = 3, ?callable $backoffStrategy = null): PromiseInterface
{
    return Async::retry($factory, $maxAttempts, $backoffStrategy);
}

/**
 * @template T
 *
 * @param  iterable<array-key, callable(): (T|PromiseInterface<T>)>  $callables
 * @param  (callable(int, int|null): void)|null  $onProgress
 * @return PromiseInterface<array<array-key, T>>
 */
function pool(iterable $callables, int $concurrency = 5, ?callable $onProgress = null): PromiseInterface
{
    return Async::pool($callables, $concurrency, $onProgress);
}

/**
 * @template T
 *
 * @param  iterable<callable(T|null): (T|PromiseInterface<T>)>  $callables
 * @param  T|null  $initialValue
 * @return PromiseInterface<T|null>
 */
function waterfall(iterable $callables, mixed $initialValue = null): PromiseInterface
{
    return Async::waterfall($callables, $initialValue);
}

/**
 * @template T
 *
 * @param  PromiseInterface<T>  $promise
 * @return PromiseInterface<T>
 */
function cancellable(PromiseInterface $promise, callable $onCancel): PromiseInterface
{
    return Async::cancellable($promise, $onCancel);
}

/**
 * @template T
 *
 * @param  PromiseInterface<T>  $promise
 * @return PromiseInterface<T>
 */
function withErrorContext(PromiseInterface $promise, string $context): PromiseInterface
{
    return Async::withErrorContext($promise, $context);
}

/**
 * @return callable(mixed...): PromiseInterface<mixed>
 */
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
