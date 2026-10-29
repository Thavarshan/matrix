<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Async;
use Matrix\Events\PromiseCreated;
use Matrix\Events\PromiseRejected;
use Matrix\Events\PromiseResolved;
use Matrix\Metrics\MetricsCollector;

use function Matrix\Support\async;
use function Matrix\Support\await;

use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ObservabilityTest extends TestCase
{
    protected function setUp(): void
    {
        Async::eventDispatcher()->clear();
        Async::eventDispatcher()->setErrorHandler(null);
        Async::metricsCollector()->reset();
        Async::metricsCollector()->setEnabled(true);
    }

    public function test_lifecycle_events_share_an_operation_type(): void
    {
        $events = [];
        Async::eventDispatcher()->listen(PromiseCreated::NAME, static function (PromiseCreated $event) use (&$events): void {
            $events[] = $event;
        });
        Async::eventDispatcher()->listen(PromiseResolved::NAME, static function (PromiseResolved $event) use (&$events): void {
            $events[] = $event;
        });

        self::assertSame('value', await(async(static fn (): string => 'value')));
        self::assertCount(2, $events);
        self::assertSame($events[0]->getPromiseId(), $events[1]->getPromiseId());
        self::assertSame('coro', $events[1]->getOperationType());
    }

    public function test_listener_exceptions_do_not_break_operations(): void
    {
        Async::eventDispatcher()->listen(PromiseRejected::NAME, static function (): never {
            throw new RuntimeException('observer');
        });

        try {
            await(async(static fn (): never => throw new RuntimeException('operation')));
        } catch (RuntimeException $exception) {
            self::assertSame('operation', $exception->getMessage());
        }
    }

    public function test_metrics_keep_cumulative_counts_with_bounded_samples(): void
    {
        $metrics = new MetricsCollector(3);

        for ($i = 0; $i < 5; $i++) {
            $id = 'id-' . $i;
            $metrics->promiseCreated($id, 'test');
            $metrics->promiseResolved($id, $i);
        }
        $data = $metrics->getMetrics();
        self::assertSame(5, $data['completed_promises']);
        self::assertSame(5, $data['counters']['promises.resolved']);
        self::assertSame(3, $data['timings']['promise.duration']['count']);
    }

    public function test_disabling_metrics_does_not_leave_active_promises(): void
    {
        $metrics = new MetricsCollector;
        $metrics->promiseCreated('work', 'map');
        $metrics->setEnabled(false);
        $metrics->promiseResolved('work');
        $metrics->promiseCreated('failed', 'pool');
        $metrics->promiseRejected('failed', new RuntimeException('failed'));
        $metrics->setEnabled(true);

        self::assertSame(0, $metrics->getActivePromiseCount());
        self::assertSame(0, $metrics->getCompletedPromiseCount());
    }

    public function test_unknown_operation_types_are_grouped(): void
    {
        $metrics = new MetricsCollector(1);

        for ($i = 0; $i < 100; $i++) {
            $id = 'id-' . $i;
            $metrics->promiseCreated($id, 'tenant-' . $i);
            $metrics->promiseResolved($id);
        }

        self::assertSame(100, $metrics->getCounter('promises.created.other'));
        self::assertCount(2, $metrics->getMetrics()['timings']);
    }

    public function test_listener_error_handler_isolated_from_operation(): void
    {
        $reported = [];
        Async::eventDispatcher()->setErrorHandler(static function (\Throwable $error) use (&$reported): void {
            $reported[] = $error->getMessage();
        });
        Async::eventDispatcher()->listen(PromiseResolved::NAME, static function (): never {
            throw new RuntimeException('observer broke');
        });

        self::assertSame('value', await(async(static fn (): string => 'value')));
        self::assertContains('observer broke', $reported);
    }
}
