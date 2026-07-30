<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Async;
use Matrix\Exceptions\AsyncException;
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
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use RuntimeException;

final class AsyncTest extends TestCase
{
    protected function setUp(): void
    {
        Async::eventDispatcher()->clear();
        Async::metricsCollector()->reset();
        Async::metricsCollector()->setEnabled(true);
    }

    public function test_coro_resolves_and_rejects_without_unhandled_side_promises(): void
    {
        self::assertSame('ok', await(async(static fn (): string => 'ok')));

        $this->expectException(RuntimeException::class);
        await(async(static fn (): never => throw new RuntimeException('failed')));
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

    public function test_batch_and_waterfall_are_composable(): void
    {
        self::assertSame([2, 4, 6, 8], await(batch(range(1, 4), static fn (array $items): array => array_map(static fn (int $item): int => $item * 2, $items), 2, 1)));
        self::assertSame('start-1-2', await(waterfall([
            static fn (string $value): string => $value . '-1',
            static fn (string $value): string => $value . '-2',
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

    public function test_timeout_cancels_source_and_cancellable_runs_cleanup_once(): void
    {
        $source = new Deferred;
        $cancelled = false;
        $timed = Async::timeout($source->promise(), 0.001);
        $timed->catch(static function (\Throwable $error): void {});

        try {
            await($timed);
        } catch (TimeoutException) {
            $cancelled = true;
        }
        self::assertTrue($cancelled);

        $cleanupCalls = 0;
        $operation = cancellable(Async::delay(1), static function () use (&$cleanupCalls): void {
            $cleanupCalls++;
        });
        self::assertInstanceOf(PromiseInterface::class, $operation);
        $operation->cancel();
        $operation->cancel();
        self::assertSame(1, $cleanupCalls);
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
