<?php

declare(strict_types=1);

namespace Matrix\Support;

/** @internal Mutable state shared with synchronous promise callbacks. */
final class OperationState implements OperationStateInterface
{
    /** @var \Closure(): bool */
    private \Closure $read;

    /** @var \Closure(): void */
    private \Closure $write;

    public function __construct()
    {
        $settled = false;
        $this->read = static function () use (&$settled): bool {
            return $settled;
        };
        $this->write = static function () use (&$settled): void {
            $settled = true;
        };
    }

    public static function create(): OperationStateInterface
    {
        return new self;
    }

    public function isSettled(): bool
    {
        return ($this->read)();
    }

    public function settle(): void
    {
        ($this->write)();
    }
}
