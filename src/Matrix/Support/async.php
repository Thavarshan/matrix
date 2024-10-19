<?php

declare(strict_types=1);

use Matrix\AsyncHelper;

if (! function_exists('async')) {
    /**
     * Async helper function to handle fetch.
     *
     * @param  callable  $promise  The promise to be handled asynchronously.
     * @return \Matrix\AsyncHelper An instance of AsyncHelper to manage the asynchronous task.
     */
    function async(callable $promise): AsyncHelper
    {
        try {
            return new AsyncHelper($promise);
        } catch (\Throwable $e) {
            // Handle the error appropriately, e.g., log it or rethrow
            throw new \Exception('Failed to create AsyncHelper instance.', 0, $e);
        }
    }
}
