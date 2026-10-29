<?php

declare(strict_types=1);

namespace Matrix\Promise;

use ArrayIterator;
use Iterator;
use IteratorAggregate;
use Matrix\Exceptions\CancellationException;
use Matrix\Support\LoopManager;
use Matrix\Support\OperationState;
use Matrix\Support\OperationStateInterface;
use React\Promise;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

/**
 * @internal Shared lazy, keyed scheduler for map and pool operations.
 *
 * @template TInputKey of array-key
 * @template TInput
 * @template TOutput
 */
final class TaskScheduler
{
    private const TASKS_PER_TICK = 100;

    /** @var Deferred<array<TInputKey, TOutput|null>> */
    private Deferred $deferred;

    /** @var Iterator<TInputKey, TInput> */
    private Iterator $iterator;

    private bool $iteratorStarted = false;

    private bool $advanceInput = false;

    private bool $inputExhausted = false;

    private int $position = 0;

    private int $pending = 0;

    private int $completed = 0;

    private ?int $total;

    /** @var array<int, array{key: TInputKey, value: TOutput|null}> */
    private array $settledResults = [];

    /** @var array<int, PromiseInterface<TOutput|null>> */
    private array $active = [];

    private OperationStateInterface $state;

    private bool $scheduled = false;

    private int $limit;

    /** @var callable(TInput): (TOutput|PromiseInterface<TOutput>) */
    private $start;

    /** @var (callable(int, int|null): void)|null */
    private $onProgress;

    /**
     * @param  iterable<TInputKey, TInput>  $items
     * @param  callable(TInput): (TOutput|PromiseInterface<TOutput>)  $start
     * @param  (callable(int, int|null): void)|null  $onProgress
     */
    private function __construct(iterable $items, callable $start, int $concurrency, ?callable $onProgress)
    {
        if ($concurrency < 0) {
            throw new \InvalidArgumentException('Concurrency must be zero or greater.');
        }
        $this->total = is_array($items) || $items instanceof \Countable ? count($items) : null;
        $this->limit = $concurrency === 0 ? PHP_INT_MAX : $concurrency;
        $this->iterator = self::iterator($items);
        $this->start = $start;
        $this->onProgress = $onProgress;
        $this->state = OperationState::create();
        $this->deferred = new Deferred(function (): void {
            $this->cancel();
        });
    }

    /**
     * @template TKey of array-key
     * @template TItem
     * @template TResult
     *
     * @param  iterable<TKey, TItem>  $items
     * @param  callable(TItem): (TResult|PromiseInterface<TResult>)  $start
     * @param  (callable(int, int|null): void)|null  $onProgress
     * @return PromiseInterface<array<TKey, TResult|null>>
     */
    public static function run(iterable $items, callable $start, int $concurrency, ?callable $onProgress = null): PromiseInterface
    {
        $scheduler = new self($items, $start, $concurrency, $onProgress);
        $scheduler->schedule();

        return $scheduler->deferred->promise();
    }

    private function cancel(): void
    {
        if ($this->state->isSettled()) {
            return;
        }
        $this->state->settle();

        foreach ($this->active as $promise) {
            $promise->cancel();
        }
        $this->active = [];

        throw new CancellationException;
    }

    private function fail(\Throwable $error): void
    {
        if ($this->state->isSettled()) {
            return;
        }
        $this->state->settle();
        $this->deferred->reject($error);
    }

    private function schedule(): void
    {
        if ($this->scheduled || $this->state->isSettled()) {
            return;
        }
        $this->scheduled = true;
        LoopManager::nextTick(function (): void {
            $this->scheduled = false;
            $this->pump();
        });
    }

    private function pump(): void
    {
        if ($this->state->isSettled()) {
            return;
        }

        $startedThisTick = 0;

        while (! $this->state->isSettled() && ! $this->inputExhausted && $this->pending < $this->limit && $startedThisTick < self::TASKS_PER_TICK) {
            try {
                if (! $this->iteratorStarted) {
                    $this->iterator->rewind();
                    $this->iteratorStarted = true;
                } elseif ($this->advanceInput) {
                    $this->iterator->next();
                    $this->advanceInput = false;
                }

                // @phpstan-ignore if.alwaysFalse (Iterator methods can cancel this scheduler through user code.)
                if ($this->state->isSettled()) {
                    return;
                }

                if (! $this->iterator->valid()) {
                    $this->inputExhausted = true;

                    break;
                }

                // @phpstan-ignore if.alwaysFalse (Iterator methods can cancel this scheduler through user code.)
                if ($this->state->isSettled()) {
                    return;
                }

                $key = $this->iterator->key();

                // @phpstan-ignore if.alwaysFalse (Iterator methods can cancel this scheduler through user code.)
                if ($this->state->isSettled()) {
                    return;
                }

                $item = $this->iterator->current();

                // @phpstan-ignore if.alwaysFalse (Iterator methods can cancel this scheduler through user code.)
                if ($this->state->isSettled()) {
                    return;
                }
                $this->advanceInput = true;
            } catch (\Throwable $error) {
                $this->fail($error);

                return;
            }

            $index = $this->position++;
            $startedThisTick++;

            try {
                $promise = $this->adopt(($this->start)($item));
            } catch (\Throwable $error) {
                $this->fail($error);

                return;
            }

            // @phpstan-ignore if.alwaysFalse (The task callback can cancel its enclosing operation synchronously.)
            if ($this->state->isSettled()) {
                $promise->cancel();

                return;
            }

            $this->active[$index] = $promise;
            $this->pending++;
            $promise->then(
                function ($value) use ($index, $key): void {
                    unset($this->active[$index]);
                    $this->pending--;

                    if ($this->state->isSettled()) {
                        return;
                    }
                    $this->settledResults[$index] = ['key' => $key, 'value' => $value];
                    $this->completed++;

                    if ($this->onProgress !== null) {
                        try {
                            ($this->onProgress)($this->completed, $this->total);
                        } catch (\Throwable $error) {
                            $this->fail($error);

                            return;
                        }
                    }

                    // @phpstan-ignore booleanNot.alwaysTrue (The progress callback can cancel this scheduler synchronously.)
                    if (! $this->state->isSettled()) {
                        $this->schedule();
                    }
                },
                function (\Throwable $error): void {
                    $this->fail($error);
                }
            );
        }

        if (! $this->state->isSettled() && ! $this->inputExhausted && $this->pending < $this->limit && $startedThisTick >= self::TASKS_PER_TICK) {
            $this->schedule();
        }

        if (! $this->state->isSettled() && $this->inputExhausted && $this->pending === 0) {
            $this->resolve();
        }
    }

    private function resolve(): void
    {
        ksort($this->settledResults);
        $results = [];

        foreach ($this->settledResults as $result) {
            // Duplicate iterator keys execute independently; later input wins deterministically.
            $results[$result['key']] = $result['value'];
        }
        $this->state->settle();
        $this->deferred->resolve($results);
    }

    /**
     * @template T
     *
     * @param  T|PromiseInterface<T>  $result
     * @return PromiseInterface<T|null>
     */
    private function adopt(mixed $result): PromiseInterface
    {
        return Promise\resolve($result);
    }

    /**
     * @template TKey of array-key
     * @template TItem
     *
     * @param  iterable<TKey, TItem>  $items
     * @return Iterator<TKey, TItem>
     */
    private static function iterator(iterable $items): Iterator
    {
        if (is_array($items)) {
            return new ArrayIterator($items);
        }

        if ($items instanceof Iterator) {
            return $items;
        }

        if ($items instanceof IteratorAggregate) {
            $iterator = $items->getIterator();

            return $iterator instanceof Iterator ? $iterator : new \IteratorIterator($iterator);
        }

        return new \IteratorIterator($items);
    }
}
