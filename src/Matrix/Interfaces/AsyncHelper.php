<?php

namespace Matrix\Interfaces;

use Matrix\Enum\TaskStatus;
use Matrix\Task;

interface AsyncHelper
{
    /**
     * Sets the success callback and starts the task.
     *
     * @param callable $callback
     *
     * @return self
     */
    public function then(callable $callback): self;

    /**
     * Sets the error callback and starts the task.
     *
     * @param callable $callback
     *
     * @return self
     */
    public function catch(callable $callback): self;

    /**
     * Starts the task.
     *
     * @return void
     */
    public function start(): void;

    /**
     * Pauses the task.
     *
     * @return void
     */
    public function pause(): void;

    /**
     * Resumes the task.
     *
     * @return void
     */
    public function resume(): void;

    /**
     * Cancels the task.
     *
     * @return void
     */
    public function cancel(): void;

    /**
     * Retries the task.
     *
     * @return void
     */
    public function retry(): void;

    /**
     * Gets the status of the task.
     *
     * @return \Matrix\Enum\TaskStatus
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
     *
     * @return \Matrix\Task
     */
    public function getTask(): Task;

    /**
     * Checks if the task is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool;
}
