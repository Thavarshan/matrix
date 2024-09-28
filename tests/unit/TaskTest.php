<?php

use Matrix\Enum\TaskStatus;
use Matrix\Exceptions\Handler;
use Matrix\Task;

/**
 * Test that the task is initialized correctly.
 */
test('initializes the task with pending status', function () {
    $task = new Task(fn () => 'task result');
    expect($task->getStatus())->toBe(TaskStatus::PENDING);
});

/**
 * Test that the task can be started and changes status to running, pauses, resumes, and completes.
 */
test('starts the task, changes status to running, pauses, resumes, and completes', function () {
    $task = new Task(function () {
        for ($i = 0; $i < 2; $i++) {
            \Fiber::suspend(); // Simulate pausing work
        }

        return 'task result';
    });

    // Start the task
    $task->start();
    expect($task->getStatus())->toBe(TaskStatus::RUNNING);

    // Pause the task after first suspension
    $task->pause();
    expect($task->getStatus())->toBe(TaskStatus::PAUSED);

    // Resume the task after suspension
    $task->resume();
    expect($task->getStatus())->toBe(TaskStatus::RUNNING);

    // Pause and resume again to complete the task
    $task->pause(); // This should be required to transition back to the paused state
    expect($task->getStatus())->toBe(TaskStatus::PAUSED);

    $task->resume(); // Resume the task again to finish
    expect($task->isCompleted())->toBeTrue();
    expect($task->getStatus())->toBe(TaskStatus::COMPLETED);

    // Check the result after the task is completed
    expect($task->getResult())->toBe('task result');
});

/**
 * Test that starting a task twice throws an exception.
 */
test('throws an exception when starting a task that has already started', function () {
    $task = new Task(fn () => 'task result');
    $task->start();
    $task->start(); // Should throw an exception
})->throws(\Exception::class, 'Task has already started.');

/**
 * Test that a task is marked as completed when the fiber terminates.
 */
test('marks the task as completed when the fiber terminates', function () {
    $task = new Task(fn () => 'Completed');
    $task->start();
    expect($task->isCompleted())->toBeTrue();
    expect($task->getStatus())->toBe(TaskStatus::COMPLETED);
});

/**
 * Test that a task can be canceled and changes status to canceled.
 */
test('cancels a task and sets the status to canceled', function () {
    $task = new Task(function () {
        for ($i = 0; $i < 3; $i++) {
            \Fiber::suspend();
        }

        return 'Completed';
    });
    $task->start();
    expect($task->getStatus())->toBe(TaskStatus::RUNNING);

    $task->cancel();
    expect($task->getStatus())->toBe(TaskStatus::CANCELED);
});

/**
 * Test that retrying a task resets its status back to pending.
 */
test('retries a completed or failed task and sets status back to pending', function () {
    $task = new Task(fn () => 'task result');
    $task->start();
    $task->setStatus(TaskStatus::COMPLETED);
    expect($task->isCompleted())->toBeTrue();

    $task->retry();
    expect($task->getStatus())->toBe(TaskStatus::PENDING);
});

/**
 * Test that pausing and resuming a task works correctly.
 */
test('pauses and resumes the task correctly', function () {
    $task = new Task(function () {
        for ($i = 0; $i < 3; $i++) {
            \Fiber::suspend();
        }

        return 'Completed';
    });
    $task->start();
    expect($task->getStatus())->toBe(TaskStatus::RUNNING);

    $task->pause();
    expect($task->getStatus())->toBe(TaskStatus::PAUSED);

    $task->resume();
    expect($task->getStatus())->toBe(TaskStatus::RUNNING);
});

/**
 * Test that resuming a non-paused task throws an exception.
 */
test('throws exception when trying to resume a non-paused task', function () {
    $task = new Task(fn () => 'task result');
    $task->start();
    $task->resume(); // Should throw an exception
})->throws(\Exception::class, 'Task can only be resumed if it is paused.');

/**
 * Test that canceling a completed task throws an exception.
 */
test('throws an exception when trying to cancel a completed task', function () {
    $task = new Task(fn () => 'task result');
    $task->start();
    $task->setStatus(TaskStatus::COMPLETED);
    $task->cancel(); // Should throw an exception
})->throws(\Exception::class, 'Task is already completed or canceled.');

/**
 * Test that retrying a non-completed or non-failed task throws an exception.
 */
test('throws exception when retrying a non-completed or non-failed task with ErrorHandler', function () {
    $task = new Task(fn () => 'task result');
    $task->start();

    $errorHandler = new Handler(3, [\RuntimeException::class]);
    $errorHandler->handle('task_1', $task, new \RuntimeException('Test Exception'));

    $task->retry(); // Should throw an exception
})->throws(\Exception::class, 'Task is not in a state that can be retried.');

/**
 * Test that retrying a completed task works and resets its status to pending.
 */
test('allows retry on a completed task', function () {
    $task = new Task(fn () => 'task result');
    $task->start();
    $task->setStatus(TaskStatus::COMPLETED);

    $task->retry();
    expect($task->getStatus())->toBe(TaskStatus::PENDING);
});

/**
 * Test that a task result can be stored and retrieved after completion.
 */
test('stores and retrieves task result after completion', function () {
    $result = 'Task Result';
    $task = new Task(fn () => $result);
    $task->start();

    expect($task->getResult())->toBe($result);
});
