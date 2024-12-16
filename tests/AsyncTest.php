<?php

declare(strict_types=1);

use function async; // Make sure async() is globally available

use React\Promise;

uses()->group('integration');

it('runs tasks concurrently, verifying interleaving through timing', function () {
    // Each task prints something, sleeps three times for 500ms each, total ~1.5 seconds per task.
    // If run sequentially, total ~3 seconds. If concurrent, ~1.5 seconds total.

    // Start time
    $start = microtime(true);

    $promise1 = async(function () {
        echo 'A';
        usleep(500000); // 500ms
        echo 'A';
        usleep(500000); // 500ms
        echo 'A';
        usleep(500000); // 500ms

        return 'Finished A';
    });

    $promise2 = async(function () {
        echo 'B';
        usleep(500000); // 500ms
        echo 'B';
        usleep(500000); // 500ms
        echo 'B';
        usleep(500000); // 500ms

        return 'Finished B';
    });

    // Wait for both promises to complete
    // `Promise\all()` returns a promise that resolves when all given promises resolve.
    $bothDone = Promise\all([$promise1->then(fn ($res) => $res), $promise2->then(fn ($res) => $res)]);

    $results = awaitPromise($bothDone);

    $end = microtime(true);
    $elapsed = $end - $start;

    // Check results
    expect($results)->toBe(['Finished A', 'Finished B']);

    // Check timing:
    // If run sequentially: ~3 seconds total.
    // If run concurrently: ~1.5 seconds total (some small variations allowed).
    // We'll check that it's less than 2.5 seconds to be safe.
    expect($elapsed)->toBeLessThan(2.5);
});
