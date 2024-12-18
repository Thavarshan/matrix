<?php

declare(strict_types=1);

use Matrix\AsyncProcessManager;

uses()->group('manager', 'async');

it('resolves with the child result when the callable succeeds', function () {
    $manager = new AsyncProcessManager;
    $promise = $manager->fork(function () {
        usleep(50000);

        return 'Child Result';
    });

    $result = awaitPromise($promise);
    expect($result)->toBe('Child Result');
});

it('rejects with an exception when the callable throws', function () {
    $manager = new AsyncProcessManager;
    $promise = $manager->fork(function () {
        throw new RuntimeException('Child error occurred');
    });

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Child error occurred');
    awaitPromise($promise);
});

it('rejects when no data is received from the child', function () {
    $manager = new AsyncProcessManager;
    $promise = $manager->fork(function () {
        // Child exits immediately without writing data
        exit(0);
    });

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('No data received from child process.');
    awaitPromise($promise);
});

it('includes file, line, and trace information when an error occurs', function () {
    $manager = new AsyncProcessManager;
    $promise = $manager->fork(function () {
        throw new \InvalidArgumentException('Test error');
    });

    try {
        awaitPromise($promise);
        $this->fail('Promise should have rejected');
    } catch (\InvalidArgumentException $e) {
        expect($e->getMessage())->toMatch('/Test error in .* on line \d+/');
        expect($e->getMessage())->toMatch('/Trace:/');
    }
});
