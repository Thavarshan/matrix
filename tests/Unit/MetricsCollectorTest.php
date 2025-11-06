<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Metrics\MetricsCollector;
use PHPUnit\Framework\TestCase;

class MetricsCollectorTest extends TestCase
{
    private MetricsCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new MetricsCollector;
    }

    public function test_can_track_promise_creation(): void
    {
        $this->collector->promiseCreated('test-promise-1', 'test');

        $this->assertEquals(1, $this->collector->getActivePromiseCount());
        $this->assertEquals(0, $this->collector->getCompletedPromiseCount());
        $this->assertEquals(1, $this->collector->getCounter('promises.created'));
        $this->assertEquals(1, $this->collector->getCounter('promises.created.test'));
    }

    public function test_can_track_promise_resolution(): void
    {
        $this->collector->promiseCreated('test-promise-1', 'test');
        $this->collector->promiseResolved('test-promise-1', 'result');

        $this->assertEquals(0, $this->collector->getActivePromiseCount());
        $this->assertEquals(1, $this->collector->getCompletedPromiseCount());
        $this->assertEquals(1, $this->collector->getCounter('promises.resolved'));
        $this->assertEquals(1, $this->collector->getCounter('promises.resolved.test'));
    }

    public function test_can_track_promise_rejection(): void
    {
        $exception = new \Exception('Test error');

        $this->collector->promiseCreated('test-promise-1', 'test');
        $this->collector->promiseRejected('test-promise-1', $exception);

        $this->assertEquals(0, $this->collector->getActivePromiseCount());
        $this->assertEquals(1, $this->collector->getCompletedPromiseCount());
        $this->assertEquals(1, $this->collector->getCounter('promises.rejected'));
        $this->assertEquals(1, $this->collector->getCounter('promises.rejected.test'));
        $this->assertEquals(1, $this->collector->getCounter('errors.Exception'));
    }

    public function test_can_track_promise_timeout(): void
    {
        $this->collector->promiseTimeout('test-promise-1', 5.0);

        $this->assertEquals(1, $this->collector->getCounter('promises.timeout'));
    }

    public function test_calculates_success_rate(): void
    {
        $exception = new \Exception('Test error');

        // Create and resolve 3 promises
        for ($i = 1; $i <= 3; $i++) {
            $this->collector->promiseCreated("promise-{$i}", 'test');
            $this->collector->promiseResolved("promise-{$i}", 'result');
        }

        // Create and reject 1 promise
        $this->collector->promiseCreated('promise-4', 'test');
        $this->collector->promiseRejected('promise-4', $exception);

        $this->assertEquals(75.0, $this->collector->getSuccessRate());
    }

    public function test_calculates_average_resolution_time(): void
    {
        // Simulate different resolution times by manually setting timings
        $this->collector->promiseCreated('promise-1', 'test');
        sleep(1); // Wait 1 second
        $this->collector->promiseResolved('promise-1', 'result');

        $avgTime = $this->collector->getAverageResolutionTime();
        $this->assertGreaterThan(0.9, $avgTime); // Should be around 1 second
        $this->assertLessThan(1.1, $avgTime);
    }

    public function test_can_reset_metrics(): void
    {
        $this->collector->promiseCreated('test-promise-1', 'test');
        $this->collector->promiseResolved('test-promise-1', 'result');

        $this->assertEquals(1, $this->collector->getCompletedPromiseCount());

        $this->collector->reset();

        $this->assertEquals(0, $this->collector->getActivePromiseCount());
        $this->assertEquals(0, $this->collector->getCompletedPromiseCount());
        $this->assertEquals(0, $this->collector->getCounter('promises.created'));
    }

    public function test_can_disable_metrics_collection(): void
    {
        $this->collector->setEnabled(false);
        $this->collector->promiseCreated('test-promise-1', 'test');

        $this->assertEquals(0, $this->collector->getActivePromiseCount());
        $this->assertFalse($this->collector->isEnabled());
    }

    public function test_get_metrics_returns_complete_data(): void
    {
        $exception = new \Exception('Test error');

        $this->collector->promiseCreated('promise-1', 'test');
        $this->collector->promiseResolved('promise-1', 'result');

        $this->collector->promiseCreated('promise-2', 'test');
        $this->collector->promiseRejected('promise-2', $exception);

        $metrics = $this->collector->getMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('active_promises', $metrics);
        $this->assertArrayHasKey('completed_promises', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);
        $this->assertArrayHasKey('average_resolution_time', $metrics);
        $this->assertArrayHasKey('counters', $metrics);
        $this->assertArrayHasKey('timings', $metrics);

        $this->assertEquals(0, $metrics['active_promises']);
        $this->assertEquals(2, $metrics['completed_promises']);
        $this->assertEquals(50.0, $metrics['success_rate']);
    }
}
