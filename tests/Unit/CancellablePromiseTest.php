<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Promise\CancellablePromise;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use RuntimeException;

class CancellablePromiseTest extends TestCase
{
    public function test_get_promise_returns_wrapped_promise(): void
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();
        $cancelFn = function () {};

        $cancellablePromise = new CancellablePromise($promise, $cancelFn);

        $this->assertSame($promise, $cancellablePromise->getPromise());
    }

    public function test_is_cancelled_returns_false_by_default(): void
    {
        $deferred = new Deferred;
        $cancelFn = function () {};

        $cancellablePromise = new CancellablePromise($deferred->promise(), $cancelFn);

        $this->assertFalse($cancellablePromise->isCancelled());
    }

    public function test_cancel_sets_is_cancelled_to_true(): void
    {
        $deferred = new Deferred;
        $cancelFn = function () {};

        $cancellablePromise = new CancellablePromise($deferred->promise(), $cancelFn);
        $cancellablePromise->cancel();

        $this->assertTrue($cancellablePromise->isCancelled());
    }

    public function test_cancel_calls_cancellation_function(): void
    {
        $deferred = new Deferred;
        $called = false;
        $cancelFn = function () use (&$called) {
            $called = true;
        };

        $cancellablePromise = new CancellablePromise($deferred->promise(), $cancelFn);
        $cancellablePromise->cancel();

        $this->assertTrue($called, 'Cancellation function should have been called');
    }

    public function test_cancel_only_calls_cancellation_function_once(): void
    {
        $deferred = new Deferred;
        $callCount = 0;
        $cancelFn = function () use (&$callCount) {
            $callCount++;
        };

        $cancellablePromise = new CancellablePromise($deferred->promise(), $cancelFn);

        // Call cancel multiple times
        $cancellablePromise->cancel();
        $cancellablePromise->cancel();
        $cancellablePromise->cancel();

        $this->assertEquals(1, $callCount, 'Cancellation function should only be called once');
    }

    public function test_cancellation_function_can_reject_promise(): void
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();

        $error = new RuntimeException('Operation cancelled');
        $cancelFn = function () use ($deferred, $error) {
            $deferred->reject($error);
        };

        $cancellablePromise = new CancellablePromise($promise, $cancelFn);

        // Set up rejection handler
        $rejectionReason = null;
        $promise->then(
            null,
            function ($reason) use (&$rejectionReason) {
                $rejectionReason = $reason;
            }
        );

        // Cancel the promise
        $cancellablePromise->cancel();

        // Verify the promise was rejected with the expected error
        $this->assertSame($error, $rejectionReason);
    }

    public function test_cancellation_function_can_resolve_promise(): void
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();

        $result = 'cancelled_successfully';
        $cancelFn = function () use ($deferred, $result) {
            $deferred->resolve($result);
        };

        $cancellablePromise = new CancellablePromise($promise, $cancelFn);

        // Set up fulfillment handler
        $resolvedValue = null;
        $promise->then(
            function ($value) use (&$resolvedValue) {
                $resolvedValue = $value;
            }
        );

        // Cancel the promise
        $cancellablePromise->cancel();

        // Verify the promise was resolved with the expected value
        $this->assertSame($result, $resolvedValue);
    }

    public function test_original_promise_still_works_normally(): void
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();
        $cancelFn = function () {};

        $cancellablePromise = new CancellablePromise($promise, $cancelFn);

        // Set up handlers
        $resolvedValue = null;
        $promise->then(
            function ($value) use (&$resolvedValue) {
                $resolvedValue = $value;
            }
        );

        // Resolve the promise normally
        $deferred->resolve('success');

        // Verify the promise was resolved with the expected value
        $this->assertSame('success', $resolvedValue);
    }

    public function test_cancellation_after_resolution_does_not_affect_promise(): void
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();

        $cancelCalled = false;
        $cancelFn = function () use (&$cancelCalled) {
            $cancelCalled = true;
        };

        $cancellablePromise = new CancellablePromise($promise, $cancelFn);

        // Resolve the promise normally
        $deferred->resolve('success');

        // Then try to cancel it
        $cancellablePromise->cancel();

        // The cancellation function should still be called
        $this->assertTrue($cancelCalled);

        // But the promise should still be resolved with the original value
        $resolvedValue = null;
        $promise->then(
            function ($value) use (&$resolvedValue) {
                $resolvedValue = $value;
            }
        );

        $this->assertSame('success', $resolvedValue);
    }

    public function test_integration_with_then_method(): void
    {
        $deferred = new Deferred;
        $promise = $deferred->promise();
        $cancelFn = function () use ($deferred) {
            $deferred->reject(new RuntimeException('Cancelled'));
        };

        $cancellablePromise = new CancellablePromise($promise, $cancelFn);

        // Create a chain of promises
        $transformed = null;
        $rejected = false;
        $rejectionReason = null;

        $promise
            ->then(function ($value) {
                return strtoupper($value);
            })
            ->then(function ($value) use (&$transformed) {
                $transformed = $value;

                return $value;
            })
            ->then(null, function ($reason) use (&$rejected, &$rejectionReason) {
                $rejected = true;
                $rejectionReason = $reason;
            });

        // Cancel the original promise
        $cancellablePromise->cancel();

        // The promise chain should be rejected
        $this->assertTrue($rejected);
        $this->assertInstanceOf(RuntimeException::class, $rejectionReason);
        $this->assertEquals('Cancelled', $rejectionReason->getMessage());

        // The transformation should not have occurred
        $this->assertNull($transformed);
    }
}
