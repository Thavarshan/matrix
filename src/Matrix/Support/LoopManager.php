<?php

declare(strict_types=1);

namespace Matrix\Support;

use Matrix\Async;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * Utility class for managing the event loop.
 */
class LoopManager
{
    /**
     * Schedule a callback to run on the next tick of the event loop.
     *
     * @param  callable  $callback  The callback to execute
     */
    public static function nextTick(callable $callback): void
    {
        Async::loop()->futureTick($callback);
    }

    /**
     * Schedule a callback to run after a delay.
     *
     * @param  float  $seconds  The delay in seconds
     * @param  callable  $callback  The callback to execute
     * @return TimerInterface The timer
     */
    public static function delay(float $seconds, callable $callback): TimerInterface
    {
        return Async::loop()->addTimer($seconds, $callback);
    }

    /**
     * Schedule a callback to run periodically.
     *
     * @param  float  $seconds  The interval in seconds
     * @param  callable  $callback  The callback to execute
     * @return TimerInterface The timer
     */
    public static function interval(float $seconds, callable $callback): TimerInterface
    {
        return Async::loop()->addPeriodicTimer($seconds, $callback);
    }

    /**
     * Cancel a timer.
     *
     * @param  TimerInterface  $timer  The timer to cancel
     */
    public static function cancelTimer(TimerInterface $timer): void
    {
        Async::loop()->cancelTimer($timer);
    }

    /**
     * Get the current event loop.
     *
     * @return LoopInterface The event loop
     */
    public static function getLoop(): LoopInterface
    {
        return Async::loop();
    }

    /**
     * Run the event loop until no pending operations remain.
     */
    public static function run(): void
    {
        Async::loop()->run();
    }

    /**
     * Stop the event loop.
     */
    public static function stop(): void
    {
        Async::loop()->stop();
    }
}
