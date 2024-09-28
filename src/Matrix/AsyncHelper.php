<?php

namespace Matrix;

use Matrix\Exceptions\Handler;
use Matrix\Interfaces\ErrorHandler;
use Throwable;

class AsyncHelper
{
    /**
     * The promise to execute.
     *
     * @var callable
     */
    protected $promise;

    /**
     * The callback to execute on success.
     *
     * @var callable|null
     */
    protected $onSuccess;

    /**
     * The callback to execute on error.
     *
     * @var callable|null
     */
    protected $onError;

    /**
     * The error that occurred during the async operation.
     *
     * @var Throwable|null
     */
    protected ?Throwable $error = null;

    /**
     * The result of the task if it was successful.
     *
     * @var mixed|null
     */
    protected $result = null;

    /**
     * The error handler for managing errors.
     *
     * @var \Matrix\Interfaces\ErrorHandler
     */
    protected ErrorHandler $errorHandler;

    /**
     * The task instance managing this async operation.
     *
     * @var \Matrix\Task
     */
    protected Task $task;

    /**
     * Indicates whether the task has been started.
     *
     * @var bool
     */
    protected bool $taskStarted = false;

    /**
     * Constructor for the AsyncHelper.
     *
     * @param callable                             $promise      The promise to execute.
     * @param \Matrix\Interfaces\ErrorHandler|null $errorHandler The error handler for managing errors.
     */
    public function __construct(callable $promise, ?ErrorHandler $errorHandler = null)
    {
        $this->promise = $promise;
        $this->errorHandler = $errorHandler ?? new Handler();
        $this->task = new Task($this->promise);
    }

    /**
     * Handle successful responses.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function then(callable $callback): self
    {
        $this->onSuccess = $callback;
        $this->start(); // Start the task when 'then' is registered

        return $this;
    }

    /**
     * Handle errors or exceptions.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function catch(callable $callback): self
    {
        $this->onError = $callback;
        $this->start(); // Start the task when 'catch' is registered

        return $this;
    }

    /**
     * Start the async task manually if not already started.
     *
     * @return void
     */
    public function start(): void
    {
        // Handle any existing errors
        if ($this->error && $this->onError) {
            ($this->onError)($this->error);

            return;
        }

        // Avoid starting the task more than once
        if ($this->taskStarted || $this->task->isCompleted()) {
            return;
        }

        $this->taskStarted = true;

        try {
            $this->task->start();

            // Handle task success
            if ($this->task->isCompleted()) {
                $this->result = $this->task->getResult();

                if ($this->onSuccess) {
                    ($this->onSuccess)($this->result);
                }
            }
        } catch (Throwable $e) {
            $this->errorHandler->handle(
                uniqid('task_', true),
                $this->task,
                $e
            );

            $this->error = $e;

            // Call error callback
            if ($this->onError) {
                ($this->onError)($this->error);
            }
        }
    }
}
