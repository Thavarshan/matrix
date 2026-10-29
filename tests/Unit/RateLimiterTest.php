<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Async;
use Matrix\Exceptions\CancellationException;

use function Matrix\Support\await;

use Matrix\Support\RateLimiter;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        Async::metricsCollector()->reset();
        Async::metricsCollector()->setEnabled(true);
    }

    public function test_invalid_configuration_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimiter(0, 1.0);
    }

    public function test_sequential_calls_run_after_the_queue_drains(): void
    {
        $limited = Async::rateLimit(static fn (string $value): string => strtoupper($value), 1, 0.001);

        self::assertSame('FIRST', await($limited('first'), 0.2));
        self::assertSame('SECOND', await($limited('second'), 0.2));
        self::assertSame('THIRD', await($limited('third'), 0.2));

        $direct = (new RateLimiter(1, 0.001))->limit(static fn (int $value): int => $value * 2);
        $helper = \Matrix\Support\rateLimit(static fn (string $value): string => strtoupper($value), 1, 0.001);
        self::assertSame(4, await($direct(2), 0.2));
        self::assertSame('HELPER', await($helper('helper'), 0.2));
    }

    public function test_queue_resumes_when_callback_runs_longer_than_the_period(): void
    {
        $started = 0;
        $limited = (new RateLimiter(1, 0.001))->limit(static function () use (&$started): int {
            $started++;
            usleep(3000);

            return $started;
        });

        self::assertSame([1, 2], await(Async::all([$limited(), $limited()]), 0.2));
        self::assertSame(2, $started);
    }

    public function test_cancelling_queued_call_removes_its_work(): void
    {
        $limiter = (new RateLimiter(1, 10))->limit(static fn (): string => 'started');
        self::assertSame('started', await($limiter()));

        $queued = $limiter();
        $result = $queued->catch(static fn (\Throwable $error): string => $error::class);
        $queued->cancel();

        self::assertSame(CancellationException::class, await($result));
        self::assertSame(0, Async::metricsCollector()->getActivePromiseCount());
    }

    public function test_nonfinite_period_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimiter(1, INF);
    }

    public function test_cancelling_queued_call_releases_its_arguments(): void
    {
        $limiter = new RateLimiter(1, 10);
        $limited = $limiter->limit(static fn (mixed ...$args): string => 'done');
        await($limited());

        $queued = $limited(new \stdClass);
        $queued->catch(static fn (\Throwable $error): null => null);
        $queued->cancel();

        $queue = (new \ReflectionProperty(RateLimiter::class, 'queue'))->getValue($limiter);
        self::assertSame([], $queue);
    }

    public function test_cancellation_inside_limited_callback_cancels_its_source(): void
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
        $limited = (new RateLimiter(1, 1))->limit(static function (mixed ...$args) use ($holder, $source): PromiseInterface {
            $holder->operation?->cancel();

            return $source->promise();
        });
        $holder->operation = $limited();
        $result = $holder->operation->catch(static fn (\Throwable $error): string => $error::class);

        self::assertSame(CancellationException::class, await($result));
        self::assertSame(1, $cancelCalls);
    }
}
