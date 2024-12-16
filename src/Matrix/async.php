<?php

declare(strict_types=1);

use Matrix\AsyncProcessManager;
use Matrix\AsyncPromise;

if (! function_exists('async')) {
    /**
     * Initiates an asynchronous task and returns an AsyncPromise.
     *
     * @param  callable  $callable  The asynchronous task to run in a child process.
     * @return AsyncPromise A promise-like object with then/catch methods.
     */
    function async(callable $callable): AsyncPromise
    {
        $manager = new AsyncProcessManager;
        $promise = $manager->fork($callable);

        return new AsyncPromise($promise);
    }
}
