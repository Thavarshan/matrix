<?php

declare(strict_types=1);

namespace Matrix\Metrics;

/** Bounded in-memory metrics for Matrix operations. */
final class MetricsCollector
{
    private const DEFAULT_SAMPLE_LIMIT = 1024;

    /** @var list<string> */
    private const OPERATION_TYPES = [
        'all', 'any', 'batch', 'cancellable', 'coro', 'delay', 'error_context',
        'map', 'pool', 'promise', 'race', 'rate_limit', 'reject', 'resolve',
        'retry', 'timeout', 'waterfall', 'other',
    ];

    /** @var list<string> */
    private const ERROR_TYPES = [
        'AsyncException', 'CancellationException', 'InvalidArgumentException',
        'LogicException', 'RuntimeException', 'RetryException', 'TimeoutException',
        'UnexpectedValueException',
    ];

    /** @var array<string, array{id: string, type: string, created_at: float, context: array<string, mixed>}> */
    private array $activePromises = [];

    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, list<float>> */
    private array $timings = [];

    private bool $enabled = true;

    private int $sampleLimit;

    public function __construct(int $sampleLimit = self::DEFAULT_SAMPLE_LIMIT)
    {
        if ($sampleLimit < 1) {
            throw new \InvalidArgumentException('Metrics sample limit must be positive.');
        }
        $this->sampleLimit = $sampleLimit;
    }

    /** @param array<string, mixed> $context */
    public function promiseCreated(string $promiseId, string $type = 'promise', array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }
        $type = in_array($type, self::OPERATION_TYPES, true) ? $type : 'other';
        $this->activePromises[$promiseId] = [
            'id'         => $promiseId,
            'type'       => $type,
            'created_at' => hrtime(true),
            'context'    => $context,
        ];
        $this->incrementCounter('promises.created');
        $this->incrementCounter("promises.created.{$type}");
    }

    public function promiseResolved(string $promiseId, mixed $value = null): void
    {
        if (! isset($this->activePromises[$promiseId])) {
            return;
        }
        $promise = $this->activePromises[$promiseId];
        $duration = (hrtime(true) - $promise['created_at']) / 1e9;
        unset($this->activePromises[$promiseId]);

        if (! $this->enabled) {
            return;
        }
        $this->incrementCounter('promises.resolved');
        $this->incrementCounter("promises.resolved.{$promise['type']}");
        $this->recordTiming('promise.duration', $duration);
        $this->recordTiming("promise.duration.{$promise['type']}", $duration);
    }

    public function promiseRejected(string $promiseId, \Throwable $reason): void
    {
        if (! isset($this->activePromises[$promiseId])) {
            return;
        }
        $promise = $this->activePromises[$promiseId];
        $duration = (hrtime(true) - $promise['created_at']) / 1e9;
        unset($this->activePromises[$promiseId]);

        if (! $this->enabled) {
            return;
        }
        $this->incrementCounter('promises.rejected');
        $this->incrementCounter("promises.rejected.{$promise['type']}");
        $errorType = $this->throwableType($reason);
        $errorType = in_array($errorType, self::ERROR_TYPES, true) ? $errorType : 'other';
        $this->incrementCounter('errors.' . $errorType);
        $this->recordTiming('promise.duration', $duration);
        $this->recordTiming("promise.duration.{$promise['type']}", $duration);
    }

    public function promiseTimeout(string $promiseId, float $timeoutDuration): void
    {
        if (! $this->enabled) {
            return;
        }
        $this->incrementCounter('promises.timeout');
        $this->recordTiming('promise.timeout.duration', $timeoutDuration);
    }

    public function getActivePromiseCount(): int
    {
        return count($this->activePromises);
    }

    public function getCompletedPromiseCount(): int
    {
        return $this->getCounter('promises.resolved') + $this->getCounter('promises.rejected');
    }

    public function getSuccessRate(): float
    {
        $resolved = $this->getCounter('promises.resolved');
        $rejected = $this->getCounter('promises.rejected');
        $total = $resolved + $rejected;

        return $total > 0 ? ($resolved / $total) * 100 : 0.0;
    }

    public function getAverageResolutionTime(): float
    {
        /** @var list<float> $timings */
        $timings = $this->timings['promise.duration'] ?? [];

        return $timings === [] ? 0.0 : array_sum($timings) / count($timings);
    }

    /**
     * @return array{
     *     active_promises: int,
     *     completed_promises: int,
     *     success_rate: float,
     *     average_resolution_time: float,
     *     counters: array<string, int>,
     *     timings: array<string, array{count: int, sum: float, avg: float, min: float, max: float, p50: float, p95: float, p99: float}>
     * }
     */
    public function getMetrics(): array
    {
        return [
            'active_promises'         => $this->getActivePromiseCount(),
            'completed_promises'      => $this->getCompletedPromiseCount(),
            'success_rate'            => $this->getSuccessRate(),
            'average_resolution_time' => $this->getAverageResolutionTime(),
            'counters'                => $this->counters,
            'timings'                 => $this->getTimingSummary(),
        ];
    }

    public function getCounter(string $key): int
    {
        return $this->counters[$key] ?? 0;
    }

    public function reset(): void
    {
        $this->activePromises = [];
        $this->counters = [];
        $this->timings = [];
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    private function incrementCounter(string $key, int $amount = 1): void
    {
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $amount;
    }

    private function recordTiming(string $key, float $value): void
    {
        $this->timings[$key] ??= [];
        $this->timings[$key][] = $value;

        if (count($this->timings[$key]) > $this->sampleLimit) {
            array_shift($this->timings[$key]);
        }
    }

    private function throwableType(\Throwable $reason): string
    {
        $class = get_class($reason);
        $separator = strrpos($class, '\\');

        return $separator === false ? $class : substr($class, $separator + 1);
    }

    /**
     * @return array<string, array{count: int, sum: float, avg: float, min: float, max: float, p50: float, p95: float, p99: float}>
     */
    private function getTimingSummary(): array
    {
        $summary = [];

        foreach ($this->timings as $key => $values) {
            if ($values === []) {
                continue;
            }
            sort($values);
            $count = count($values);
            $sum = array_sum($values);
            $percentile = static fn (float $p): float => $values[max(0, (int) ceil($p * $count) - 1)];
            $summary[$key] = [
                'count' => $count,
                'sum'   => $sum,
                'avg'   => $sum / $count,
                'min'   => $values[0],
                'max'   => $values[$count - 1],
                'p50'   => $percentile(0.50),
                'p95'   => $percentile(0.95),
                'p99'   => $percentile(0.99),
            ];
        }

        return $summary;
    }
}
