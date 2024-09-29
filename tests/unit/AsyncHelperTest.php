<?php

use Matrix\AsyncHelper;
use Matrix\Enum\TaskStatus;
use Matrix\Exceptions\Handler;

/**
 * Test that the AsyncHelper executes a simple task asynchronously and returns the correct result.
 */
test('executes task asynchronously and returns result', function () {
    $result = 'Async Result';

    $asyncHelper = new AsyncHelper(fn () => $result, new Handler());

    // Execute and verify the result
    $asyncHelper->then(function ($res) use ($result) {
        expect($res)->toBe($result); // Ensure the result matches
    });
});

/**
 * Test that AsyncHelper handles a successful asynchronous task execution with the correct callback.
 */
test('calls then callback on success', function () {
    $result = 'Success';

    $asyncHelper = new AsyncHelper(fn () => $result);

    $wasCalled = false;

    $asyncHelper->then(function ($res) use (&$wasCalled, $result) {
        expect($res)->toBe($result);
        $wasCalled = true;
    });

    expect($wasCalled)->toBeTrue();
});

/**
 * Test that AsyncHelper handles exceptions and triggers the catch callback.
 */
test('calls catch callback on error', function () {
    $exception = new \Exception('Something went wrong');

    $asyncHelper = new AsyncHelper(function () use ($exception) {
        throw $exception;
    });

    $wasCalled = false;

    $asyncHelper->catch(function ($e) use (&$wasCalled, $exception) {
        expect($e)->toBe($exception);
        $wasCalled = true;
    });

    expect($wasCalled)->toBeTrue();
});

/**
 * Test that AsyncHelper does not call the success callback when an error occurs.
 */
test('does not call then callback on error', function () {
    $exception = new \Exception('An error occurred');
    $wasCalled = false;

    $asyncHelper = new AsyncHelper(function () use ($exception) {
        throw $exception;
    }, new Handler());

    // This should not be called because an exception occurs
    $asyncHelper->then(function () use (&$wasCalled) {
        $wasCalled = true;
    });

    // Ensure the success callback was not called
    expect($wasCalled)->toBeFalse();
});

/**
 * Test that AsyncHelper does not call the catch callback on successful task completion.
 */
test('does not call catch callback on success', function () {
    $result = 'Success';
    $wasCalled = false;

    $asyncHelper = new AsyncHelper(fn () => $result, new Handler());

    // This should not be called because no error occurs
    $asyncHelper->catch(function () use (&$wasCalled) {
        $wasCalled = true;
    });

    // Ensure the error callback was not called
    expect($wasCalled)->toBeFalse();
});

/**
 * Test that AsyncHelper integrates properly with Task and ErrorHandler.
 */
test('integrates Task and ErrorHandler correctly', function () {
    $result = 'Task Success';
    $wasErrorCalled = false;

    $asyncHelper = new AsyncHelper(fn () => $result, new Handler());

    // Simulate a success scenario
    $asyncHelper
        ->then(function ($res) use ($result) {
            expect($res)->toBe($result); // Validate result
        })
        ->catch(function () use (&$wasErrorCalled) {
            $wasErrorCalled = true; // This should not be called
        });

    expect($wasErrorCalled)->toBeFalse(); // No errors should have occurred
});

/**
 * Test that AsyncHelper respects task lifecycle.
 */
test('handles task lifecycle correctly', function () {
    $result = 'Lifecycle Success';
    $taskStatus = TaskStatus::PENDING;

    $asyncHelper = new AsyncHelper(fn () => $result, new Handler());

    $asyncHelper->then(function ($res) use (&$taskStatus) {
        expect($res)->toBe('Lifecycle Success'); // Validate result
        $taskStatus = TaskStatus::COMPLETED; // Task should be completed
    });

    // Now the task should transition to completed
    expect($taskStatus)->toBe(TaskStatus::COMPLETED); // Task should transition to completed
});

/**
 * Test task cancellation in AsyncHelper.
 */
test('cancels task correctly', function () {
    $asyncHelper = new AsyncHelper(fn () => 'Task result');

    // Manually cancel the task
    $asyncHelper->cancel();

    expect($asyncHelper->getStatus())->toBe(TaskStatus::CANCELED);
});

/**
 * Test task retry functionality.
 */
test('retries task correctly', function () {
    // Move $attempt outside of the closure so it is preserved across retries
    $attempt = 0;
    $asyncHelper = new AsyncHelper(function () use (&$attempt) {
        if (++$attempt < 2) {
            throw new \Exception('Fail');
        }

        return 'Retry Success';
    });

    $asyncHelper->catch(function ($e) {
        expect($e->getMessage())->toBe('Fail'); // First attempt should fail
    });

    // Retry the task after failure
    $asyncHelper->retry();

    // Ensure the task was retried and completed successfully
    expect($asyncHelper->getStatus())->toBe(TaskStatus::COMPLETED); // Should be completed after retry
    expect($asyncHelper->getResult())->toBe('Retry Success'); // Ensure the result is correct
});

/**
 * Test the async helper method with task control.
 */
test('helper method handles task with control methods correctly', function () {
    $message = '';

    // Use async helper to automatically start the task
    async(fn () => 'Task result')
        ->then(function ($res) use (&$message) {
            $message = $res;
        });

    expect($message)->toBe('Task result');

    // Test task cancellation
    $asyncHelper = async(fn () => 'Task result');
    $asyncHelper->cancel();
    expect($asyncHelper->getStatus())->toBe(TaskStatus::CANCELED);

    // Test task retry
    $attempt = 0;
    $retryAsync = async(function () use (&$attempt) {
        if (++$attempt < 2) {
            throw new \Exception('Error on first try');
        }

        return 'Retry Success';
    });

    $retryAsync->catch(function ($e) {
        expect($e->getMessage())->toBe('Error on first try'); // First attempt should fail
    });

    $retryAsync->retry();

    expect($retryAsync->getStatus())->toBe(TaskStatus::COMPLETED);
    expect($retryAsync->getResult())->toBe('Retry Success');
});

/**
 * Test the async helper method.
 */
test('helper method handles task correctly', function () {
    $message = '';

    // Use async helper to automatically start the task
    async(fn () => 'Task result')
        ->then(function ($res) use (&$message) {
            $message = $res;
        });

    expect($message)->toBe('Task result');

    $errorMessage = '';

    async(fn () => throw new \Exception('Fake error'))
        ->catch(function ($e) use (&$errorMessage) {
            $errorMessage = $e->getMessage();
        });

    expect($errorMessage)->toBe('Fake error');
});
