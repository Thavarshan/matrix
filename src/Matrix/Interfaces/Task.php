<?php

declare(strict_types=1);

namespace Matrix\Interfaces;

use Exception;
use Matrix\Enum\TaskStatus;
use Throwable;

interface Task
{
    /**
     * Start the task by activating the fiber.
     *
     * Changes the task status to `RUNNING`. Throws an exception if the task has already started.
     *
     *
     * @throws Exception If the task has already started or failed during execution.
     */
    public function start(): void;

    /**
     * Check if the task has started.
     *
     * @return bool True if the task has started and is running, false otherwise.
     */
    public function isStarted(): bool;

    /**
     * Check if the task is completed.
     *
     * @return bool True if the task status is `COMPLETED`, false otherwise.
     */
    public function isCompleted(): bool;

    /**
     * Get the current status of the task.
     *
     * @return \Matrix\Enum\TaskStatus The current status of the task.
     */
    public function getStatus(): TaskStatus;

    /**
     * Cancel the task.
     *
     * Cancels the task if it is not already canceled or completed.
     *
     *
     * @throws Exception If the task is already canceled or completed.
     */
    public function cancel(): void;

    /**
     * Retry the task.
     *
     * Retries the task by resetting the fiber. The task can only be retried if it has completed or failed.
     *
     *
     * @throws Exception If the task is not completed or failed.
     */
    public function retry(): void;

    /**
     * Pause the task.
     *
     * Pauses the task if it is running.
     *
     *
     * @throws Exception If the task is not running.
     */
    public function pause(): void;

    /**
     * Resume the task.
     *
     * Resumes the task if it is in the `PAUSED` state.
     *
     *
     * @throws Exception If the task is not paused.
     */
    public function resume(): void;

    /**
     * Get the result of the task once it is completed.
     *
     * @return mixed The result of the task.
     *
     * @throws Exception If the task has not completed yet.
     */
    public function getResult();

    /**
     * Set the status of the task.
     *
     * Updates the current status of the task.
     *
     * @param  \Matrix\Enum\TaskStatus  $status  The new status of the task.
     */
    public function setStatus(TaskStatus $status): void;

    /**
     * Handles any errors that occur during task execution.
     *
     * @param  Throwable  $e  The exception or error that occurred.
     *
     * @throws Throwable If no error handler is provided.
     */
    public function handleError(Throwable $e): void;
}
