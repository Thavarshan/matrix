<?php

declare(strict_types=1);

namespace Matrix;

use Matrix\Enum\TaskStatus;
use Matrix\Exceptions\Handler;
use Matrix\Interfaces\AsyncHelper as AsyncHelperInterface;
use Matrix\Interfaces\ErrorHandler;
use Throwable;

class AsyncHelper implements AsyncHelperInterface
{
    /**
     * The promise to execute asynchronously.
     *
     * @var callable
     */
    protected $promise;

    /**
     * The success callback.
     *
     * @var callable|null
     */
    protected $onSuccess;

    /**
     * The error callback.
     *
     * @var callable|null
     */
    protected $onError;

    /**
     * The error that occurred during the task execution.
     */
    protected ?Throwable $error = null;

    /**
     * The result of the task execution.
     *
     * @var mixed|null
     */
    protected $result = null;

    /**
     * The error handler for handling task errors.
     */
    protected ErrorHandler $errorHandler;

    /**
     * The task to execute asynchronously.
     */
    protected Task $task;

    /**
     * Indicates if the task has started.
     */
    protected bool $taskStarted = false;

    /**
     * AsyncHelper constructor.
     *
     *
     * @return void
     */
    public function __construct(callable $promise, ?ErrorHandler $errorHandler = null)
    {
        $this->promise = $promise;
        $this->errorHandler = $errorHandler ?? new Handler;
        $this->task = new Task($this->promise);
    }

    /**
     * Sets the success callback and starts the task.
     */
    public function then(callable $callback): self
    {
        $this->onSuccess = $callback;
        $this->start();

        return $this;
    }

    /**
     * Sets the error callback and starts the task.
     */
    public function catch(callable $callback): self
    {
        $this->onError = $callback;
        $this->start();

        return $this;
    }

    /**
     * Starts the task.
     */
    public function start(): void
    {
        if ($this->error && $this->onError) {
            ($this->onError)($this->error);

            return;
        }

        if ($this->taskStarted || $this->task->isCompleted()) {
            return;
        }

        $this->taskStarted = true;

        try {
            $this->task->start();

            if ($this->task->isCompleted()) {
                $this->result = $this->task->getResult();

                if ($this->onSuccess) {
                    ($this->onSuccess)($this->result);
                }
            }
        } catch (Throwable $e) {
            $this->errorHandler->handle(uniqid('task_', true), $this->task, $e);
            $this->error = $e;

            if ($this->onError) {
                ($this->onError)($this->error);
            }
        }
    }

    /**
     * Pauses the task.
     */
    public function pause(): void
    {
        $this->task->pause();
    }

    /**
     * Resumes the task.
     */
    public function resume(): void
    {
        $this->task->resume();
    }

    /**
     * Cancels the task.
     */
    public function cancel(): void
    {
        $this->task->cancel();
    }

    /**
     * Retries the task.
     */
    public function retry(): void
    {
        $this->task->retry();

        $this->task->start();
    }

    /**
     * Gets the status of the task.
     */
    public function getStatus(): TaskStatus
    {
        return $this->task->getStatus();
    }

    /**
     * Gets the result of the task.
     *
     * @return mixed
     */
    public function getResult()
    {
        return $this->task->getResult();
    }

    /**
     * Gets the task.
     */
    public function getTask(): Task
    {
        return $this->task;
    }

    /**
     * Checks if the task is completed.
     */
    public function isCompleted(): bool
    {
        return $this->task->isCompleted();
    }
}
