<?php

declare(strict_types=1);

namespace Matrix\Metrics;

/** Bounded in-memory metrics for Matrix operations. */
final class MetricsCollector
{
    private const DEFAULT_SAMPLE_LIMIT = 1024;

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
        $this->activePromises[$promiseId] = [
            'id'         => $promiseId,
            'type'       => $type,
            'created_at' => microtime(true),
            'context'    => $context,
        ];
        $this->incrementCounter('promises.created');
        $this->incrementCounter("promises.created.{$type}");
    }

    public function promiseResolved(string $promiseId, mixed $value = null): void
    {
        if (! $this->enabled || ! isset($this->activePromises[$promiseId])) {
            return;
        }
        $promise = $this->activePromises[$promiseId];
        $duration = microtime(true) - $promise['created_at'];
        unset($this->activePromises[$promiseId]);
        $this->incrementCounter('promises.resolved');
        $this->incrementCounter("promises.resolved.{$promise['type']}");
        $this->recordTiming('promise.duration', $duration);
        $this->recordTiming("promise.duration.{$promise['type']}", $duration);
    }

    public function promiseRejected(string $promiseId, \Throwable $reason): void
    {
        if (! $this->enabled || ! isset($this->activePromises[$promiseId])) {
            return;
        }
        $promise = $this->activePromises[$promiseId];
        $duration = microtime(true) - $promise['created_at'];
        unset($this->activePromises[$promiseId]);
        $this->incrementCounter('promises.rejected');
        $this->incrementCounter("promises.rejected.{$promise['type']}");
        $this->incrementCounter('errors.' . get_class($reason));
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

    /** @return array<string, mixed> */
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

    /** @return array<string, array<string, float>> */
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
