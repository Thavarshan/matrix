<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Async;
use Matrix\Exceptions\AsyncException;
use Matrix\Exceptions\CancellationException;
use Matrix\Exceptions\RetryException;
use Matrix\Exceptions\TimeoutException;

use function Matrix\Support\all;
use function Matrix\Support\async;
use function Matrix\Support\await;
use function Matrix\Support\batch;
use function Matrix\Support\cancellable;
use function Matrix\Support\map;
use function Matrix\Support\pool;
use function Matrix\Support\retry;
use function Matrix\Support\waterfall;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\Exception\LengthException;
use React\Promise\PromiseInterface;
use RuntimeException;

final class AsyncTest extends TestCase
{
    protected function setUp(): void
    {
        Async::eventDispatcher()->clear();
        Async::eventDispatcher()->setErrorHandler(null);
        Async::metricsCollector()->reset();
        Async::metricsCollector()->setEnabled(true);
    }

    public function test_coro_resolves_and_rejects_without_unhandled_side_promises(): void
    {
        self::assertSame('ok', await(async(static fn (): string => 'ok')));

        $this->expectException(RuntimeException::class);
        await(async(static fn (): never => throw new RuntimeException('failed')));
    }

    public function test_cancelling_coro_before_its_tick_skips_the_factory(): void
    {
        $ran = false;
        $operation = async(static function () use (&$ran): string {
            $ran = true;

            return 'late';
        });
        $operation->catch(static fn (\Throwable $error): null => null);
        $operation->cancel();
        await(Async::resolve(null));

        self::assertFalse($ran);
        self::assertSame(0, Async::metricsCollector()->getActivePromiseCount());
    }

    public function test_cancelling_running_coro_cancels_its_source(): void
    {
        $cancelCalls = 0;
        $source = new Deferred(static function () use (&$cancelCalls): void {
            $cancelCalls++;
        });
        $operation = async(static fn (): PromiseInterface => $source->promise());
        $operation->catch(static fn (\Throwable $error): null => null);
        Async::loop()->futureTick(static function () use ($operation): void {
            $operation->cancel();
        });
        await($operation->catch(static fn (\Throwable $error): string => $error::class));

        self::assertSame(1, $cancelCalls);
    }

    public function test_cancelling_delay_rejects_and_retires_metrics(): void
    {
        $operation = Async::delay(60);
        $caught = $operation->catch(static fn (\Throwable $error): string => $error::class);
        $operation->cancel();

        self::assertSame(CancellationException::class, await($caught));
        self::assertSame(0, Async::metricsCollector()->getActivePromiseCount());
    }

    public function test_collection_helpers_accept_values_and_preserve_keys(): void
    {
        self::assertSame(['a' => 2, 'b' => 4], await(map(['a' => 1, 'b' => 2], static fn (int $value): int => $value * 2, 1)));
        self::assertSame(['a' => 1, 'b' => 2], await(all(['a' => 1, 'b' => async(static fn (): int => 2)])));
        self::assertSame(['a' => 1, 'b' => 2], await(pool(['a' => static fn (): int => 1, 'b' => static fn (): int => 2], 1)));

        $generator = (static function (): \Generator {
            yield 'x' => 3;
            yield 'y' => 4;
        })();
        self::assertSame(['x' => 6, 'y' => 8], await(map($generator, static fn (int $value): int => $value * 2)));
    }

    public function test_all_any_and_race_cover_values_and_empty_inputs(): void
    {
        self::assertSame([], await(all([])));
        self::assertSame(2, await(Async::any([Async::reject(new RuntimeException('first')), 2])));
        self::assertSame(1, await(Async::race([1, 2])));

        try {
            await(Async::any([]));
            self::fail('Expected empty any() to reject.');
        } catch (LengthException) {
        }
    }

    public function test_lazy_map_executes_duplicate_keys_and_keeps_last_result(): void
    {
        $started = [];
        $items = (static function (): \Generator {
            yield from ['same' => 1];
            yield from ['same' => 2];
        })();

        $result = await(map($items, static function (int $value) use (&$started): int {
            $started[] = $value;

            return $value * 10;
        }, 1));

        self::assertSame([1, 2], $started);
        self::assertSame(['same' => 20], $result);
    }

    public function test_cancelled_map_suppresses_late_progress(): void
    {
        $source = new Deferred;
        $progress = [];
        $operation = map([1], static fn (): PromiseInterface => $source->promise(), 1, static function (int $done) use (&$progress): void {
            $progress[] = $done;
        });
        $operation->catch(static fn (\Throwable $error): null => null);
        $operation->cancel();
        $source->resolve('late');
        await(Async::resolve(null));

        self::assertSame([], $progress);
        self::assertSame(0, Async::metricsCollector()->getActivePromiseCount());
    }

    public function test_cancellation_inside_map_callback_cancels_its_returned_source(): void
    {
        $cancelCalls = 0;
        $source = new Deferred(static function () use (&$cancelCalls): void {
            $cancelCalls++;
        });
        $operation = null;
        $operation = map([1], static function () use (&$operation, $source): PromiseInterface {
            $operation?->cancel();

            return $source->promise();
        }, 1);
        $result = $operation->catch(static fn (\Throwable $error): string => $error::class);

        self::assertSame(CancellationException::class, await($result));
        self::assertSame(1, $cancelCalls);
    }

    public function test_idle_await_late_settlement_does_not_stop_a_later_wait(): void
    {
        $source = new Deferred;

        try {
            await($source->promise());
            self::fail('Expected idle-loop failure.');
        } catch (AsyncException) {
        }

        Async::loop()->futureTick(static function () use ($source): void {
            $source->resolve('late');
        });
        self::assertSame('later', await(Async::delay(0.001, 'later')));
    }

    public function test_loop_callback_error_cleans_up_await_timeout(): void
    {
        $cancelCalls = 0;
        $source = new Deferred(static function () use (&$cancelCalls): void {
            $cancelCalls++;
        });
        Async::loop()->futureTick(static function (): never {
            throw new RuntimeException('loop callback failed');
        });

        try {
            await($source->promise(), 0.005);
            self::fail('Expected loop callback error.');
        } catch (RuntimeException $error) {
            self::assertSame('loop callback failed', $error->getMessage());
        }

        self::assertSame('later', await(Async::delay(0.01, 'later')));
        self::assertSame(1, $cancelCalls);
    }

    public function test_iterator_errors_reject_the_scheduler(): void
    {
        $started = [];
        $items = static function (): \Generator {
            yield 1;

            throw new RuntimeException('iterator failed');
        };

        try {
            await(map($items(), static function (int $value) use (&$started): int {
                $started[] = $value;

                return $value;
            }));
            self::fail('Expected iterator failure.');
        } catch (RuntimeException $error) {
            self::assertSame('iterator failed', $error->getMessage());
        }
        self::assertSame([1], $started);
    }

    public function test_cancellation_from_generator_does_not_start_its_yielded_task(): void
    {
        $holder = new class
        {
            /** @var PromiseInterface<mixed>|null */
            public ?PromiseInterface $operation = null;
        };
        $started = [];
        $items = (static function () use ($holder): \Generator {
            $holder->operation?->cancel();
            yield 1;
        })();

        $holder->operation = map($items, static function (int $item) use (&$started): int {
            $started[] = $item;

            return $item;
        });

        self::assertSame(CancellationException::class, await($holder->operation->catch(static fn (\Throwable $error): string => $error::class)));
        self::assertSame([], $started);
    }

    public function test_synchronous_callback_errors_become_rejections(): void
    {
        $this->expectException(RuntimeException::class);
        await(map([1], static fn (): never => throw new RuntimeException('callback failed')));
    }

    public function test_pool_is_fail_fast_and_does_not_start_queued_work(): void
    {
        $started = [];
        $pending = new Deferred;
        $promise = pool([
            static function () use (&$started, $pending) {
                $started[] = 1;

                return $pending->promise();
            },
            static function () use (&$started): never {
                $started[] = 2;

                throw new RuntimeException('stop');
            },
            static function () use (&$started): int {
                $started[] = 3;

                return 3;
            },
        ], 2);

        try {
            await($promise);
            self::fail('Expected pool failure.');
        } catch (RuntimeException $exception) {
            self::assertSame('stop', $exception->getMessage());
        }
        self::assertSame([1, 2], $started);
    }

    public function test_pool_failure_observes_active_tasks_without_cancelling_them(): void
    {
        $cancelCalls = 0;
        $progress = [];
        $source = new Deferred(static function () use (&$cancelCalls): void {
            $cancelCalls++;
        });
        $operation = pool([
            static fn (): PromiseInterface => $source->promise(),
            static fn (): never => throw new RuntimeException('failed'),
        ], 2, static function (int $completed) use (&$progress): void {
            $progress[] = $completed;
        });

        try {
            await($operation);
            self::fail('Expected pool failure.');
        } catch (RuntimeException $error) {
            self::assertSame('failed', $error->getMessage());
        }
        self::assertSame(0, $cancelCalls);
        $source->resolve('late');
        self::assertSame([], $progress);
    }

    public function test_batch_and_waterfall_are_composable(): void
    {
        self::assertSame([2, 4, 6, 8], await(batch(range(1, 4), static fn (array $items): array => array_map(static fn (int $item): int => $item * 2, $items), 2, 1)));
        self::assertSame('start-1-2', await(waterfall([
            static fn (?string $value): string => ($value ?? '') . '-1',
            static fn (?string $value): string => ($value ?? '') . '-2',
        ], 'start')));
    }

    public function test_retry_records_failures_and_succeeds(): void
    {
        $attempts = 0;
        self::assertSame('ok', await(retry(function () use (&$attempts): string {
            if (++$attempts < 3) {
                throw new RuntimeException('try again');
            }

            return 'ok';
        }, 3, static fn (): float => 0.0)));
        self::assertSame(3, $attempts);

        $this->expectException(RetryException::class);
        await(retry(static fn (): never => throw new RuntimeException('always'), 2, static fn (): float => 0.0));
    }

    public function test_retry_rejects_when_backoff_throws(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('backoff failed');
        await(retry(
            static fn (): never => throw new RuntimeException('attempt failed'),
            2,
            static fn (): never => throw new \LogicException('backoff failed')
        ));
    }

    public function test_cancellation_inside_retry_factory_cancels_the_returned_source(): void
    {
        $cancelCalls = 0;
        $source = new Deferred(static function () use (&$cancelCalls): void {
            $cancelCalls++;
        });
        $holder = new class
        {
            /** @var PromiseInterface<mixed>|null */
            public ?PromiseInterface $operation = null;
        };
        $holder->operation = retry(static function () use ($holder, $source): PromiseInterface {
            $holder->operation?->cancel();

            return $source->promise();
        });
        $result = $holder->operation->catch(static fn (\Throwable $error): string => $error::class);

        self::assertSame(CancellationException::class, await($result));
        self::assertSame(1, $cancelCalls);
    }

    public function test_cancellation_inside_backoff_does_not_schedule_a_timer(): void
    {
        $originalLoop = Loop::get();
        $loop = $this->createMock(LoopInterface::class);
        $ticks = [];
        $loop->method('futureTick')->willReturnCallback(static function (callable $tick) use (&$ticks): void {
            $ticks[] = $tick;
        });
        $loop->expects(self::never())->method('addTimer');
        $loop->method('run')->willReturnCallback(static function () use (&$ticks): void {
            foreach ($ticks as $tick) {
                $tick();
            }
        });
        Loop::set($loop);

        try {
            $holder = new class
            {
                /** @var PromiseInterface<mixed>|null */
                public ?PromiseInterface $operation = null;
            };
            $holder->operation = retry(
                static fn (): never => throw new RuntimeException('attempt'),
                2,
                static function () use ($holder): float {
                    $holder->operation?->cancel();

                    return 60.0;
                }
            );
            $result = $holder->operation->catch(static fn (\Throwable $error): string => $error::class);
            self::assertSame(CancellationException::class, await($result));
        } finally {
            Loop::set($originalLoop);
        }
    }

    public function test_nonfinite_durations_are_rejected(): void
    {
        foreach ([NAN, INF] as $duration) {
            try {
                Async::delay($duration);
                self::fail('Expected nonfinite delay rejection.');
            } catch (\InvalidArgumentException) {
            }
        }

        $this->expectException(\InvalidArgumentException::class);
        Async::timeout(Async::resolve('value'), INF);
    }

    public function test_await_and_retry_reject_nonfinite_durations(): void
    {
        try {
            await(Async::resolve('value'), NAN);
            self::fail('Expected invalid await timeout.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        await(retry(
            static fn (): never => throw new RuntimeException('retry'),
            2,
            static fn (): float => NAN
        ));
    }

    public function test_timeout_cancels_source_and_cancellable_runs_cleanup_once(): void
    {
        $cancelCount = 0;
        $source = new Deferred(static function () use (&$cancelCount): void {
            $cancelCount++;
        });
        $cancelled = false;
        $timed = Async::timeout($source->promise(), 0.001);
        $timed->catch(static function (\Throwable $error): void {});

        try {
            await($timed);
        } catch (TimeoutException) {
            $cancelled = true;
        }
        self::assertTrue($cancelled);
        self::assertSame(1, $cancelCount);

        $cleanupCalls = 0;
        $operation = cancellable(Async::delay(1), static function () use (&$cleanupCalls): void {
            $cleanupCalls++;
        });
        self::assertInstanceOf(PromiseInterface::class, $operation);
        $operation->cancel();
        $operation->cancel();
        self::assertSame(1, $cleanupCalls);
    }

    public function test_error_context_preserves_matrix_exception_subtype_and_cause(): void
    {
        $timeout = new TimeoutException(0.5, 'slow');

        try {
            await(Async::withErrorContext(Async::reject($timeout), 'database read'));
            self::fail('Expected contextual timeout.');
        } catch (TimeoutException $exception) {
            self::assertStringContainsString('database read', $exception->getMessage());
            self::assertSame(0.5, $exception->getDuration());
        }

        $sqlError = new RuntimeException('query failed', 0);

        try {
            await(Async::withErrorContext(Async::reject($sqlError), 'database read'));
            self::fail('Expected contextual error.');
        } catch (AsyncException $exception) {
            self::assertSame($sqlError, $exception->getPrevious());
        }

        $pdoError = new \PDOException('query failed');
        (new \ReflectionProperty(\Exception::class, 'code'))->setValue($pdoError, '42P01');

        try {
            await(Async::withErrorContext(Async::reject($pdoError), 'database read'));
            self::fail('Expected contextual PDO failure.');
        } catch (AsyncException $exception) {
            self::assertSame($pdoError, $exception->getPrevious());
            self::assertSame(0, $exception->getCode());
        }
    }

    public function test_cancellable_forwards_when_cleanup_throws(): void
    {
        $cancelCalls = 0;
        $source = new Deferred(static function () use (&$cancelCalls): void {
            $cancelCalls++;
        });
        $operation = cancellable($source->promise(), static function (): never {
            throw new \LogicException('cleanup failed');
        });
        $result = $operation->catch(static fn (\Throwable $error): string => $error->getMessage());
        $operation->cancel();

        self::assertSame(1, $cancelCalls);
        self::assertSame('cleanup failed', await($result));
    }

    public function test_cancellable_rejects_even_if_source_does_not_settle(): void
    {
        $source = new Deferred;
        $operation = cancellable($source->promise(), static function (): void {});
        $result = $operation->catch(static fn (\Throwable $error): string => $error::class);
        $operation->cancel();

        self::assertSame(CancellationException::class, await($result));
        self::assertSame(0, Async::metricsCollector()->getActivePromiseCount());
        $source->resolve('late');
        self::assertSame(CancellationException::class, await($operation->catch(static fn (\Throwable $error): string => $error::class)));
    }

    public function test_cancellable_preserves_other_source_followers(): void
    {
        $cancelCalls = 0;
        $source = new Deferred(static function () use (&$cancelCalls): void {
            $cancelCalls++;
        });
        $otherFollower = $source->promise()->then(static fn (mixed $value): mixed => $value);
        $operation = cancellable($source->promise(), static function (): void {});
        $result = $operation->catch(static fn (\Throwable $error): string => $error::class);
        $operation->cancel();

        self::assertSame(CancellationException::class, await($result));
        self::assertSame(0, $cancelCalls);
        $source->resolve('shared');
        self::assertSame('shared', await($otherFollower));
    }

    public function test_error_context_keeps_shared_exceptions_independent(): void
    {
        $original = new TimeoutException(0.5, 'slow', 7, new RuntimeException('root cause'));
        $source = \React\Promise\reject($original);
        $first = Async::withErrorContext($source, 'first');
        $second = Async::withErrorContext($source, 'second');
        $firstError = await($first->catch(static fn (\Throwable $error): \Throwable => $error));
        $secondError = await($second->catch(static fn (\Throwable $error): \Throwable => $error));

        self::assertInstanceOf(TimeoutException::class, $firstError);
        self::assertInstanceOf(TimeoutException::class, $secondError);
        self::assertSame('slow', $original->getMessage());
        self::assertSame('first: slow', $firstError->getMessage());
        self::assertSame('second: slow', $secondError->getMessage());
        self::assertSame(0.5, $firstError->getDuration());
        self::assertSame(7, $firstError->getCode());
        self::assertSame($original, $firstError->getPrevious());
        self::assertSame($original, $secondError->getPrevious());
        self::assertNotSame($firstError, $secondError);

        $nested = $firstError->withContext('outer');
        self::assertSame('outer: first: slow', $nested->getMessage());
        self::assertSame('first: slow', $firstError->getMessage());
        self::assertSame($firstError, $nested->getPrevious());

        $failure = new RuntimeException('attempt');
        $retryError = new RetryException(2, [$failure], 'failed', 9);
        $retryCopy = $retryError->withContext('request');
        self::assertSame(2, $retryCopy->getAttempts());
        self::assertSame([$failure], $retryCopy->getFailures());
        self::assertSame(9, $retryCopy->getCode());
        self::assertSame($retryError, $retryCopy->getPrevious());

        $custom = new ContextMetadataException('job-42');
        $customCopy = $custom->withContext('outer');
        self::assertSame('job-42', $customCopy->operation);
        self::assertSame('outer: custom failure', $customCopy->getMessage());
        self::assertSame($custom, $customCopy->getPrevious());
    }

    public function test_await_rejects_invalid_timeout_and_idle_promises(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        await(Async::resolve('value'), 0);
    }

    public function test_await_reports_an_idle_unsettled_promise(): void
    {
        $this->expectException(AsyncException::class);
        await((new Deferred)->promise());
    }
}

final class ContextMetadataException extends AsyncException
{
    public function __construct(public readonly string $operation)
    {
        parent::__construct('custom failure');
    }
}
