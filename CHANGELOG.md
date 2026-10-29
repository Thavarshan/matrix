# Changelog

Release notes for all published Matrix releases, newest first. Each entry mirrors its GitHub release and links to the changes since the previous release.

## Matrix v4.0.0

**Released:** 2026-10-29 · **Status:** Stable

### Changes

Matrix 4.0.0 is a major release focused on predictable cancellation, bounded asynchronous scheduling, observability, and a consolidated API reference.

#### Highlights

- Added lazy keyed map and pool scheduling with bounded work per event-loop tick, progress reporting, and support for unknown-length iterables.
- Added terminal cancellation exceptions, cancellation forwarding, timer cleanup, and consistent lifecycle accounting.
- Fixed timeout and await cleanup, invalid retry backoff results, cancellation inside callbacks, and rate-limiter queue reuse and wakeups.
- Preserved independent error context, exception subtypes and metadata, and original causes, including SQLSTATE errors.
- Added monotonic durations and limiter windows, bounded metric categories and samples, and isolated observer diagnostics.
- Expanded regression coverage, removed broad PHPStan suppressions, added archive consumer smoke checks and Composer dependency monitoring.
- Consolidated project documentation in README.md.

#### Breaking changes

- Promise cancellation now rejects with `CancellationException`.
- Progress callbacks can receive a `null` total for unknown-length iterables.
- Duplicate input keys are each executed; the last input value wins in the result.
- See the migration guide in README.md for API and class changes.

### Full changelog

[Compare 4.0.0-beta.1...4.0.0](https://github.com/Thavarshan/matrix/compare/4.0.0-beta.1...4.0.0)

## Matrix v4.0.0-beta.1

**Released:** 2026-07-30 · **Status:** Pre-release

### Changes

- Bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/Thavarshan/matrix/pull/12
- Matrix 4.0 modernization release by @Thavarshan in https://github.com/Thavarshan/matrix/pull/16

### Full changelog

[Compare 3.4.0...4.0.0-beta.1](https://github.com/Thavarshan/matrix/compare/3.4.0...4.0.0-beta.1)

## Matrix v3.4.0

**Released:** 2025-11-06 · **Status:** Stable

### Changes

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

### Full changelog

[Compare 3.3.1...3.4.0](https://github.com/Thavarshan/matrix/compare/3.3.1...3.4.0)

## Matrix v3.3.1

**Released:** 2025-11-06 · **Status:** Stable

### Changes

#### Changed

- **Helper Functions Namespace**: Added `Matrix\Support` namespace to all helper functions in `src/Matrix/Support/helpers.php`. This change provides better organization and prevents global namespace pollution while maintaining backward compatibility through function existence checks.

### Full changelog

[Compare 3.3.0...3.3.1](https://github.com/Thavarshan/matrix/compare/3.3.0...3.3.1)

## Matrix v3.3.0

**Released:** 2025-05-11 · **Status:** Stable

### Changes

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

### Full changelog

[Compare 3.2.0...3.3.0](https://github.com/Thavarshan/matrix/compare/3.2.0...3.3.0)

## Matrix v3.2.0

**Released:** 2025-05-03 · **Status:** Stable

### Changes

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

### Full changelog

[Compare 3.1.0...3.2.0](https://github.com/Thavarshan/matrix/compare/3.1.0...3.2.0)

## Matrix v3.1.0

**Released:** 2024-12-20 · **Status:** Stable

### Changes

#### Added
- **Enhanced `async()` and `await()` Functions**:
  - Automatically manage the ReactPHP event loop, removing the need for manual `getLoop()->run()` calls in most scenarios.
  - Added improved handling of promise resolution and rejection, ensuring consistent results in asynchronous workflows.

#### Changed
- **Simplified Code Structure**:
  - Removed reliance on `pcntl_fork()` for child process management, enabling better compatibility with web server environments.
  - Improved API consistency for seamless integration in both CLI and web-based applications.
- **Deprecated Fork-Based Concurrency**:
  - Eliminated the usage of `pcntl_fork()` to support non-blocking asynchronous operations without requiring process forking.
  - Streamlined error handling and promise lifecycle management by adopting a purely ReactPHP-based approach.

#### Fixed
- **Web Compatibility**:
  - Resolved issues that previously caused incompatibility with multi-threaded web server configurations like Apache's Worker MPM.
  - Ensured that asynchronous tasks can now execute reliably in web contexts.

### Full changelog

[Compare 3.0.1...3.1.0](https://github.com/Thavarshan/matrix/compare/3.0.1...3.1.0)

## Matrix v3.0.1

**Released:** 2024-12-18 · **Status:** Stable

### Changes

#### Changed

- Refactored `catch` method in `AsyncPromise` class to use React PHP's `catch` method instead of `otherwise` method as it is deprecated.

### Full changelog

[Compare 3.0.0...3.0.1](https://github.com/Thavarshan/matrix/compare/3.0.0...3.0.1)

## Matrix v3.0.0

**Released:** 2024-12-16 · **Status:** Stable

### Changes

#### Added
- **`async()` Helper**: Introduce a global `async()` function that starts an asynchronous task in a child process and returns an `AsyncPromise`, simplifying async task initialization.
- **`AsyncProcessManager`**: New class responsible for forking child processes, running callables, and returning results via ReactPHP promises. Enables true parallel execution of tasks.
- **`AsyncPromise`**: A promise-like wrapper around ReactPHP promises, providing a JavaScript-inspired `.then()` and `.catch()` API for handling asynchronous results and errors.
- **Non-blocking Event Loop Integration**: Leverages ReactPHP’s event loop to ensure the parent process never blocks, improving concurrency and throughput.

#### Changed
- **Concurrency Model**: Replaced the previous Fiber-based approach with a `pcntl_fork()` and event-loop-driven model, allowing tasks to run in fully parallel child processes rather than cooperatively within a single process.
- **API Simplification**: Removed complex Fiber management and lifecycle control in favor of a simpler, promise-based API that closely resembles JavaScript promises.
- **Error Handling**: Exceptions are now serialized in child processes and rethrown in the parent, streamlining error handling and eliminating Fiber-based error propagation.
- **User Experience**: The entire asynchronous execution flow now relies on `async()` and `AsyncPromise` for a more intuitive and familiar development experience, reducing the mental overhead of previous Fiber lifecycle methods.

#### Fixed
- **Blocking Behavior**: Previous code using Fibers sometimes led to partial blocking or complicated flow control. The new process-based approach ensures truly non-blocking behavior, improving application responsiveness.
- **Complex Error Traces**: With Fibers, debugging could be cumbersome. Now, error messages, file, line, and stack traces are cleanly serialized by the child and rethrown in the parent, simplifying debugging.
- **Limited Concurrency**: The old Fiber system provided concurrency but not true parallelism. By using separate child processes, we’ve fixed previous limitations and enabled multiple tasks to genuinely run at the same time.

### Full changelog

[Compare 2.0.0...3.0.0](https://github.com/Thavarshan/matrix/compare/2.0.0...3.0.0)

## Matrix v2.0.0

**Released:** 2024-12-06 · **Status:** Stable

### Changes

#### Added

- Added support for PHP 8.3 and PHP 8.4 (#2).

#### Changed

- Updated development dependencies.

#### Breaking changes

- Dropped support for PHP 8.2.

### Full changelog

[Compare 1.0.1...2.0.0](https://github.com/Thavarshan/matrix/compare/1.0.1...2.0.0)

## Matrix v1.0.1

**Released:** 2024-10-19 · **Status:** Stable

### Changes

#### Added

- Laravel Pint for code style linting and formatting

#### Changed

- Update code styling for less strict Laravel Pint rules
- Update dependencies (testing/mocking)

### Full changelog

[Compare 1.0.0...1.0.1](https://github.com/Thavarshan/matrix/compare/1.0.0...1.0.1)

## Matrix v1.0.0

**Released:** 2024-09-29 · **Status:** Stable

### Changes

Initial release of Matrix.

### Full changelog

Initial release; no earlier release to compare.
