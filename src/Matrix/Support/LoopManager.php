<?php

declare(strict_types=1);

namespace Matrix\Support;

use Matrix\Async;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/** @internal Thin loop adapter used by Matrix schedulers. */
final class LoopManager
{
    private static bool $running = false;

    public static function nextTick(callable $callback): void
    {
        Async::loop()->futureTick($callback);
    }

    public static function delay(float $seconds, callable $callback): TimerInterface
    {
        return Async::loop()->addTimer($seconds, $callback);
    }

    public static function interval(float $seconds, callable $callback): TimerInterface
    {
        return Async::loop()->addPeriodicTimer($seconds, $callback);
    }

    public static function cancelTimer(TimerInterface $timer): void
    {
        Async::loop()->cancelTimer($timer);
    }

    public static function getLoop(): LoopInterface
    {
        return Async::loop();
    }

    public static function run(): void
    {
        if (self::$running) {
            throw new \LogicException('The Matrix event loop is already running.');
        }
        self::$running = true;

        try {
            Async::loop()->run();
        } finally {
            self::$running = false;
        }
    }

    public static function isRunning(): bool
    {
        return self::$running;
    }

    public static function stop(): void
    {
        Async::loop()->stop();
    }
}
