<?php

/**
 * Matrix v3.4.0 Event System & Metrics Demo
 *
 * This script demonstrates the new event-driven observability features
 * introduced in Matrix v3.4.0.
 */

require_once __DIR__.'/vendor/autoload.php';

use function Matrix\Support\async;
use function Matrix\Support\await;
use function Matrix\Support\getMetrics;
use function Matrix\Support\listen;
use function Matrix\Support\timeout;

echo "🚀 Matrix v3.4.0 Event System & Metrics Demo\n";
echo '='.str_repeat('=', 45)."\n\n";

// Set up event listeners for demonstration
listen('promise.created', function ($event) {
    echo "📦 Promise created: {$event->getPromiseId()} (type: {$event->getType()})\n";
});

listen('promise.resolved', function ($event) {
    $duration = round($event->getDuration() * 1000, 2); // Convert to milliseconds
    echo "✅ Promise resolved: {$event->getPromiseId()} in {$duration}ms\n";
});

listen('promise.rejected', function ($event) {
    $duration = round($event->getDuration() * 1000, 2);
    echo "❌ Promise rejected: {$event->getPromiseId()} in {$duration}ms - {$event->getErrorMessage()}\n";
});

listen('promise.timeout', function ($event) {
    echo "⏱️  Promise timeout: {$event->getPromiseId()} after {$event->getTimeoutDuration()}s\n";
});

echo "1. Running successful async operations...\n";

// Execute some successful operations
$results = [];
for ($i = 1; $i <= 3; $i++) {
    $results[] = await(async(function () use ($i) {
        // Simulate different work durations
        usleep(rand(10000, 50000)); // 10-50ms

        return "Task {$i} completed";
    }));
}

echo "\nResults: ".implode(', ', $results)."\n\n";

echo "2. Running operations with failures...\n";

// Execute operations with some failures
for ($i = 1; $i <= 2; $i++) {
    try {
        await(async(function () use ($i) {
            if ($i === 2) {
                throw new \Exception("Simulated error in task {$i}");
            }
            usleep(20000); // 20ms

            return "Task {$i} completed";
        }));
    } catch (\Exception $e) {
        // Expected for task 2
    }
}

echo "\n3. Demonstrating timeout events...\n";

// Demonstrate timeout
try {
    await(timeout(
        async(function () {
            usleep(200000); // 200ms - will timeout

            return 'This should timeout';
        }),
        0.1, // 100ms timeout
        'Custom timeout message'
    ));
} catch (\Exception $e) {
    echo "Caught timeout exception: {$e->getMessage()}\n";
}

echo "\n4. Current Metrics Summary:\n";
echo str_repeat('-', 30)."\n";

$metrics = getMetrics();

echo "📊 Performance Metrics:\n";
echo "  Active promises: {$metrics['active_promises']}\n";
echo "  Completed promises: {$metrics['completed_promises']}\n";
echo '  Success rate: '.round($metrics['success_rate'], 2)."%\n";
echo '  Average resolution time: '.round($metrics['average_resolution_time'] * 1000, 2)."ms\n\n";

echo "📈 Operation Counters:\n";
foreach ($metrics['counters'] as $key => $count) {
    echo "  {$key}: {$count}\n";
}

if (! empty($metrics['timings'])) {
    echo "\n⏱️  Timing Statistics:\n";
    foreach ($metrics['timings'] as $operation => $stats) {
        echo "  {$operation}:\n";
        echo "    Count: {$stats['count']}\n";
        echo '    Average: '.round($stats['avg'] * 1000, 2)."ms\n";
        echo '    Min: '.round($stats['min'] * 1000, 2)."ms\n";
        echo '    Max: '.round($stats['max'] * 1000, 2)."ms\n";
        echo '    P95: '.round($stats['p95'] * 1000, 2)."ms\n";
    }
}

echo "\n🎉 Demo completed! Matrix v3.4.0 provides powerful observability for your async operations.\n";
echo "\nKey benefits:\n";
echo "  ✓ Real-time event monitoring\n";
echo "  ✓ Performance metrics collection\n";
echo "  ✓ Error tracking and classification\n";
echo "  ✓ Debugging and profiling capabilities\n";
echo "  ✓ Zero-configuration setup\n";
