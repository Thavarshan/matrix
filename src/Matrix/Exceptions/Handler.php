<?php

declare(strict_types=1);

namespace Matrix\Exceptions;

use Matrix\Enum\TaskStatus;
use Matrix\Interfaces\ErrorHandler as HandlerInterface;
use Matrix\Task;
use Throwable;

class Handler implements HandlerInterface
{
    /**
     * List of recoverable exceptions that can trigger a task retry.
     *
     * @var array<string>
     */
    protected static array $recoverableExceptions = [];

    /**
     * Number of retry attempts for each context (e.g., task).
     *
     * @var array<string, int>
     */
    protected array $retryCount = [];

    /**
     * Maximum allowed retries for a context (e.g., task).
     */
    protected int $maxRetries = 3;

    /**
     * Custom logger or callback for error logging.
     *
     * @var callable|null
     */
    protected $logger;

    /**
     * Constructor to set the maximum number of retries and recoverable exceptions.
     *
     * @param  int  $maxRetries  Maximum number of retries allowed for a task.
     * @param  array  $recoverableExceptions  List of exception classes that are considered recoverable.
     * @param  callable|null  $logger  Optional custom logger or callback for error logging.
     */
    public function __construct(
        int $maxRetries = 3,
        array $recoverableExceptions = [],
        ?callable $logger = null
    ) {
        $this->maxRetries = $maxRetries;
        $this->logger = $logger;

        if (! empty($recoverableExceptions)) {
            self::setRecoverableExceptions($recoverableExceptions);
        }
    }

    /**
     * Handle errors in a generic context (Task, Request, Response, etc.).
     *
     * @param  string  $contextId  The unique identifier for the context in which the error occurred.
     * @param  mixed  $context  The context in which the error occurred (e.g., Task, Request, Response).
     * @param  Throwable  $e  The exception or error that occurred.
     */
    public function handle(string $contextId, $context, Throwable $e): void
    {
        // Check if the context is a Task, as our current logic primarily supports Tasks.
        if ($context instanceof Task) {
            $this->handleTaskError($contextId, $context, $e);

            return;
        }

        // Fallback for handling errors in other contexts (e.g., Request, Response)
        $this->logGenericError($contextId, $context, $e);
    }

    /**
     * Handles the task error by logging the error, checking if it is recoverable, and retrying if possible.
     *
     * @param  string  $contextId  Unique identifier for the task (e.g., task ID).
     * @param  \Matrix\Task  $task  The task object associated with the error.
     * @param  Throwable  $e  The exception that occurred.
     */
    protected function handleTaskError(string $contextId, Task $task, Throwable $e): void
    {
        // Log the task-specific error
        $this->logTaskError($contextId, $task, $e);

        // Mark the task as failed before retrying
        $task->setStatus(TaskStatus::FAILED);

        // Check if the error is recoverable and the task can be retried
        if ($this->shouldRetry($contextId, $task, $e)) {
            // Retry the task
            $this->retryTask($contextId, $task);

            return;
        }

        // If retries are exhausted or the error is non-recoverable, mark the task as failed
        $task->setStatus(TaskStatus::FAILED);
        $this->handleFinalFailure($contextId, $task, $e);
    }

    /**
     * Logs the task error, providing context and details about the failure.
     *
     * @param  string  $taskId  The unique task identifier.
     * @param  \Matrix\Task  $task  The task object that encountered the error.
     * @param  Throwable  $e  The exception that caused the task to fail.
     */
    protected function logTaskError(string $taskId, Task $task, Throwable $e): void
    {
        $status = $task->getStatus()->value;
        $message = sprintf(
            'Task %s (status: %s) failed with error: %s in %s on line %d',
            $taskId,
            $status,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );

        $this->logger
            ? call_user_func($this->logger, $message)
            : error_log($message);
    }

    /**
     * Logs the error for generic contexts (non-Task contexts).
     *
     * @param  string  $contextId  The unique identifier for the context.
     * @param  mixed  $context  The context in which the error occurred (e.g., Request, Response).
     * @param  Throwable  $e  The exception that caused the error.
     */
    protected function logGenericError(string $contextId, $context, Throwable $e): void
    {
        $contextType = is_object($context) ? get_class($context) : gettype($context);
        $message = sprintf(
            'Error in context %s (%s) with message: %s',
            $contextId,
            $contextType,
            $e->getMessage()
        );

        // Use custom logger if provided, fallback to error_log
        $this->logger
            ? call_user_func($this->logger, $message)
            : error_log($message);
    }

    /**
     * Determines whether a task should be retried based on the exception type and retry limit.
     *
     * @param  string  $taskId  The unique task identifier.
     * @param  \Matrix\Task  $task  The task object.
     * @param  Throwable  $e  The exception that occurred.
     * @return bool True if the task should be retried, false otherwise.
     */
    protected function shouldRetry(string $taskId, Task $task, Throwable $e): bool
    {
        // Initialize the retry count for the task if it doesn't exist
        if (! isset($this->retryCount[$taskId])) {
            $this->retryCount[$taskId] = 0;
        }

        // Check if the exception is in the list of recoverable exceptions
        $isRecoverable = in_array(get_class($e), self::$recoverableExceptions);

        // If the error is recoverable and the task has remaining retry attempts
        if ($isRecoverable && $this->retryCount[$taskId] < $this->maxRetries) {
            $this->retryCount[$taskId]++;

            return true;
        }

        return false;
    }

    /**
     * Retries the task by invoking the retry method on the task object.
     *
     * @param  string  $taskId  The unique task identifier.
     * @param  \Matrix\Task  $task  The task object to retry.
     */
    protected function retryTask(string $taskId, Task $task): void
    {
        $retryAttempt = $this->retryCount[$taskId];

        // Log the retry attempt
        $this->logger
            ? call_user_func($this->logger, sprintf('Retrying task %s (attempt %d)...', $taskId, $retryAttempt))
            : error_log(sprintf('Retrying task %s (attempt %d)...', $taskId, $retryAttempt));

        // Retry the task and reset its state
        $task->retry();
    }

    /**
     * Handles the final failure of a task after all retry attempts have been exhausted.
     *
     * @param  string  $taskId  The unique task identifier.
     * @param  \Matrix\Task  $task  The task that failed.
     * @param  Throwable  $e  The exception that caused the final failure.
     */
    protected function handleFinalFailure(string $taskId, Task $task, Throwable $e): void
    {
        // Log the final failure of the task for debugging or monitoring purposes
        $message = sprintf('Task %s permanently failed: %s', $taskId, $e->getMessage());

        if ($this->logger) {
            call_user_func($this->logger, $message);
        } else {
            error_log($message);
        }

        // Optional: Trigger alerts, notifications, or other failure handling mechanisms
    }

    /**
     * Sets the list of recoverable exceptions.
     *
     * @param  array<string>  $recoverableExceptions  Array of exception class names considered recoverable.
     */
    public static function setRecoverableExceptions(array $recoverableExceptions): void
    {
        self::$recoverableExceptions = $recoverableExceptions;
    }
}
