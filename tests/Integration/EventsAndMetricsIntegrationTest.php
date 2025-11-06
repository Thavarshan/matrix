<?php

declare(strict_types=1);

namespace Tests\Integration;

use Matrix\Async;
use Matrix\Events\PromiseCreated;
use Matrix\Events\PromiseRejected;
use Matrix\Events\PromiseResolved;
use Tests\TestCase;

use function Matrix\Support\async;
use function Matrix\Support\await;
use function Matrix\Support\eventDispatcher;
use function Matrix\Support\getMetrics;
use function Matrix\Support\listen;
use function Matrix\Support\metricsCollector;

class EventsAndMetricsIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset events and metrics for each test
        Async::eventDispatcher()->clear();
        Async::metricsCollector()->reset();
    }

    public function test_async_operations_fire_events(): void
    {
        $promiseCreatedFired = false;
        $promiseResolvedFired = false;
        $createdEvent = null;
        $resolvedEvent = null;

        // Register event listeners
        listen('promise.created', function ($event) use (&$promiseCreatedFired, &$createdEvent) {
            $promiseCreatedFired = true;
            $createdEvent = $event;
        });

        listen('promise.resolved', function ($event) use (&$promiseResolvedFired, &$resolvedEvent) {
            $promiseResolvedFired = true;
            $resolvedEvent = $event;
        });

        // Execute async operation
        $result = await(async(function () {
            return 'test result';
        }));

        $this->assertEquals('test result', $result);
        $this->assertTrue($promiseCreatedFired);
        $this->assertTrue($promiseResolvedFired);
        $this->assertInstanceOf(PromiseCreated::class, $createdEvent);
        $this->assertInstanceOf(PromiseResolved::class, $resolvedEvent);
        $this->assertEquals('coro', $createdEvent->getType());
        $this->assertEquals('test result', $resolvedEvent->getValue());
    }

    public function test_rejected_promises_fire_events(): void
    {
        $promiseRejectedFired = false;
        $rejectedEvent = null;

        listen('promise.rejected', function ($event) use (&$promiseRejectedFired, &$rejectedEvent) {
            $promiseRejectedFired = true;
            $rejectedEvent = $event;
        });

        try {
            await(async(function () {
                throw new \Exception('Test error');
            }));
        } catch (\Exception $e) {
            // Expected
        }

        $this->assertTrue($promiseRejectedFired);
        $this->assertInstanceOf(PromiseRejected::class, $rejectedEvent);
        $this->assertEquals('Test error', $rejectedEvent->getErrorMessage());
        $this->assertEquals('Exception', $rejectedEvent->getErrorClass());
    }

    public function test_metrics_are_collected_for_async_operations(): void
    {
        // Execute some async operations
        await(async(function () {
            return 'result 1';
        }));

        await(async(function () {
            return 'result 2';
        }));

        try {
            await(async(function () {
                throw new \Exception('Test error');
            }));
        } catch (\Exception $e) {
            // Expected
        }

        $metrics = getMetrics();

        $this->assertEquals(0, $metrics['active_promises']);
        $this->assertEquals(3, $metrics['completed_promises']);
        $this->assertGreaterThan(0, $metrics['average_resolution_time']);

        // Check counters
        $this->assertEquals(3, $metrics['counters']['promises.created']);
        $this->assertEquals(3, $metrics['counters']['promises.created.coro']);
        $this->assertEquals(2, $metrics['counters']['promises.resolved']);
        $this->assertEquals(2, $metrics['counters']['promises.resolved.coro']);
        $this->assertEquals(1, $metrics['counters']['promises.rejected']);
        $this->assertEquals(1, $metrics['counters']['promises.rejected.coro']);
        $this->assertEquals(1, $metrics['counters']['errors.Exception']);

        // Success rate should be 66.67% (2 out of 3)
        $this->assertEqualsWithDelta(66.67, $metrics['success_rate'], 0.1);
    }

    public function test_event_dispatcher_helper_functions(): void
    {
        $dispatcher = eventDispatcher();
        $collector = metricsCollector();

        $this->assertInstanceOf(\Matrix\Events\EventDispatcher::class, $dispatcher);
        $this->assertInstanceOf(\Matrix\Metrics\MetricsCollector::class, $collector);

        // Test that they return the same instances as Async class
        $this->assertSame($dispatcher, Async::eventDispatcher());
        $this->assertSame($collector, Async::metricsCollector());
    }

    public function test_can_disable_events_and_metrics(): void
    {
        $eventFired = false;

        listen('promise.created', function () use (&$eventFired) {
            $eventFired = true;
        });

        // Disable events and metrics
        eventDispatcher()->setEnabled(false);
        metricsCollector()->setEnabled(false);

        await(async(function () {
            return 'test';
        }));

        $this->assertFalse($eventFired);
        $this->assertEquals(0, metricsCollector()->getActivePromiseCount());
        $this->assertEquals(0, metricsCollector()->getCompletedPromiseCount());

        // Re-enable for cleanup
        eventDispatcher()->setEnabled(true);
        metricsCollector()->setEnabled(true);
    }

    public function test_multiple_concurrent_operations_are_tracked(): void
    {
        $operations = [];

        // Start multiple concurrent operations
        for ($i = 0; $i < 5; $i++) {
            $operations[] = async(function () use ($i) {
                // Simulate some work
                usleep(10000); // 10ms

                return "result {$i}";
            });
        }

        // Wait for all to complete
        $results = [];
        foreach ($operations as $operation) {
            $results[] = await($operation);
        }

        $this->assertCount(5, $results);

        $metrics = getMetrics();
        $this->assertEquals(0, $metrics['active_promises']);
        $this->assertEquals(5, $metrics['completed_promises']);
        $this->assertEquals(5, $metrics['counters']['promises.created']);
        $this->assertEquals(5, $metrics['counters']['promises.resolved']);
        $this->assertEquals(100.0, $metrics['success_rate']);
    }
}
