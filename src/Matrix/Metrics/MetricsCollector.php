<?php

declare(strict_types=1);

namespace Matrix\Metrics;

/**
 * Metrics collector for tracking async operation performance.
 */
class MetricsCollector
{
    /**
     * @var array<string, mixed> Active promises being tracked
     */
    private array $activePromises = [];

    /**
     * @var array<string, mixed> Completed promise metrics
     */
    private array $completedPromises = [];

    /**
     * @var array<string, int> Operation counters
     */
    private array $counters = [];

    /**
     * @var array<string, float> Timing data
     */
    private array $timings = [];

    /**
     * @var bool Whether metrics collection is enabled
     */
    private bool $enabled = true;

    /**
     * Track a promise creation.
     *
     * @param  array<string, mixed>  $context
     */
    public function promiseCreated(string $promiseId, string $type = 'promise', array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->activePromises[$promiseId] = [
            'id' => $promiseId,
            'type' => $type,
            'created_at' => microtime(true),
            'context' => $context,
        ];

        $this->incrementCounter('promises.created');
        $this->incrementCounter("promises.created.{$type}");
    }

    /**
     * Track a promise resolution.
     */
    public function promiseResolved(string $promiseId, mixed $value = null): void
    {
        if (! $this->enabled || ! isset($this->activePromises[$promiseId])) {
            return;
        }

        $promise = $this->activePromises[$promiseId];
        $duration = microtime(true) - $promise['created_at'];

        $this->completedPromises[$promiseId] = array_merge($promise, [
            'resolved_at' => microtime(true),
            'duration' => $duration,
            'status' => 'resolved',
            'value_type' => gettype($value),
        ]);

        unset($this->activePromises[$promiseId]);

        $this->incrementCounter('promises.resolved');
        $this->incrementCounter("promises.resolved.{$promise['type']}");
        $this->recordTiming('promise.duration', $duration);
        $this->recordTiming("promise.duration.{$promise['type']}", $duration);
    }

    /**
     * Track a promise rejection.
     */
    public function promiseRejected(string $promiseId, \Throwable $reason): void
    {
        if (! $this->enabled || ! isset($this->activePromises[$promiseId])) {
            return;
        }

        $promise = $this->activePromises[$promiseId];
        $duration = microtime(true) - $promise['created_at'];

        $this->completedPromises[$promiseId] = array_merge($promise, [
            'rejected_at' => microtime(true),
            'duration' => $duration,
            'status' => 'rejected',
            'error_class' => get_class($reason),
            'error_message' => $reason->getMessage(),
        ]);

        unset($this->activePromises[$promiseId]);

        $this->incrementCounter('promises.rejected');
        $this->incrementCounter("promises.rejected.{$promise['type']}");
        $this->incrementCounter('errors.'.get_class($reason));
        $this->recordTiming('promise.duration', $duration);
        $this->recordTiming("promise.duration.{$promise['type']}", $duration);
    }

    /**
     * Track a promise timeout.
     */
    public function promiseTimeout(string $promiseId, float $timeoutDuration): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->incrementCounter('promises.timeout');
        $this->recordTiming('promise.timeout.duration', $timeoutDuration);
    }

    /**
     * Get the number of active promises.
     */
    public function getActivePromiseCount(): int
    {
        return count($this->activePromises);
    }

    /**
     * Get the total number of completed promises.
     */
    public function getCompletedPromiseCount(): int
    {
        return count($this->completedPromises);
    }

    /**
     * Get the success rate as a percentage.
     */
    public function getSuccessRate(): float
    {
        $resolved = $this->getCounter('promises.resolved');
        $rejected = $this->getCounter('promises.rejected');
        $total = $resolved + $rejected;

        return $total > 0 ? ($resolved / $total) * 100 : 0.0;
    }

    /**
     * Get the average promise resolution time in seconds.
     */
    public function getAverageResolutionTime(): float
    {
        $timings = $this->timings['promise.duration'] ?? [];

        return count($timings) > 0 ? array_sum($timings) / count($timings) : 0.0;
    }

    /**
     * Get all metrics data.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return [
            'active_promises' => $this->getActivePromiseCount(),
            'completed_promises' => $this->getCompletedPromiseCount(),
            'success_rate' => $this->getSuccessRate(),
            'average_resolution_time' => $this->getAverageResolutionTime(),
            'counters' => $this->counters,
            'timings' => $this->getTimingSummary(),
        ];
    }

    /**
     * Get a specific counter value.
     */
    public function getCounter(string $key): int
    {
        return $this->counters[$key] ?? 0;
    }

    /**
     * Reset all metrics.
     */
    public function reset(): void
    {
        $this->activePromises = [];
        $this->completedPromises = [];
        $this->counters = [];
        $this->timings = [];
    }

    /**
     * Enable or disable metrics collection.
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    /**
     * Check if metrics collection is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Increment a counter.
     */
    private function incrementCounter(string $key, int $amount = 1): void
    {
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $amount;
    }

    /**
     * Record a timing value.
     */
    private function recordTiming(string $key, float $value): void
    {
        if (! isset($this->timings[$key])) {
            $this->timings[$key] = [];
        }

        $this->timings[$key][] = $value;
    }

    /**
     * Get timing summary statistics.
     *
     * @return array<string, array<string, float>>
     */
    private function getTimingSummary(): array
    {
        $summary = [];

        foreach ($this->timings as $key => $values) {
            if (empty($values)) {
                continue;
            }

            sort($values);
            $count = count($values);
            $sum = array_sum($values);

            $summary[$key] = [
                'count' => $count,
                'sum' => $sum,
                'avg' => $sum / $count,
                'min' => $values[0],
                'max' => $values[$count - 1],
                'p50' => $values[intval($count * 0.5)],
                'p95' => $values[intval($count * 0.95)],
                'p99' => $values[intval($count * 0.99)],
            ];
        }

        return $summary;
    }
}
