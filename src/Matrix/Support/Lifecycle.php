<?php

declare(strict_types=1);

namespace Matrix\Support;

use Matrix\Async;
use Matrix\Events\PromiseCreated;
use Matrix\Events\PromiseRejected;
use Matrix\Events\PromiseResolved;
use Matrix\Events\PromiseTimeout;
use React\Promise\PromiseInterface;

/** @internal Centralizes promise lifecycle instrumentation. */
final class Lifecycle
{
    private function __construct() {}

    public static function id(): string
    {
        return 'promise_' . bin2hex(random_bytes(12));
    }

    /** @param PromiseInterface<mixed> $promise @return PromiseInterface<mixed> */
    public static function track(PromiseInterface $promise, string $type, ?string $id = null): PromiseInterface
    {
        $id ??= self::id();
        $startedAt = microtime(true);

        Async::eventDispatcher()->dispatch(new PromiseCreated($id, $type));
        Async::metricsCollector()->promiseCreated($id, $type);

        return $promise->then(
            function (mixed $value) use ($id, $type, $startedAt): mixed {
                $duration = microtime(true) - $startedAt;
                Async::eventDispatcher()->dispatch(new PromiseResolved($id, $value, $duration, [
                    'operation_type' => $type,
                ]));
                Async::metricsCollector()->promiseResolved($id, $value);

                return $value;
            },
            function (\Throwable $reason) use ($id, $type, $startedAt): never {
                $duration = microtime(true) - $startedAt;
                Async::eventDispatcher()->dispatch(new PromiseRejected($id, $reason, $duration, [
                    'operation_type' => $type,
                ]));
                Async::metricsCollector()->promiseRejected($id, $reason);

                throw $reason;
            }
        );
    }

    public static function timeout(string $id, string $type, float $duration, string $message): void
    {
        Async::eventDispatcher()->dispatch(new PromiseTimeout($id, $duration, $message, [
            'operation_type' => $type,
        ]));
        Async::metricsCollector()->promiseTimeout($id, $duration);
    }
}
