<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Async;
use Matrix\Support\LoopManager;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use ReflectionClass;

class LoopManagerTest extends TestCase
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

    public function test_next_tick_delegates_to_loop(): void
    {
        $callback = function () {};

        $this->loopMock->expects($this->once())
            ->method('futureTick')
            ->with($this->identicalTo($callback));

        LoopManager::nextTick($callback);
    }

    public function test_delay_delegates_to_loop(): void
    {
        $seconds = 1.5;
        $callback = function () {};
        $timerMock = $this->createMock(TimerInterface::class);

        $this->loopMock->expects($this->once())
            ->method('addTimer')
            ->with($this->equalTo($seconds), $this->identicalTo($callback))
            ->willReturn($timerMock);

        $result = LoopManager::delay($seconds, $callback);

        $this->assertSame($timerMock, $result);
    }

    public function test_interval_delegates_to_loop(): void
    {
        $seconds = 2.0;
        $callback = function () {};
        $timerMock = $this->createMock(TimerInterface::class);

        $this->loopMock->expects($this->once())
            ->method('addPeriodicTimer')
            ->with($this->equalTo($seconds), $this->identicalTo($callback))
            ->willReturn($timerMock);

        $result = LoopManager::interval($seconds, $callback);

        $this->assertSame($timerMock, $result);
    }

    public function test_cancel_timer_delegates_to_loop(): void
    {
        $timerMock = $this->createMock(TimerInterface::class);

        $this->loopMock->expects($this->once())
            ->method('cancelTimer')
            ->with($this->identicalTo($timerMock));

        LoopManager::cancelTimer($timerMock);
    }

    public function test_get_loop_returns_loop_instance(): void
    {
        $result = LoopManager::getLoop();

        $this->assertSame($this->loopMock, $result);
    }

    public function test_run_delegates_to_loop(): void
    {
        $this->loopMock->expects($this->once())
            ->method('run');

        LoopManager::run();
    }

    public function test_stop_delegates_to_loop(): void
    {
        $this->loopMock->expects($this->once())
            ->method('stop');

        LoopManager::stop();
    }
}
