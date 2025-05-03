<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * Internal event loop instance.
 *
 * @return LoopInterface The ReactPHP event loop instance
 */
function getLoop(): LoopInterface
{
    static $loop = null;

    if ($loop === null) {
        $loop = Loop::get();
    }

    return $loop;
}

/**
 * Wraps a callable into an async function that returns a promise.
 *
 * @param  callable  $callable  The function to execute asynchronously
 * @return PromiseInterface<mixed> A promise that resolves with the callable's result
 */
function async(callable $callable): PromiseInterface
{
    return new Promise(function ($resolve, $reject) use ($callable) {
        getLoop()->futureTick(function () use ($callable, $resolve, $reject) {
            try {
                $result = $callable();

                if ($result instanceof PromiseInterface) {
                    $result
                        ->then(function ($value) use ($resolve) {
                            $resolve($value);
                            // Don't stop the loop here as it might be handling other promises
                        })
                        ->otherwise(function ($reason) use ($reject) {
                            $reject($reason);
                            // Don't stop the loop here as it might be handling other promises
                        });
                } else {
                    $resolve($result);
                    // Don't stop the loop here as it might be handling other promises
                }
            } catch (\Throwable $e) {
                $reject($e);
                // Don't stop the loop here as it might be handling other promises
            }
        });
    });
}

/**
 * Awaits the resolution of a promise and returns the result.
 *
 * @param  PromiseInterface  $promise  The promise to await
 * @return mixed The resolved value of the promise (could be any type)
 *
 * @throws \Throwable If the promise is rejected with an exception
 */
function await(PromiseInterface $promise): mixed
{
    $result = null;
    $exception = null;
    $resolved = false;

    $promise->then(
        function ($value) use (&$result, &$resolved) {
            $result = $value;
            $resolved = true;
            getLoop()->stop();
        },
        function ($reason) use (&$exception, &$resolved) {
            $exception = $reason;
            $resolved = true;
            getLoop()->stop();
        }
    );

    // Run the event loop until the promise is resolved or rejected
    if (! $resolved) {
        getLoop()->run();
    }

    if ($exception instanceof \Throwable) {
        throw $exception;
    }

    return $result;
}

/**
 * Runs multiple promises concurrently and returns a promise that resolves
 * with an array of all results.
 *
 * @param  array<PromiseInterface>  $promises  An array of promises to run concurrently
 * @return PromiseInterface<array> A promise that resolves with an array of results
 */
function all(array $promises): PromiseInterface
{
    return \React\Promise\all($promises);
}

/**
 * Runs multiple promises concurrently and returns a promise that resolves
 * with the result of the first resolved promise.
 *
 * @param  array<PromiseInterface>  $promises  An array of promises to run concurrently
 * @return PromiseInterface<mixed> A promise that resolves with the first result
 */
function race(array $promises): PromiseInterface
{
    return \React\Promise\race($promises);
}

/**
 * Runs multiple promises concurrently and returns a promise that resolves
 * when any promise resolves or all promises reject.
 *
 * @param  array<PromiseInterface>  $promises  An array of promises to run concurrently
 * @return PromiseInterface<mixed> A promise that resolves with the first successful result
 *                                 or rejects with an array of all rejection reasons
 */
function any(array $promises): PromiseInterface
{
    return \React\Promise\any($promises);
}
