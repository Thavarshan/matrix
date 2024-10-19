<?php

declare(strict_types=1);

namespace Matrix;

use Exception;
use Fiber;
use Matrix\Enum\TaskStatus;
use Matrix\Interfaces\ErrorHandler as HandlerInterface;
use Matrix\Interfaces\Task as TaskInterface;
use Throwable;

class Task implements TaskInterface
{
    /**
     * The fiber responsible for executing the task.
     */
    protected Fiber $fiber;

    /**
     * The original callable that creates the task.
     *
     * @var callable
     */
    protected $callable;

    /**
     * The status of the task (e.g., pending, running, completed, failed, paused, canceled).
     */
    protected TaskStatus $status;

    /**
     * The error handler for handling task errors.
     */
    protected ?HandlerInterface $errorHandler;

    /**
     * The result of the task execution.
     *
     * @var mixed
     */
    protected $result;

    /**
     * Task constructor.
     *
     * Initializes a new task with the given callable function and sets its initial status to `PENDING`.
     *
     * @param  callable  $callable  The function that defines the task's execution.
     * @param  \Matrix\Interfaces\ErrorHandler|null  $errorHandler  The error handler for handling task errors.
     * @return void
     */
    public function __construct(callable $callable, ?HandlerInterface $errorHandler = null)
    {
        $this->callable = $callable;
        $this->fiber = new Fiber($callable);
        $this->status = TaskStatus::PENDING;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Start the task by activating the fiber.
     *
     * Changes the task status to `RUNNING`. Throws an exception if the task has already started.
     *
     *
     * @throws Exception If the task has already started or failed during execution.
     */
    public function start(): void
    {
        if ($this->fiber->isStarted()) {
            throw new Exception('Task has already started.');
        }

        try {
            $this->fiber->start();
        } catch (Throwable $e) {
            $this->handleError($e);
        }

        // If the fiber is suspended, mark the task as running and await resume
        if ($this->fiber->isSuspended()) {
            $this->setStatus(TaskStatus::RUNNING);
        }

        if ($this->fiber->isTerminated()) {
            // If the fiber has terminated, store the result and mark the task as completed
            $this->result = $this->fiber->getReturn();

            $this->setStatus(TaskStatus::COMPLETED);
        }
    }

    /**
     * Check if the task has started.
     *
     * @return bool True if the task has started and is running, false otherwise.
     */
    public function isStarted(): bool
    {
        return $this->fiber->isStarted() && $this->status === TaskStatus::RUNNING;
    }

    /**
     * Check if the task is completed.
     *
     * @return bool True if the task status is `COMPLETED`, false otherwise.
     */
    public function isCompleted(): bool
    {
        return $this->status === TaskStatus::COMPLETED;
    }

    /**
     * Get the current status of the task.
     *
     * @return \Matrix\Enum\TaskStatus The current status of the task.
     */
    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    /**
     * Cancel the task.
     *
     * Cancels the task if it is not already canceled or completed.
     *
     * @throws Exception If the task is already canceled or completed.
     */
    public function cancel(): void
    {
        if ($this->status === TaskStatus::CANCELED || $this->status === TaskStatus::COMPLETED) {
            throw new Exception('Task is already completed or canceled.');
        }

        if ($this->fiber->isStarted() && ! $this->fiber->isTerminated()) {
            try {
                $this->fiber->throw(new Exception('Task canceled'));
            } catch (Exception $e) {
                // Suppress the exception within the fiber and proceed with cancellation
            }
        }

        $this->status = TaskStatus::CANCELED;
    }

    /**
     * Retry the task.
     *
     * Retries the task by resetting the fiber. The task can only be retried if it has completed or failed.
     *
     * @throws Exception If the task is not completed or failed.
     */
    public function retry(): void
    {
        if (! $this->fiber->isTerminated() && $this->status !== TaskStatus::FAILED) {
            throw new Exception('Task is not in a state that can be retried.');
        }

        $this->fiber = new Fiber($this->callable);
        $this->status = TaskStatus::PENDING;
    }

    /**
     * Pause the task.
     *
     * Pauses the task if it is running.
     *
     * @throws Exception If the task is not running.
     */
    public function pause(): void
    {
        if ($this->status !== TaskStatus::RUNNING) {
            throw new Exception('Task can only be paused when running.');
        }

        if (! $this->fiber->isSuspended()) {
            $this->fiber->suspend();
        }

        $this->status = TaskStatus::PAUSED;
    }

    /**
     * Resume the task.
     *
     * Resumes the task if it is in the `PAUSED` state.
     *
     * @throws Exception If the task is not paused.
     */
    public function resume(): void
    {
        if ($this->status !== TaskStatus::PAUSED) {
            throw new Exception('Task can only be resumed if it is paused.');
        }

        try {
            $this->fiber->resume();
        } catch (Throwable $e) {
            $this->handleError($e);
        }

        if ($this->fiber->isSuspended()) {
            $this->setStatus(TaskStatus::RUNNING);
        } elseif ($this->fiber->isTerminated()) {
            $this->result = $this->fiber->getReturn();
            $this->setStatus(TaskStatus::COMPLETED);
        }
    }

    /**
     * Get the result of the task once it is completed.
     *
     * @return mixed The result of the task.
     *
     * @throws Exception If the task has not completed yet.
     */
    public function getResult()
    {
        if ($this->status !== TaskStatus::COMPLETED) {
            throw new Exception('Cannot get result of a task that has not completed.');
        }

        return $this->result;
    }

    /**
     * Set the status of the task.
     *
     * Updates the current status of the task.
     *
     * @param  \Matrix\Enum\TaskStatus  $status  The new status of the task.
     */
    public function setStatus(TaskStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * Handles any errors that occur during task execution.
     *
     * @param  Throwable  $e  The exception or error that occurred.
     *
     * @throws Throwable If no error handler is provided.
     */
    public function handleError(Throwable $e): void
    {
        if (! $this->errorHandler) {
            throw $e; // If no error handler is provided, rethrow the error.
        }

        $this->errorHandler->handle('task_id', $this, $e);
        $this->setStatus(TaskStatus::FAILED);
    }
}
