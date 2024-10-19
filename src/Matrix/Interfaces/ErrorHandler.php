<?php

declare(strict_types=1);

namespace Matrix\Interfaces;

use Throwable;

interface ErrorHandler
{
    /**
     * Handle errors in a generic context (Task, Request, Response, etc.).
     *
     * @param  string  $contextId  The unique identifier for the context in which the error occurred.
     * @param  mixed  $context  The context in which the error occurred (e.g., Task, Request, Response).
     * @param  Throwable  $e  The exception or error that occurred.
     */
    public function handle(
        string $contextId,
        $context,
        Throwable $e
    ): void;
}
