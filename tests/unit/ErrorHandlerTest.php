<?php

use Matrix\Enum\TaskStatus;
use Matrix\Exceptions\Handler;
use Matrix\Task;

afterEach(function () {
    \Mockery::close();
});

test('logs task errors with context', function () {
    $contextId = 'task_123';
    $exception = new \Exception('Something went wrong');

    // Mock the logTaskError method and allow mocking protected methods
    $handler = \Mockery::mock(Handler::class)->makePartial();
    $handler->shouldAllowMockingProtectedMethods();

    $handler->shouldReceive('logTaskError')
        ->with($contextId, \Mockery::type(Task::class), $exception)
        ->once()
        ->andReturnUsing(function ($contextId, $task, $e) {
            $status = $task->getStatus()->value; // Access the string value of the enum
            error_log(sprintf('Task %s (status: %s) failed with error: %s', $contextId, $status, $e->getMessage()));
        });

    // Trigger error handling
    $handler->handle($contextId, new Task(fn () => 'task result'), $exception);
});

test('retries a task only up to the maximum retry limit', function () {
    $contextId = 'task_789';
    $handler = new Handler(3, [\RuntimeException::class]);

    // The task will throw a RuntimeException during execution
    $task = new Task(function () {
        throw new \RuntimeException('Recoverable error');
    });

    // Start the task and let it fail
    try {
        $task->start();
    } catch (\RuntimeException $e) {
        // The exception is caught and passed to the error handler
        $handler->handle($contextId, $task, $e);
    }

    // Ensure task is in PENDING state after retry
    expect($task->getStatus())->toEqual(TaskStatus::PENDING);

    // Retry the task up to the max retries
    for ($i = 0; $i < 2; $i++) {
        // Let the handler retry the task naturally
        try {
            $task->start(); // Retry will re-execute the task
        } catch (\RuntimeException $e) {
            $handler->handle($contextId, $task, $e);
        }

        // Ensure task is PENDING after retry
        expect($task->getStatus())->toEqual(TaskStatus::PENDING);
    }

    // On exceeding max retries, the task should fail
    try {
        $task->start();
    } catch (\RuntimeException $e) {
        $handler->handle($contextId, $task, $e);
    }

    // After exhausting retries, the task should be FAILED
    expect($task->getStatus())->toEqual(TaskStatus::FAILED);
});

test('marks a task as failed when exception is not recoverable', function () {
    $contextId = 'task_987';
    $exception = new \Exception('Non-recoverable error');
    $task = new Task(function () {
        // Simulate some work before failing
        for ($i = 0; $i < 2; $i++) {
            \Fiber::suspend(); // Suspend the fiber to simulate work
        }

        return 'task result';
    });

    // Start the task
    $task->start();
    expect($task->getStatus())->toBe(TaskStatus::RUNNING);

    // Create the error handler
    $handler = new Handler(3, [\RuntimeException::class]);

    // Simulate an unrecoverable error and mark the task as failed
    $handler->handle($contextId, $task, $exception);

    // Since it's a non-recoverable exception, the task should now be marked as FAILED
    expect($task->getStatus())->toBe(TaskStatus::FAILED);
});

test('handles final failure after all retries are exhausted', function () {
    $contextId = 'task_654';
    $exception = new \RuntimeException('Recoverable error');
    $task = new Task(fn () => 'task result');

    // Mock the Handler and allow mocking protected methods
    $handler = \Mockery::mock(Handler::class)->makePartial();
    $handler->shouldAllowMockingProtectedMethods();

    // Mock the handleFinalFailure method
    $handler->shouldReceive('handleFinalFailure')
        ->once();

    // Exhaust retries
    for ($i = 0; $i < 3; $i++) {
        $task->setStatus(TaskStatus::FAILED); // Ensure the task is in a state that can be retried
        $handler->handle($contextId, $task, $exception);
        expect($task->getStatus())->toEqual(TaskStatus::PENDING); // It should still be retrying
    }

    // After retries are exhausted, final failure should be triggered
    $task->setStatus(TaskStatus::FAILED); // Ensure the task is in a state that can be retried
    $handler->handle($contextId, $task, $exception);

    // Check if final failure was called
    $handler->shouldHaveReceived('handleFinalFailure')
        ->once();
});
