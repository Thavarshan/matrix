<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Async;
use Matrix\Support\RateLimiter;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ReflectionClass;

class RateLimiterTest extends TestCase
{
    /**
     * @var LoopInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $loopMock;

    protected function setUp(): void
    {
        $this->loopMock = $this->createMock(LoopInterface::class);

        // Create a reflection to access the private static property of Async
        $asyncReflection = new ReflectionClass(Async::class);
        $loopProperty = $asyncReflection->getProperty('loop');
        $loopProperty->setAccessible(true);
        $loopProperty->setValue(null, $this->loopMock);
    }

    protected function tearDown(): void
    {
        // Reset the loop property after each test
        $asyncReflection = new ReflectionClass(Async::class);
        $loopProperty = $asyncReflection->getProperty('loop');
        $loopProperty->setAccessible(true);
        $loopProperty->setValue(null, null);
    }

    public function test_create_returns_rate_limiter_instance(): void
    {
        $rateLimiter = RateLimiter::create(10, 1.0);

        $this->assertInstanceOf(RateLimiter::class, $rateLimiter);

        // Test the constructor parameters are set correctly using reflection
        $reflection = new ReflectionClass($rateLimiter);

        $maxCallsProperty = $reflection->getProperty('maxCalls');
        $maxCallsProperty->setAccessible(true);
        $this->assertEquals(10, $maxCallsProperty->getValue($rateLimiter));

        $periodProperty = $reflection->getProperty('period');
        $periodProperty->setAccessible(true);
        $this->assertEquals(1.0, $periodProperty->getValue($rateLimiter));
    }

    public function test_limit_returns_callable(): void
    {
        $rateLimiter = new RateLimiter(10, 1.0);
        $fn = function (): PromiseInterface {
            $deferred = new Deferred;
            $deferred->resolve('result');

            return $deferred->promise();
        };

        $limited = $rateLimiter->limit($fn);

        $this->assertTrue(is_callable($limited));
    }

    public function test_limited_function_queues_call_and_starts_processing(): void
    {
        $rateLimiter = new RateLimiter(2, 1.0);
        $fn = function (...$args): PromiseInterface {
            $deferred = new Deferred;
            $deferred->resolve(implode('-', $args));

            return $deferred->promise();
        };

        // Mock the future tick to capture the queue processing
        $processQueueCallback = null;
        $this->loopMock->expects($this->once())
            ->method('futureTick')
            ->willReturnCallback(function (callable $callback) use (&$processQueueCallback) {
                $processQueueCallback = $callback;

                return null;
            });

        $limited = $rateLimiter->limit($fn);

        // Queue should be empty initially
        $reflection = new ReflectionClass($rateLimiter);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setAccessible(true);
        $this->assertEmpty($queueProperty->getValue($rateLimiter));

        // Call the limited function
        $promise = $limited('test', 123);

        // Queue should now have one item
        $queue = $queueProperty->getValue($rateLimiter);
        $this->assertCount(1, $queue);
        $this->assertEquals(['test', 123], $queue[0][0]); // Arguments
        $this->assertInstanceOf(Deferred::class, $queue[0][1]); // Deferred
        $this->assertSame($fn, $queue[0][2]); // Original function

        // Processing flag should be true
        $processingProperty = $reflection->getProperty('processing');
        $processingProperty->setAccessible(true);
        $this->assertTrue($processingProperty->getValue($rateLimiter));

        // Verify the promise is returned
        $this->assertInstanceOf(PromiseInterface::class, $promise);
    }

    public function test_process_queue_step_executes_items_within_rate_limit(): void
    {
        $rateLimiter = new RateLimiter(2, 1.0);

        // Create a reflection of RateLimiter to access private properties/methods
        $reflection = new ReflectionClass($rateLimiter);

        // Set up test data
        $executed = [];
        $fn1 = function ($id) use (&$executed): PromiseInterface {
            $deferred = new Deferred;
            $executed[] = $id;
            $deferred->resolve($id);

            return $deferred->promise();
        };

        $fn2 = function ($id) use (&$executed): PromiseInterface {
            $deferred = new Deferred;
            $executed[] = $id;
            $deferred->resolve($id);

            return $deferred->promise();
        };

        $fn3 = function ($id) use (&$executed): PromiseInterface {
            $deferred = new Deferred;
            $executed[] = $id;
            $deferred->resolve($id);

            return $deferred->promise();
        };

        // Set up the queue
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setAccessible(true);

        $deferred1 = new Deferred;
        $deferred2 = new Deferred;
        $deferred3 = new Deferred;

        $queueProperty->setValue($rateLimiter, [
            [[1], $deferred1, $fn1],
            [[2], $deferred2, $fn2],
            [[3], $deferred3, $fn3],
        ]);

        // Set processing flag
        $processingProperty = $reflection->getProperty('processing');
        $processingProperty->setAccessible(true);
        $processingProperty->setValue($rateLimiter, true);

        // Create empty time window
        $timeWindowProperty = $reflection->getProperty('timeWindow');
        $timeWindowProperty->setAccessible(true);
        $timeWindowProperty->setValue($rateLimiter, []);

        // The limiter should schedule another processing step for remaining queue items
        $timerCallback = null;
        $this->loopMock->expects($this->once())
            ->method('addTimer')
            ->with(
                $this->equalTo(0.5), // period/maxCalls = 1.0/2
                $this->callback(function ($callback) use (&$timerCallback) {
                    $timerCallback = $callback;

                    return true;
                })
            );

        // Call processQueueStep
        $processQueueStepMethod = $reflection->getMethod('processQueueStep');
        $processQueueStepMethod->setAccessible(true);
        $processQueueStepMethod->invoke($rateLimiter);

        // Check that the functions were executed
        $this->assertEquals([1, 2], $executed);

        // Verify that the first 2 items were executed (within rate limit)
        $this->assertCount(2, $timeWindowProperty->getValue($rateLimiter)); // Two timestamps added
        $this->assertCount(1, $queueProperty->getValue($rateLimiter)); // One item still in queue

        // Call the scheduled timer callback to process the remaining item
        $timeWindowProperty->setValue($rateLimiter, []); // Reset time window to simulate time passing
        $timerCallback();

        // Check that the remaining function was executed
        $this->assertEquals([1, 2, 3], $executed);

        // Verify all items have been processed
        $this->assertCount(1, $timeWindowProperty->getValue($rateLimiter)); // One more timestamp added
        $this->assertEmpty($queueProperty->getValue($rateLimiter)); // Queue should be empty
        $this->assertFalse($processingProperty->getValue($rateLimiter)); // Processing flag should be reset
    }
}
