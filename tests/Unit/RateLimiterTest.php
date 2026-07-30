<?php

declare(strict_types=1);

namespace Tests\Unit;

use Matrix\Support\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function test_invalid_configuration_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RateLimiter(0, 1.0);
    }
}
