# Matrix

Matrix provides small, event-driven async helpers for [ReactPHP](https://reactphp.org/). It makes promise composition, concurrency limits, retries, timeouts, cancellation, and rate limiting consistent without pretending to create threads or process-level parallelism.

## Requirements

- PHP 8.3 or newer
- `react/event-loop` 1.6 or newer
- `react/promise` 3.3 or newer

No `pcntl`, `posix`, or `sockets` extension is required.

Install it with Composer:

```bash
composer require jerome/matrix
```

## Core usage

Import the helpers explicitly in each file:

```php
use function Matrix\Support\async;
use function Matrix\Support\await;
use function Matrix\Support\delay;

$value = await(async(fn () => 'ready'));
$later = await(delay(0.1, 'ready later'));
```

`async()` schedules a callable on the ReactPHP loop. The callable must use non-blocking I/O; `sleep()`, `file_get_contents()`, CPU-heavy work, and synchronous database clients still block the loop.

`await()` is a top-level synchronous bridge for CLI scripts and other code that owns the loop. It must not be called from inside an already-running loop callback. Use promise chaining inside an event-driven application instead.

## Composition and concurrency

All combinators accept promises or plain values. `all()`, `race()`, and `any()` accept any iterable. `all()` preserves keys:

```php
use function Matrix\Support\all;
use function Matrix\Support\async;
use function Matrix\Support\await;

$results = await(all([
    'first' => async(fn () => 1),
    'second' => 2,
]));
// ['first' => 1, 'second' => 2]
```

`map()` accepts arrays or generators, preserves keys, and limits active work when `$concurrency` is greater than zero. `pool()` does the same for zero-argument task callables. Both stop scheduling new work after the first failure and observe already-running tasks.

```php
use function Matrix\Support\await;
use function Matrix\Support\map;

$values = await(map(
    ['a' => 1, 'b' => 2, 'c' => 3],
    fn (int $value): int => $value * 2,
    concurrency: 2,
));
```

`batch()` groups values in encounter order and flattens each batch result into a list. `waterfall()` passes each result to the next callable.

## Timeouts, retries, and cancellation

```php
use function Matrix\Support\async;
use function Matrix\Support\await;
use function Matrix\Support\retry;
use function Matrix\Support\timeout;

$result = await(timeout(
    retry(fn () => async(fn () => fetchNonBlockingData()), 3),
    5.0,
));
```

Timeouts cancel the losing operation and their timer. Retry backoff is event-loop based. Invalid durations, limits, or attempt counts throw `InvalidArgumentException` before work is scheduled.

`cancellable()` returns a native ReactPHP `PromiseInterface`. Calling `cancel()` invokes the cleanup callback once and forwards cancellation to the wrapped promise:

```php
use function Matrix\Support\cancellable;
use function Matrix\Support\delay;

$operation = cancellable(delay(30), fn () => releaseResources());
$operation->cancel();
```

## Rate limiting

```php
use function Matrix\Support\await;
use function Matrix\Support\rateLimit;

$limited = rateLimit(fn (string $url) => fetchNonBlocking($url), 2, 1.0);
$response = await($limited('https://example.com'));
```

The limiter uses a sliding window and removes cancelled queued calls before execution.

## Events and metrics

Matrix emits `promise.created`, `promise.resolved`, `promise.rejected`, and `promise.timeout` events for Matrix-created operations. Listener exceptions are isolated so an observer cannot break application work.

```php
use function Matrix\Support\listen;

listen('promise.rejected', static function ($event): void {
    error_log($event->getErrorClass().': '.$event->getErrorMessage());
});
```

`metricsCollector()->getMetrics()` keeps cumulative counters and bounded timing samples. Percentiles describe the most recent 1,024 samples per operation series, so metrics remain safe in long-running workers.

## Development

```bash
composer install
composer check
```

The check runs Composer validation, Pint, PHPStan at maximum level, and PHPUnit. See [UPGRADING.md](UPGRADING.md) for the 3.x to 4.0 migration notes.

## License

Matrix is released under the MIT license.
