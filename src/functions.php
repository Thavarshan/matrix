<?php

declare(strict_types=1);

use React\EventLoop\Loop;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

/**
 * Internal event loop instance.
 */
function getLoop(): object
{
    static $loop = null;
    if ($loop === null) {
        $loop = Loop::get();
    }

    return $loop;
}

/**
 * Wraps a callable into an async function that returns a promise.
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
                            getLoop()->stop();
                        })
                        ->otherwise(function ($reason) use ($reject) {
                            $reject($reason);
                            getLoop()->stop();
                        });
                } else {
                    $resolve($result);
                    getLoop()->stop();
                }
            } catch (\Throwable $e) {
                $reject($e);
                getLoop()->stop();
            }
        });
    });
}

/**
 * Awaits the resolution of a promise and returns the result.
 *
 * @throws \Throwable
 */
function await(PromiseInterface $promise): mixed
{
    $result = null;
    $exception = null;

    $promise->then(
        function ($value) use (&$result) {
            $result = $value;
            getLoop()->stop();
        },
        function ($reason) use (&$exception) {
            $exception = $reason;
            getLoop()->stop();
        }
    );

    // Run the event loop until the promise is resolved or rejected
    getLoop()->run();

    if ($exception instanceof \Throwable) {
        throw $exception;
    }

    return $result;
}
