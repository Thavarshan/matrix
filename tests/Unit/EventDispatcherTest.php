<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Events\EventDispatcher;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher;
    }

    public function test_can_register_and_fire_listeners(): void
    {
        $called = false;
        $receivedEvent = null;

        $this->dispatcher->listen('test.event', function ($event) use (&$called, &$receivedEvent) {
            $called = true;
            $receivedEvent = $event;
        });

        $this->dispatcher->fire('test.event', ['key' => 'value']);

        $this->assertTrue($called);
        $this->assertNotNull($receivedEvent);
        $this->assertEquals('test.event', $receivedEvent->getName());
        $this->assertEquals(['key' => 'value'], $receivedEvent->getData());
    }

    public function test_can_register_multiple_listeners(): void
    {
        $called1 = false;
        $called2 = false;

        $this->dispatcher->listen('test.event', function () use (&$called1) {
            $called1 = true;
        });

        $this->dispatcher->listen('test.event', function () use (&$called2) {
            $called2 = true;
        });

        $this->dispatcher->fire('test.event');

        $this->assertTrue($called1);
        $this->assertTrue($called2);
    }

    public function test_can_remove_listener(): void
    {
        $called = false;
        $listener = function () use (&$called) {
            $called = true;
        };

        $this->dispatcher->listen('test.event', $listener);
        $this->dispatcher->removeListener('test.event', $listener);
        $this->dispatcher->fire('test.event');

        $this->assertFalse($called);
    }

    public function test_can_remove_all_listeners(): void
    {
        $called1 = false;
        $called2 = false;

        $this->dispatcher->listen('test.event', function () use (&$called1) {
            $called1 = true;
        });

        $this->dispatcher->listen('test.event', function () use (&$called2) {
            $called2 = true;
        });

        $this->dispatcher->removeAllListeners('test.event');
        $this->dispatcher->fire('test.event');

        $this->assertFalse($called1);
        $this->assertFalse($called2);
    }

    public function test_can_disable_events(): void
    {
        $called = false;

        $this->dispatcher->listen('test.event', function () use (&$called) {
            $called = true;
        });

        $this->dispatcher->setEnabled(false);
        $this->dispatcher->fire('test.event');

        $this->assertFalse($called);
        $this->assertFalse($this->dispatcher->isEnabled());
    }

    public function test_listener_exceptions_are_caught(): void
    {
        $called = false;

        $this->dispatcher->listen('test.event', function () {
            throw new \Exception('Test exception');
        });

        $this->dispatcher->listen('test.event', function () use (&$called) {
            $called = true;
        });

        // Should not throw, and second listener should still be called
        $this->dispatcher->fire('test.event');
        $this->assertTrue($called);
    }
}
