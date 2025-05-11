<?php

declare(strict_types=1);

namespace Matrix\Promise;

use Matrix\Async;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * A pool for controlling concurrent promise execution.
 *
 * @template T
 */
class PromisePool
{
    /**
     * @var array<callable(): PromiseInterface<T>> Array of callables that return promises
     */
    private array $tasks;

    /**
     * @var int Maximum number of concurrent promises
     */
    private int $concurrency;

    /**
     * @var callable(int, int): void|null Progress callback
     */
    private $progressCallback;

    /**
     * Create a new promise pool.
     *
     * @param  array<callable(): PromiseInterface<T>>  $tasks  Array of callables that return promises
     * @param  int  $concurrency  Maximum number of concurrent promises
     * @param  callable(int $done, int $total): void|null  $progressCallback  Optional progress callback
     */
    public function __construct(
        array $tasks,
        int $concurrency = 5,
        ?callable $progressCallback = null
    ) {
        $this->tasks = $tasks;
        $this->concurrency = max(1, $concurrency);
        $this->progressCallback = $progressCallback;
    }

    /**
     * Create and run a new promise pool.
     *
     * @template U
     *
     * @param  array<callable(): PromiseInterface<U>>  $tasks  Array of callables that return promises
     * @param  int  $concurrency  Maximum number of concurrent promises
     * @param  callable(int $done, int $total): void|null  $progressCallback  Optional progress callback
     * @return PromiseInterface<array<U>> Promise that resolves with an array of results
     */
    public static function create(
        array $tasks,
        int $concurrency = 5,
        ?callable $progressCallback = null
    ): PromiseInterface {
        return (new self($tasks, $concurrency, $progressCallback))->run();
    }

    /**
     * Run the tasks in the pool.
     *
     * @return PromiseInterface<array<T>> Promise that resolves with an array of results
     */
    public function run(): PromiseInterface
    {
        if (empty($this->tasks)) {
            return Async::resolve([]);
        }

        $results = [];
        $pending = 0;
        $position = 0;
        $completed = 0;
        $totalTasks = count($this->tasks);
        $deferred = new Deferred;

        $processNext = function () use (
            &$pending,
            &$position,
            &$completed,
            &$results,
            $totalTasks,
            $deferred,
            &$processNext
        ): void {
            // All tasks done, resolve with results
            if ($completed === $totalTasks) {
                ksort($results);
                $deferred->resolve($results);

                return;
            }

            // Process more tasks if we're under concurrency limit
            while ($pending < $this->concurrency && $position < $totalTasks) {
                $idx = $position++;
                $task = $this->tasks[$idx];
                $pending++;

                try {
                    $task()->then(
                        function ($result) use (
                            $idx,
                            &$results,
                            &$pending,
                            &$completed,
                            $totalTasks,
                            $processNext
                        ): void {
                            $results[$idx] = $result;
                            $pending--;
                            $completed++;

                            if ($this->progressCallback !== null) {
                                ($this->progressCallback)($completed, $totalTasks);
                            }

                            $processNext();
                        },
                        function ($reason) use ($deferred): void {
                            $deferred->reject($reason);
                        }
                    );
                } catch (\Throwable $e) {
                    $deferred->reject($e);

                    return;
                }
            }
        };

        Async::loop()->futureTick($processNext);

        return $deferred->promise();
    }
}
