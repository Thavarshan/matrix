<?php

declare(strict_types=1);

it('supports chaining then and catch syntax', function () {
    $func = async(fn () => 'Success');

    $result = null;

    $func->then(
        function (mixed $value) use (&$result) {
            $result = $value;
        }
    )->catch(
        fn (\Throwable $throwable) => $this->fail($throwable->getMessage())
    );

    // Run the event loop to allow promise resolution
    getLoop()->run();

    expect($result)->toBe('Success');
});

it('supports await syntax for promise resolution', function () {
    try {
        $result = await(async(fn () => 'Success'));

        expect($result)->toBe('Success');
    } catch (\Throwable $th) {
        $this->fail('Await threw an unexpected exception: ' . $th->getMessage());
    }
});

it('handles errors gracefully in then/catch syntax', function () {
    $func = async(fn () => throw new \RuntimeException('Test Error'));

    $caughtMessage = null;

    $func->then(
        fn (mixed $value) => $this->fail('Then was called unexpectedly')
    )->catch(
        function (\Throwable $throwable) use (&$caughtMessage) {
            $caughtMessage = $throwable->getMessage();
        }
    );

    // Run the event loop to allow promise rejection to propagate
    getLoop()->run();

    expect($caughtMessage)->toBe('Test Error');
});

it('handles errors gracefully in await syntax', function () {
    try {
        await(async(fn () => throw new \RuntimeException('Test Error')));
        $this->fail('Await did not throw as expected');
    } catch (\RuntimeException $e) {
        expect($e->getMessage())->toBe('Test Error');
    }
});
