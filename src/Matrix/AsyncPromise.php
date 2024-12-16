<?php

declare(strict_types=1);

namespace Matrix;

use React\Promise\PromiseInterface;

/**
 * AsyncPromise wraps a ReactPHP promise to provide a familiar `then()` and `catch()` API.
 */
class AsyncPromise
{
    /**
     * The underlying React promise.
     */
    protected PromiseInterface $promise;

    /**
     * Constructor.
     *
     * @param  PromiseInterface  $promise  The ReactPHP promise to wrap.
     */
    public function __construct(PromiseInterface $promise)
    {
        $this->promise = $promise;
    }

    /**
     * Adds a fulfillment handler to the promise and returns $this for chaining.
     *
     * @param  callable  $onFulfilled  The callback to execute when the promise resolves.
     * @return $this
     */
    public function then(callable $onFulfilled): self
    {
        $this->promise = $this->promise->then($onFulfilled);

        return $this;
    }

    /**
     * Adds a rejection handler to the promise and returns $this for chaining.
     *
     * The catch() method is analogous to otherwise() in ReactPHP promises.
     *
     * @param  callable  $onRejected  The callback to execute if the promise rejects.
     * @return $this
     */
    public function catch(callable $onRejected): self
    {
        // ReactPHP doesn't have a catch() method, but otherwise() attaches an error handler.
        $this->promise = $this->promise->otherwise($onRejected);

        return $this;
    }
}
