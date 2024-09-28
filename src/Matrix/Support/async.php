<?php

use Matrix\AsyncHelper;

if (! function_exists('async')) {
    /**
     * Async helper function to handle fetch.
     *
     * @param callable $promise
     *
     * @return \Matrix\AsyncHelper
     */
    function async(callable $promise): AsyncHelper
    {
        return new AsyncHelper($promise);
    }
}
