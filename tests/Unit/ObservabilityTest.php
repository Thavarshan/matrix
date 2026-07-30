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
        Async::metricsCollector()->reset();
        Async::metricsCollector()->setEnabled(true);
    }

    public function test_lifecycle_events_share_an_operation_type(): void
    {
        $events = [];
        Async::eventDispatcher()->listen(PromiseCreated::NAME, static function ($event) use (&$events): void {
            $events[] = $event;
        });
        Async::eventDispatcher()->listen(PromiseResolved::NAME, static function ($event) use (&$events): void {
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
            self::fail('Expected operation failure.');
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
}
