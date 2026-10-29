<?php

declare(strict_types=1);

namespace Matrix\Support;

/** @internal Mutable completion state shared across promise callbacks. */
interface OperationStateInterface
{
    public function isSettled(): bool;

    public function settle(): void;
}
