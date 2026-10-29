# Matrix

Matrix provides event-driven async helpers for [ReactPHP](https://reactphp.org/): promise composition, concurrency limits, retries, timeouts, cancellation, rate limiting, events, and metrics. Work runs cooperatively on one event loop; callbacks must use nonblocking I/O.

This README is the repository's documentation, including the API reference, migration guide, and release history.

- [Installation](#installation)
- [Quick start](#quick-start)
- [Event-loop integration](#event-loop-integration)
- [Promises and collections](#promises-and-collections)
- [Map, pool, batch, and waterfall](#map-pool-batch-and-waterfall)
- [Timeouts and retries](#timeouts-and-retries)
- [Cancellation](#cancellation)
- [Error context](#error-context)
- [Rate limiting](#rate-limiting)
- [HTTP example](#http-example)
- [Events and metrics](#events-and-metrics)
- [Upgrading from 3.x to 4.0](#upgrading-from-3x-to-40)
- [Development](#development)
- [Support](#support)
- [Security](#security)
- [Release history](#release-history)
- [License](#license)

## Installation

```bash
composer require jerome/matrix
```

Matrix requires PHP 8.3 or newer, `react/event-loop` ^1.6, and `react/promise` ^3.3. Composer installs the ReactPHP dependencies. The `pcntl`, `posix`, and `sockets` extensions are optional.

## Quick start

Import helper functions from `Matrix\Support`. Asynchronous helpers return ReactPHP `PromiseInterface` instances; callbacks can return plain values or promises.

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use function Matrix\Support\async;
use function Matrix\Support\await;
use function Matrix\Support\delay;
use function Matrix\Support\map;

$ready = await(async(static fn (): string => 'ready'));

$results = await(map(
    ['first' => 1, 'second' => 2],
    static fn (int $value) => delay(0.01, $value * 2),
    concurrency: 2,
));
// ['first' => 2, 'second' => 4]
```

`async($callable)` schedules the callable on the next event-loop tick and adopts its return value. It does not make a synchronous operation nonblocking. `sleep()`, `usleep()`, synchronous network or database calls, and long CPU computations block every other task on the loop.

## Event-loop integration

`await(PromiseInterface $promise, ?float $timeout = null)` runs the Matrix-managed loop until the promise settles. It returns the fulfillment value, including `null`, or throws the rejection. An optional finite positive timeout cancels the source and throws `Matrix\Exceptions\TimeoutException`. If the loop becomes idle with the promise still pending, `await()` throws `AsyncException`.

Use `await()` at the top level of a script that owns the loop. Inside an existing event-driven application, return or chain promises:

```php
use function Matrix\Support\delay;
use function Matrix\Support\getLoop;

$operation = delay(0.01, 'ready');
$operation->then(
    static function (string $value): void {
        echo $value . PHP_EOL;
    },
    static function (Throwable $error): void {
        error_log($error->getMessage());
    },
);
getLoop()->run();
```

`getLoop()` returns the shared ReactPHP `LoopInterface`; `Matrix\Async::loop()` provides the same loop. Matrix detects nested runs started through its own loop adapter. It cannot reliably detect a loop started directly through ReactPHP, so calling `await()` from any running loop callback is unsupported.

## Promises and collections

The following helpers use ReactPHP Promise 3 semantics. Their argument is named `$promises`, which also supports named calls such as `all(promises: [...])`.

| Helper | Result | Empty input / failure |
| --- | --- | --- |
| `resolve($value)` | A fulfilled promise, or adoption of an existing promise | Accepts any value |
| `reject(Throwable $reason)` | A rejected promise | Requires a throwable |
| `all(iterable $promises)` | Array of fulfilled values, preserving keys and input order | `all([])` resolves to `[]`; the first rejection rejects the aggregate |
| `any(iterable $promises)` | The first fulfilled value | Empty input rejects with `React\Promise\Exception\LengthException`; all failures reject with `CompositeException` |
| `race(iterable $promises)` | The first fulfillment or rejection | `race([])` remains pending |

```php
use function Matrix\Support\all;
use function Matrix\Support\await;
use function Matrix\Support\delay;

$results = await(all([
    'later' => delay(0.01, 1),
    'now' => 2,
]));
// ['later' => 1, 'now' => 2]
```

These collection helpers accept promises or plain values. Cancellation follows the underlying ReactPHP combinator and source promises; it does not guarantee that a noncancellable source stops.

## Map, pool, batch, and waterfall

### Map and pool

```text
map(iterable $items, callable $callback, int $concurrency = 0, ?callable $onProgress = null)
pool(iterable $callables, int $concurrency = 5, ?callable $onProgress = null)
```

`map()` calls `$callback($item)` for every input value. `pool()` invokes zero-argument task callables. Both return promises for result arrays, consume input lazily as capacity opens, and preserve input keys and order. Map concurrency `0` means unlimited; pool concurrency must be positive. A large set of immediately settled tasks yields between groups of work so other loop activity can run.

Duplicate iterator keys execute every item. The last input with a given key determines its final result, regardless of completion order. Use unique keys if every result must appear separately.

```php
use function Matrix\Support\await;
use function Matrix\Support\delay;
use function Matrix\Support\pool;

$results = await(pool([
    'first' => static fn () => delay(0.02, 'A'),
    'second' => static fn () => delay(0.01, 'B'),
], concurrency: 2, onProgress: static function (int $completed, ?int $total): void {
    echo $completed . '/' . ($total ?? '?') . PHP_EOL;
}));
// ['first' => 'A', 'second' => 'B']
```

Progress receives `(completed, total)`. The total is known for arrays and `Countable` inputs; otherwise it is `null`. A task failure or throwing progress callback rejects the aggregate and stops queued work. Active tasks continue to be observed, and later results do not produce progress updates. Cancelling the aggregate forwards cancellation to active tasks and stops queued work.

### Batch

`batch(iterable $items, callable $batchCallback, int $batchSize = 10, int $concurrency = 1)` forms lists of input values in encounter order. The callback receives each list and returns an iterable, or a promise for an iterable. Results are flattened into one list in batch order; returned keys are discarded. Batch size and concurrency must be positive. A noniterable callback result rejects with `UnexpectedValueException`.

```php
use function Matrix\Support\await;
use function Matrix\Support\batch;

$doubled = await(batch(
    range(1, 5),
    static fn (array $items): array => array_map(static fn (int $n): int => $n * 2, $items),
    batchSize: 2,
    concurrency: 2,
));
// [2, 4, 6, 8, 10]
```

### Waterfall

`waterfall(iterable $callables, mixed $initialValue = null)` passes each resolved value to the next callable. The first callable receives `$initialValue`; an empty sequence resolves to that value. Each step may return a value or promise. The first rejection skips subsequent steps.

```php
use function Matrix\Support\await;
use function Matrix\Support\waterfall;

$result = await(waterfall([
    static fn (int $value): int => $value + 1,
    static fn (int $value): int => $value * 2,
], 3));
// 8
```

## Timeouts and retries

### Delays and timeouts

`delay(float $seconds, mixed $value = null)` resolves with `$value` after a finite nonnegative delay. A zero delay still schedules a timer. `timeout(PromiseInterface $promise, float $seconds, string $message = 'Operation timed out')` follows the source unless its finite positive timeout wins. A winning timeout cancels the source and rejects with `TimeoutException`; a winning source removes the timer.

```php
use Matrix\Exceptions\TimeoutException;
use function Matrix\Support\await;
use function Matrix\Support\delay;
use function Matrix\Support\timeout;

try {
    await(timeout(delay(0.2, 'late'), 0.01, 'Request took too long'));
} catch (TimeoutException $error) {
    echo $error->getMessage();
}
```

Timers cannot interrupt a blocked PHP callback. A timeout can cancel I/O only when the source library supports cancellation.

### Retry

`retry(callable $factory, int $maxAttempts = 3, ?callable $backoffStrategy = null)` invokes a new factory attempt after a rejection or thrown exception. `$maxAttempts` includes the first attempt and must be positive.

The backoff callback receives `(failedAttemptNumber, Throwable $error)`, starting at attempt `1`. Return a finite nonnegative number of seconds to retry, or `null` to stop and reject with the last error. The default delay is `min(0.1 * 2 ** (attempt - 1), 5.0)`. Exhaustion rejects with `RetryException`, whose `getAttempts()` and `getFailures()` expose the attempt count and failures. A thrown backoff error or invalid result rejects the returned operation directly.

```php
use function Matrix\Support\await;
use function Matrix\Support\retry;

$attempts = 0;
$result = await(retry(
    static function () use (&$attempts): string {
        if (++$attempts < 3) {
            throw new RuntimeException('Try again');
        }
        return 'ready';
    },
    maxAttempts: 3,
    backoffStrategy: static fn (int $attempt, Throwable $error): float => $attempt * 0.01,
));
```

Invalid durations, concurrency values, limits, and attempt counts throw `InvalidArgumentException` when the helper is called. Invalid backoff results reject asynchronously because the strategy runs after an attempt fails.

## Cancellation

`async()`, `delay()`, `timeout()`, `retry()`, `map()`, `pool()`, `batch()`, `rateLimit()`, and `cancellable()` create work and timer operations using native ReactPHP `PromiseInterface` objects. Cancelling a pending operation selects a terminal `CancellationException`, stops its queued work or timers, and forwards cancellation to active sources. A settled operation keeps its existing result. ReactPHP's collection helpers and source libraries retain their own cancellation behavior.

`cancellable(PromiseInterface $promise, callable $onCancel)` adds cleanup that runs once when cancellation reaches the wrapper. It cancels its own subscription to the source, so another source follower can continue. If cleanup throws, that error becomes the wrapper's rejection and source cancellation is still forwarded. Late source settlement cannot replace a cancelled wrapper's result.

```php
use Matrix\Exceptions\CancellationException;
use function Matrix\Support\await;
use function Matrix\Support\cancellable;
use function Matrix\Support\delay;

$operation = cancellable(delay(30), static function (): void {
    // Release resources owned by this operation.
});
$observed = $operation->catch(static fn (CancellationException $error): string => 'cancelled');
$operation->cancel();
echo await($observed); // cancelled
```

Attach rejection handlers to operations whose cancellation you initiate. When several child promises share a source, ReactPHP propagates child cancellation upstream once its follower rules permit it.

## Error context

`withErrorContext(PromiseInterface $promise, string $context)` prefixes rejection messages. Matrix `AsyncException` subclasses retain their subtype, code, and metadata in a separate contextual exception. Other throwables are wrapped in `AsyncException`; noninteger codes such as SQLSTATE remain available on the original exception. Every contextual error keeps the original as `getPrevious()`. Nested contexts form a chain and leave earlier errors unchanged.

```php
use Matrix\Exceptions\AsyncException;
use function Matrix\Support\await;
use function Matrix\Support\reject;
use function Matrix\Support\withErrorContext;

try {
    await(withErrorContext(reject(new RuntimeException('Unavailable')), 'Load account'));
} catch (AsyncException $error) {
    echo $error->getMessage(); // Load account: Unavailable
}
```

## Rate limiting

`rateLimit(callable $fn, int $maxCalls, float $period)` returns a callable that queues invocations and returns a promise for each result. It starts at most `$maxCalls` invocations in a sliding window of `$period` seconds. The limit must be positive and the period finite and positive. Callback failures count as started invocations; cancelling a queued call removes it before execution.

```php
use function Matrix\Support\all;
use function Matrix\Support\await;
use function Matrix\Support\delay;
use function Matrix\Support\rateLimit;

$limited = rateLimit(static fn (string $name) => delay(0.01, strtoupper($name)), 2, 1.0);
$results = await(all([$limited('first'), $limited('second'), $limited('third')]));
```

`Matrix\Support\RateLimiter::create($maxCalls, $period)->limit($fn)` offers the same behavior. Typed callbacks are accepted. The wrapper's PHPDoc exposes `PromiseInterface<mixed>`; the callback's parameter checks still apply at runtime.

## HTTP example

For real nonblocking network I/O, install the optional [ReactPHP HTTP client](https://reactphp.org/http/#client):

```bash
composer require react/http
```

Return the HTTP client's promise directly from the map callback:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use React\Http\Browser;
use function Matrix\Support\await;
use function Matrix\Support\map;
use function Matrix\Support\timeout;

$browser = new Browser();
$urls = ['https://example.com/', 'https://example.org/'];

try {
    $responses = await(timeout(map(
        $urls,
        static fn (string $url) => $browser->get($url),
        concurrency: 2,
    ), 5.0));

    foreach ($responses as $response) {
        echo $response->getStatusCode() . ': ' . strlen((string) $response->getBody()) . ' bytes' . PHP_EOL;
    }
} catch (Throwable $error) {
    error_log($error->getMessage());
}
```

Matrix also works with other ReactPHP-compatible promise clients. A synchronous client called inside `async()` still blocks the loop.

## Events and metrics

### Events

`listen(string $eventName, callable $listener)` registers an observer on `eventDispatcher()`. Matrix emits lifecycle events for operations created through its helpers:

| Event | Class in `Matrix\Events` | Useful accessors |
| --- | --- | --- |
| `promise.created` | `PromiseCreated` | `getPromiseId()`, `getOperationType()` |
| `promise.resolved` | `PromiseResolved` | `getValue()`, `getDuration()` |
| `promise.rejected` | `PromiseRejected` | `getReason()`, `getErrorClass()`, `getErrorMessage()`, `getDuration()` |
| `promise.timeout` | `PromiseTimeout` | `getTimeoutDuration()`, `getMessage()` |

Creation and settlement share an operation ID and type. The timeout helper emits `promise.timeout` only when its timer wins; an `await()` timeout does not create a separate timeout-helper event. Durations are in seconds and use monotonic time. Event timestamps use wall-clock time.

```php
use Matrix\Events\EventInterface;
use Matrix\Events\PromiseRejected;
use function Matrix\Support\eventDispatcher;
use function Matrix\Support\listen;

listen(PromiseRejected::NAME, static function (PromiseRejected $event): void {
    error_log($event->getOperationType() . ': ' . $event->getErrorMessage());
});

eventDispatcher()->setErrorHandler(static function (Throwable $error, EventInterface $event): void {
    error_log('Observer for ' . $event->getName() . ' failed: ' . $error->getMessage());
});
```

Listener exceptions and error-handler exceptions are isolated from operation results. The dispatcher also provides `removeListener($name, $listener)`, `removeAllListeners($name)`, `getListeners($name)`, `clear()`, `setEnabled(bool)`, and `isEnabled()`. `fire($name, $data = [])` dispatches a generic event. Use the same callable instance when removing a listener. Set the diagnostic handler to `null` to remove it.

### Metrics

`getMetrics()` returns the same snapshot as `metricsCollector()->getMetrics()`:

| Field | Meaning |
| --- | --- |
| `active_promises` | Number of tracked operations still pending |
| `completed_promises` | Recorded resolutions plus rejections |
| `success_rate` | Recorded resolutions as a percentage of completed operations |
| `average_resolution_time` | Mean duration across the retained sample window |
| `counters` | Cumulative creation, settlement, timeout, operation-type, and error counters |
| `timings` | Sample count, sum, mean, minimum, maximum, and p50/p95/p99 by series |

The default sample limit is 1,024 per timing series. `new Matrix\Metrics\MetricsCollector($sampleLimit)` allows a positive custom limit. Unknown operation types and error classes are grouped into finite categories. Pending operations retain active metadata until settlement; bounded timing samples do not bound the number of live operations.

`metricsCollector()->setEnabled(false)` pauses recording. Previously tracked operations are retired when they settle while disabled, without adding completion counters. `reset()` clears counters, samples, and active entries. Existing operations that settle after a reset are no longer counted.

For manual instrumentation, the collector exposes `promiseCreated($id, $type = 'promise', $context = [])`, `promiseResolved($id, $value = null)`, `promiseRejected($id, Throwable $error)`, and `promiseTimeout($id, $duration)`. `Matrix\Async::generatePromiseId()` creates an operation ID. Individual queries include `getCounter($key)`, `getActivePromiseCount()`, `getCompletedPromiseCount()`, `getSuccessRate()`, `getAverageResolutionTime()`, and `isEnabled()`.

## Upgrading from 3.x to 4.0

- PHP 8.3 was already required by 3.4.0. Runtime dependencies now require Event Loop ^1.6 and Promise ^3.3; `pcntl`, `posix`, and `sockets` are optional.
- Import helpers from `Matrix\Support`. `async()` maps to `Matrix\Async::coro()`; other helpers map to methods on the static `Matrix\Async` facade.
- `CancellablePromise` has been removed. Use the returned `PromiseInterface` directly, replace `$wrapper->getPromise()` with `$wrapper`, and handle cancellation rejections instead of querying `isCancelled()`.
- `Async`, `MetricsCollector`, `PromisePool`, `LoopManager`, and `RateLimiter` are final. `Async` has a private constructor. Use composition instead of subclassing these classes.
- Custom `EventInterface` implementations must add `getOperationType()`. Custom `Matrix\Interfaces\Async` implementations must implement the expanded method set and signatures.
- `reject()` requires a `Throwable`. Callbacks may return plain values or promises. `all()`, `map()`, and `pool()` preserve keys; collection helpers accept iterables.
- Named calls to `all()`, `any()`, and `race()` use `$promises`. Map/pool progress totals can be `null` for non-countable input. Duplicate iterator keys execute every callback, with the last input supplying the result.
- `await()` owns a top-level loop run; it cannot reliably detect external ReactPHP loop runs. Chain promises inside loop callbacks.
- Cancellation of pending Matrix work rejects with `CancellationException`. Map/pool failures stop queued work while observing active tasks; explicit cancellation forwards to active tasks.
- Durations must be finite. Invalid helper arguments throw synchronously; invalid retry backoff results reject the returned promise. Backoff may return `null` to stop retries.
- Error context preserves Matrix exception subtypes and metadata without mutating shared errors, and chains the original as `previous`.
- Lifecycle IDs and operation types remain consistent through settlement. Timing samples are bounded, durations use monotonic time, and timeout events occur only when the timeout helper wins.

## Development

```bash
composer install
composer check
```

`composer check` runs strict Composer validation, Pint, maximum-level PHPStan, and PHPUnit. Individual commands are `composer lint`, `composer analyse`, and `composer test`; `composer fix` applies Pint formatting. `composer audit` checks resolved dependencies for advisories.

CI runs tests on PHP 8.3, 8.4, and 8.5 on Linux, plus PHP 8.5 on Windows, and tests the lowest supported dependency set. Package CI creates a Composer archive, checks required files, installs it into a fresh consumer without development dependencies, and exercises async, map, and timeout behavior.

Update this README for API, migration, development, security, support, and release documentation changes. The GitHub support and security files link here so their policies have one source.

## Support

Report reproducible bugs and request features through [GitHub issues](https://github.com/Thavarshan/matrix/issues). Include the Matrix and PHP versions, a minimal example, and the observed and expected behavior. General questions can also be directed to [Jerome on Twitter](https://twitter.com/tjthavarshan).

## Security

Report vulnerabilities privately to Jerome Thayananthajothy at [tjthavarshan@gmail.com](mailto:tjthavarshan@gmail.com). Include reproduction steps, affected versions, and the potential impact. Please avoid public disclosure while a fix is being prepared.

<details>
<summary>Public PGP key for encrypted reports</summary>

```text
-----BEGIN PGP PUBLIC KEY BLOCK-----
Version: Keybase OpenPGP v1.0.0
Comment: https://keybase.io/crypto

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
UcKwfSc=
=N/Ig
-----END PGP PUBLIC KEY BLOCK-----
```

</details>

## Release history

### 4.0.0 — 2026-10-29

[Changes from 3.4.0](https://github.com/Thavarshan/matrix/compare/3.4.0...4.0.0) · [Unreleased changes](https://github.com/Thavarshan/matrix/compare/4.0.0...HEAD)

- Added lazy keyed map/pool scheduling with bounded work per event-loop tick and progress reporting for unknown-length iterables.
- Added terminal cancellation exceptions, cancellation forwarding, timer cleanup, and consistent lifecycle accounting.
- Fixed timeout/await cleanup, thrown and invalid retry backoff results, cancellation inside callbacks, and rate-limiter queue reuse.
- Preserved independent error context, exception subtypes, metadata, and original causes, including SQLSTATE errors.
- Added monotonic durations and limiter windows, bounded metric categories and samples, and isolated observer diagnostics.
- Expanded regression tests, removed broad PHPStan suppressions, added archive consumer smoke tests and Composer dependency monitoring, and consolidated documentation in this README.
- Breaking changes include native promise cancellation rejections, nullable progress totals, duplicate-key execution, and the API/class changes listed in the migration guide above.

<details>
<summary>3.x, 2.x, and 1.x release notes</summary>

The following entries describe the APIs and behavior of those historical versions.

### [v3.4.0](https://github.com/Thavarshan/matrix/compare/3.3.1...3.4.0) - 2025-11-07

#### Added

- **Event System & Observability**: Comprehensive event-driven architecture for monitoring async operations
  - `EventDispatcher` class for managing event listeners and firing events
  - Promise lifecycle events: `promise.created`, `promise.resolved`, `promise.rejected`, `promise.timeout`
  - Event classes: `PromiseCreated`, `PromiseResolved`, `PromiseRejected`, `PromiseTimeout`
  - Helper functions: `listen()`, `eventDispatcher()` for easy event handling
- **Metrics Collection**: Built-in performance monitoring and statistics
  - `MetricsCollector` class for tracking promise performance and statistics
  - Real-time metrics: active promises, completion rates, success rates, timing data
  - Detailed counters for different operation types and error classes
  - Helper functions: `getMetrics()`, `metricsCollector()` for accessing metrics data
- **Enhanced Debugging**: Event-driven debugging capabilities for better async operation visibility
  - Promise ID generation for tracking individual operations
  - Duration tracking for all async operations
  - Error classification and counting
  - Performance profiling with percentile statistics (p50, p95, p99)
- **Comprehensive Test Coverage**: Full unit and integration tests for all event and metrics functionality

#### Changed

- **Async Class Enhancement**: Modified `Async::coro()` and `Async::timeout()` methods to fire events and collect metrics
- **Promise Lifecycle Tracking**: All promises now automatically fire events during creation, resolution, rejection, and timeout
- **Helper Functions Expansion**: Added new helper functions for event system and metrics access

### [v3.3.1](https://github.com/Thavarshan/matrix/compare/3.3.0...3.3.1) - 2025-11-07

#### Changed

- **Helper Functions Namespace**: Added `Matrix\Support` namespace to all helper functions in `src/Matrix/Support/helpers.php`. This change provides better organization and prevents global namespace pollution while maintaining backward compatibility through function existence checks.

### [v3.3.0](https://github.com/Thavarshan/matrix/compare/3.2.0...3.3.0) - 2025-05-11

#### Added

- Rate limiting functionality via `rateLimit()` to manage API and resource access
- Promise cancellation support with `cancellable()` and cleanup callbacks
- Batch processing capability with the `batch()` function for improved performance
- Retry mechanism with configurable backoff strategies using `retry()`
- Error context enhancement via `withErrorContext()` for better debugging
- Waterfall promise chaining with `waterfall()` for sequential operations
- Comprehensive integration tests covering real-world usage scenarios
- Advanced error handling with specialized exception classes (`TimeoutException`, `RetryException`, `AsyncException`)
- Support for progress tracking in concurrent operations (`map()`, `pool()`)
- Promise pooling with `pool()` for executing multiple callables with concurrency limits
- Detailed API documentation with examples for all functions
- JIRA integration example showing complex asynchronous workflows
- Database operations example demonstrating concurrent queries

#### Changed

- Improved test suite to avoid timing-dependent assertions
- Enhanced `timeout()` implementation for more reliable operation
- Refactored `map()` to better handle unlimited concurrency (0 value)
- Optimized event loop management for improved performance
- Updated the README with comprehensive API documentation and examples
- Reorganized code structure for better maintainability

#### Fixed

- Fixed unstable tests by removing timing-dependent assertions
- Improved error propagation in nested promises
- Enhanced promise resolution to prevent memory leaks
- Fixed concurrency control in `map()` and `batch()` functions
- Addressed potential issues with event loop management in `await()`
- Improved error handling in `retry()` to properly collect and report failures
- Fixed progress tracking in concurrent operations

### [v3.2.0](https://github.com/Thavarshan/matrix/compare/3.1.0...3.2.0) - 2025-05-03

#### Added

- **New Concurrency Helper Functions**:
  - `all(array $promises)`: Run multiple promises concurrently and get an array of results.
  - `race(array $promises)`: Get the result of the first promise that resolves.
  - `any(array $promises)`: Get the result of the first promise that succeeds.

#### Changed

- **Improved Type Declarations and PHPDocs**:
  - Added proper return type `LoopInterface` to `getLoop()` function.
  - Enhanced documentation with more detailed parameter and return type descriptions.
  - Included examples of various usage patterns in function documentation.

#### Fixed

- **Event Loop Management**:
  - Removed premature `getLoop()->stop()` calls in the `async()` function.
  - Added resolution flag in `await()` to prevent running the event loop if a promise is already resolved.
  - Fixed potential issues with multiple concurrent promises by allowing the event loop to continue running when needed.

### [v3.1.0](https://github.com/Thavarshan/matrix/compare/3.0.1...3.1.0) - 2024-12-20

#### Added

- **Enhanced `async()` and `await()` Functions**:
  - Automatically manage the ReactPHP event loop, removing the need for manual `getLoop()->run()` calls in most scenarios.
  - Added improved handling of promise resolution and rejection, ensuring consistent results in asynchronous workflows.

#### Changed

- **Simplified Code Structure**:
  - Removed reliance on `pcntl_fork()` for child process management, enabling better compatibility with web server environments.
  - Improved API consistency for seamless integration in both CLI and web-based applications.

#### Removed

- **Deprecated Fork-Based Concurrency**:
  - Eliminated the usage of `pcntl_fork()` to support non-blocking asynchronous operations without requiring process forking.
  - Streamlined error handling and promise lifecycle management by adopting a purely ReactPHP-based approach.

#### Fixed

- **Web Compatibility**:
  - Resolved issues that previously caused incompatibility with multi-threaded web server configurations like Apache's Worker MPM.
  - Ensured that asynchronous tasks can now execute reliably in web contexts.

### [v3.0.1](https://github.com/Thavarshan/matrix/compare/3.0.0...3.0.1) - 2024-12-18

#### Changed

- Refactored `catch` method in `AsyncPromise` class to use React PHP's `catch` method instead of `otherwise` method as it is deprecated.

### [v3.0.0](https://github.com/Thavarshan/matrix/compare/2.0.0...3.0.0) - 2024-12-16

#### Added

- **`async()` Helper**: Introduce a global `async()` function that starts an asynchronous task in a child process and returns an `AsyncPromise`, simplifying async task initialization.
- **`AsyncProcessManager`**: New class responsible for forking child processes, running callables, and returning results via ReactPHP promises. Enables true parallel execution of tasks.
- **`AsyncPromise`**: A promise-like wrapper around ReactPHP promises, providing a JavaScript-inspired `.then()` and `.catch()` API for handling asynchronous results and errors.
- **Non-blocking Event Loop Integration**: Leverages ReactPHP's event loop to ensure the parent process never blocks, improving concurrency and throughput.

#### Changed

- **Concurrency Model**: Replaced the previous Fiber-based approach with a `pcntl_fork()` and event-loop-driven model, allowing tasks to run in fully parallel child processes rather than cooperatively within a single process.
- **API Simplification**: Removed complex Fiber management and lifecycle control in favor of a simpler, promise-based API that closely resembles JavaScript promises.
- **Error Handling**: Exceptions are now serialized in child processes and rethrown in the parent, streamlining error handling and eliminating Fiber-based error propagation.
- **User Experience**: The entire asynchronous execution flow now relies on `async()` and `AsyncPromise` for a more intuitive and familiar development experience, reducing the mental overhead of previous Fiber lifecycle methods.

#### Fixed

- **Blocking Behavior**: Previous code using Fibers sometimes led to partial blocking or complicated flow control. The new process-based approach ensures truly non-blocking behavior, improving application responsiveness.
- **Complex Error Traces**: With Fibers, debugging could be cumbersome. Now, error messages, file, line, and stack traces are cleanly serialized by the child and rethrown in the parent, simplifying debugging.
- **Limited Concurrency**: The old Fiber system provided concurrency but not true parallelism. By using separate child processes, we've fixed previous limitations and enabled multiple tasks to genuinely run at the same time.

### [v2.0.0](https://github.com/Thavarshan/matrix/compare/1.0.1...2.0.0) - 2024-12-06

#### Added

- Support for PHP 8.4 and PHP 8.3 (#2)

#### Changed

- Dropped support for PHP 8.2

**Full Changelog**: [https://github.com/Thavarshan/matrix/compare/1.0.1...2.0.0](https://github.com/Thavarshan/matrix/compare/1.0.1...2.0.0)

### [v1.0.1](https://github.com/Thavarshan/matrix/compare/1.0.0...1.0.1) - 2024-10-19

#### Added

- Laravel Pint for code style linting and formatting

#### Changed

- Update code styling for less strict Laravel styles
- Update dependencies (testing/mocking)

### v1.0.0 - 2024-09-29

Initial release.

</details>

## License

Matrix is released under the [MIT license](LICENSE).
