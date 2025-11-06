<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Events\PromiseCreated;
use Matrix\Events\PromiseRejected;
use Matrix\Events\PromiseResolved;
use Matrix\Events\PromiseTimeout;
use PHPUnit\Framework\TestCase;

class EventClassesTest extends TestCase
{
    public function test_promise_created_event(): void
    {
        $event = new PromiseCreated('test-promise-1', 'test', ['context' => 'value']);

        $this->assertEquals('promise.created', $event->getName());
        $this->assertEquals('test-promise-1', $event->getPromiseId());
        $this->assertEquals('test', $event->getType());
        $this->assertEquals('value', $event->get('context'));
        $this->assertIsFloat($event->getTimestamp());
    }

    public function test_promise_resolved_event(): void
    {
        $event = new PromiseResolved('test-promise-1', 'result', 1.5);

        $this->assertEquals('promise.resolved', $event->getName());
        $this->assertEquals('test-promise-1', $event->getPromiseId());
        $this->assertEquals('result', $event->getValue());
        $this->assertEquals(1.5, $event->getDuration());
        $this->assertIsFloat($event->getTimestamp());
    }

    public function test_promise_rejected_event(): void
    {
        $exception = new \Exception('Test error');
        $event = new PromiseRejected('test-promise-1', $exception, 2.0);

        $this->assertEquals('promise.rejected', $event->getName());
        $this->assertEquals('test-promise-1', $event->getPromiseId());
        $this->assertSame($exception, $event->getReason());
        $this->assertEquals(2.0, $event->getDuration());
        $this->assertEquals('Test error', $event->getErrorMessage());
        $this->assertEquals('Exception', $event->getErrorClass());
        $this->assertIsFloat($event->getTimestamp());
    }

    public function test_promise_timeout_event(): void
    {
        $event = new PromiseTimeout('test-promise-1', 5.0, 'Custom timeout message');

        $this->assertEquals('promise.timeout', $event->getName());
        $this->assertEquals('test-promise-1', $event->getPromiseId());
        $this->assertEquals(5.0, $event->getTimeoutDuration());
        $this->assertEquals('Custom timeout message', $event->getMessage());
        $this->assertIsFloat($event->getTimestamp());
    }

    public function test_promise_timeout_event_with_default_message(): void
    {
        $event = new PromiseTimeout('test-promise-1', 3.0);

        $this->assertEquals('Operation timed out', $event->getMessage());
    }

    public function test_events_can_set_and_get_data(): void
    {
        $event = new PromiseCreated('test-promise-1');

        $event->set('custom_key', 'custom_value');
        $this->assertEquals('custom_value', $event->get('custom_key'));
        $this->assertEquals('default', $event->get('nonexistent', 'default'));
    }
}
