<?php

declare(strict_types=1);

use Matrix\AsyncPromise;
use React\Promise\Deferred;

uses()->group('async-promise');

it('calls the then handler when resolved', function () {
    $deferred = new Deferred;
    $promise = new AsyncPromise($deferred->promise());

    $resultValue = null;
    $promise->then(function ($value) use (&$resultValue) {
        $resultValue = $value;
    });

    // Resolve the underlying promise
    $deferred->resolve('test result');

    // Since we're in a synchronous test, manually tick the event loop and promise microtasks.
    // React promises do not strictly require an event loop tick to resolve,
    // but if you integrate with an event loop in a real scenario, you may need Loop::run().
    // For these tests, React promises resolve synchronously upon calling resolve().

    expect($resultValue)->toBe('test result');
});

it('calls the catch handler when rejected', function () {
    $deferred = new Deferred;
    $promise = new AsyncPromise($deferred->promise());

    $errorValue = null;
    $promise->catch(function ($error) use (&$errorValue) {
        $errorValue = $error;
    });

    $deferred->reject(new RuntimeException('Something went wrong'));

    expect($errorValue)->toBeInstanceOf(RuntimeException::class);
    expect($errorValue)->getMessage()->toBe('Something went wrong');
});

it('can chain multiple then calls', function () {
    $deferred = new Deferred;
    $promise = new AsyncPromise($deferred->promise());

    $values = [];
    $promise
        ->then(function ($val) use (&$values) {
            $values[] = $val . '1';
        })
        ->then(function () use (&$values) {
            $values[] = '2';
        })
        ->then(function () use (&$values) {
            $values[] = '3';
        });

    $deferred->resolve('value');

    expect($values)->toEqual(['value1', '2', '3']);
});

it('can chain catch calls', function () {
    $deferred = new Deferred;
    $promise = new AsyncPromise($deferred->promise());

    $errors = [];
    $promise
        ->catch(function ($err) use (&$errors) {
            $errors[] = 'first handler: ' . $err->getMessage();
        })
        ->catch(function ($err) use (&$errors) {
            $errors[] = 'second handler: ' . $err->getMessage();
        });

    $deferred->reject(new RuntimeException('chain error'));

    // Note: With React promises, each handler transforms the promise.
    // Once a promise is handled by a then/catch, subsequent handlers see the transformed value.
    // By default, a catch handler returning nothing doesn't re-throw.
    // This test checks that both handlers run. However, note that by default,
    // `otherwise()` (catch) in React promises will not call subsequent handlers
    // if the error is "handled". If you need multiple handlers to run, you may need
    // to re-throw or chain differently. For demonstration, we assume the handlers chain as expected.
    //
    // In React promises, once an error is handled by `otherwise()`, it does not propagate to subsequent handlers.
    // So in reality, only the first catch handler would handle the error. The second won't see the original error.
    //
    // If we want both to be called in a chain, we must re-throw the error in the first handler:
    // Update the test to reflect realistic behavior:

    // Adjusted example:
    $errors = [];
    $promise = new AsyncPromise($deferred->promise());
    $promise
        ->catch(function ($err) use (&$errors) {
            $errors[] = 'first handler: ' . $err->getMessage();

            // Re-throw to allow the next handler to catch it
            throw $err;
        })
        ->catch(function ($err) use (&$errors) {
            $errors[] = 'second handler: ' . $err->getMessage();
        });

    $deferred->reject(new RuntimeException('chain error'));
    expect($errors)->toEqual(['first handler: chain error', 'second handler: chain error']);
});

it('returns $this for chaining', function () {
    $deferred = new Deferred;
    $promise = new AsyncPromise($deferred->promise());

    $chained = $promise->then(fn () => null)->catch(fn () => null);

    expect($chained)->toBe($promise);
});
