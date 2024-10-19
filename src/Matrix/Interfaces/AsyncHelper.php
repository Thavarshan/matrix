<?php

declare(strict_types=1);

namespace Matrix\Interfaces;

use Matrix\Enum\TaskStatus;
use Matrix\Task;

interface AsyncHelper
{
    /**
     * Sets the success callback and starts the task.
     */
    public function then(callable $callback): self;

    /**
     * Sets the error callback and starts the task.
     */
    public function catch(callable $callback): self;

    /**
     * Starts the task.
     */
    public function start(): void;

    /**
     * Pauses the task.
     */
    public function pause(): void;

    /**
     * Resumes the task.
     */
    public function resume(): void;

    /**
     * Cancels the task.
     */
    public function cancel(): void;

    /**
     * Retries the task.
     */
    public function retry(): void;

    /**
     * Gets the status of the task.
     */
    public function getStatus(): TaskStatus;

    /**
     * Gets the result of the task.
     *
     * @return mixed
     */
    public function getResult();

    /**
     * Gets the task.
     */
    public function getTask(): Task;

    /**
     * Checks if the task is completed.
     */
    public function isCompleted(): bool;
}
