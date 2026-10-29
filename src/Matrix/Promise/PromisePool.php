<?php

declare(strict_types=1);

namespace Matrix\Promise;

use Matrix\Support\Lifecycle;
use React\Promise\PromiseInterface;

/** @internal A keyed, fail-fast pool of promise-returning tasks. */
final class PromisePool
{
    /**
     * @template T
     *
     * @param  iterable<array-key, callable(): (T|PromiseInterface<T>)>  $tasks
     * @param  (callable(int, int|null): void)|null  $progressCallback
     * @return PromiseInterface<array<array-key, T>>
     */
    public static function create(iterable $tasks, int $concurrency = 5, ?callable $progressCallback = null): PromiseInterface
    {
        if ($concurrency < 1) {
            throw new \InvalidArgumentException('Pool concurrency must be positive.');
        }

        $promise = TaskScheduler::run($tasks, self::invokeTask(...), $concurrency, $progressCallback);

        return Lifecycle::track($promise, 'pool');
    }

    /**
     * @template TResult
     *
     * @param  callable(): (TResult|PromiseInterface<TResult>)  $task
     * @return TResult|PromiseInterface<TResult>
     */
    private static function invokeTask(callable $task): mixed
    {
        return $task();
    }
}
