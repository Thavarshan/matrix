# Release Notes

## [Unreleased](https://github.com/Thavarshan/matrix/compare/v3.3.1...HEAD)

## [v3.3.1](https://github.com/Thavarshan/matrix/compare/v3.3.0...v3.3.1) - 2025-11-07

### Changed

- **Helper Functions Namespace**: Added `Matrix\Support` namespace to all helper functions in `src/Matrix/Support/helpers.php`. This change provides better organization and prevents global namespace pollution while maintaining backward compatibility through function existence checks.

## [v3.3.0](https://github.com/Thavarshan/matrix/compare/v3.2.0...v3.3.0) - 2025-05-11

### Added

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

### Changed

- Improved test suite to avoid timing-dependent assertions
- Enhanced `timeout()` implementation for more reliable operation
- Refactored `map()` to better handle unlimited concurrency (0 value)
- Optimized event loop management for improved performance
- Updated the README with comprehensive API documentation and examples
- Reorganized code structure for better maintainability

### Fixed

- Fixed unstable tests by removing timing-dependent assertions
- Improved error propagation in nested promises
- Enhanced promise resolution to prevent memory leaks
- Fixed concurrency control in `map()` and `batch()` functions
- Addressed potential issues with event loop management in `await()`
- Improved error handling in `retry()` to properly collect and report failures
- Fixed progress tracking in concurrent operations

## [v3.2.0](https://github.com/Thavarshan/matrix/compare/v3.1.0...v3.2.0) - 2025-05-03

### Added

- **New Concurrency Helper Functions**:
  - `all(array $promises)`: Run multiple promises concurrently and get an array of results.
  - `race(array $promises)`: Get the result of the first promise that resolves.
  - `any(array $promises)`: Get the result of the first promise that succeeds.

### Changed

- **Improved Type Declarations and PHPDocs**:
  - Added proper return type `LoopInterface` to `getLoop()` function.
  - Enhanced documentation with more detailed parameter and return type descriptions.
  - Included examples of various usage patterns in function documentation.

### Fixed

- **Event Loop Management**:
  - Removed premature `getLoop()->stop()` calls in the `async()` function.
  - Added resolution flag in `await()` to prevent running the event loop if a promise is already resolved.
  - Fixed potential issues with multiple concurrent promises by allowing the event loop to continue running when needed.

## [v3.1.0](https://github.com/Thavarshan/matrix/compare/v3.0.1...v3.1.0) - 2024-12-20

### Added

- **Enhanced `async()` and `await()` Functions**:
  - Automatically manage the ReactPHP event loop, removing the need for manual `getLoop()->run()` calls in most scenarios.
  - Added improved handling of promise resolution and rejection, ensuring consistent results in asynchronous workflows.

### Changed

- **Simplified Code Structure**:
  - Removed reliance on `pcntl_fork()` for child process management, enabling better compatibility with web server environments.
  - Improved API consistency for seamless integration in both CLI and web-based applications.

### Removed

- **Deprecated Fork-Based Concurrency**:
  - Eliminated the usage of `pcntl_fork()` to support non-blocking asynchronous operations without requiring process forking.
  - Streamlined error handling and promise lifecycle management by adopting a purely ReactPHP-based approach.

### Fixed

- **Web Compatibility**:
  - Resolved issues that previously caused incompatibility with multi-threaded web server configurations like Apache's Worker MPM.
  - Ensured that asynchronous tasks can now execute reliably in web contexts.

## [v3.0.1](https://github.com/Thavarshan/matrix/compare/v3.0.0...v3.0.1) - 2024-12-18

### Changed

- Refactored `catch` method in `AsyncPromise` class to use React PHP's `catch` method instead of `otherwise` method as it is deprecated.

## [v3.0.0](https://github.com/Thavarshan/matrix/compare/v2.0.0...v3.0.0) - 2024-12-16

### Added

- **`async()` Helper**: Introduce a global `async()` function that starts an asynchronous task in a child process and returns an `AsyncPromise`, simplifying async task initialization.
- **`AsyncProcessManager`**: New class responsible for forking child processes, running callables, and returning results via ReactPHP promises. Enables true parallel execution of tasks.
- **`AsyncPromise`**: A promise-like wrapper around ReactPHP promises, providing a JavaScript-inspired `.then()` and `.catch()` API for handling asynchronous results and errors.
- **Non-blocking Event Loop Integration**: Leverages ReactPHP's event loop to ensure the parent process never blocks, improving concurrency and throughput.

### Changed

- **Concurrency Model**: Replaced the previous Fiber-based approach with a `pcntl_fork()` and event-loop-driven model, allowing tasks to run in fully parallel child processes rather than cooperatively within a single process.
- **API Simplification**: Removed complex Fiber management and lifecycle control in favor of a simpler, promise-based API that closely resembles JavaScript promises.
- **Error Handling**: Exceptions are now serialized in child processes and rethrown in the parent, streamlining error handling and eliminating Fiber-based error propagation.
- **User Experience**: The entire asynchronous execution flow now relies on `async()` and `AsyncPromise` for a more intuitive and familiar development experience, reducing the mental overhead of previous Fiber lifecycle methods.

### Fixed

- **Blocking Behavior**: Previous code using Fibers sometimes led to partial blocking or complicated flow control. The new process-based approach ensures truly non-blocking behavior, improving application responsiveness.
- **Complex Error Traces**: With Fibers, debugging could be cumbersome. Now, error messages, file, line, and stack traces are cleanly serialized by the child and rethrown in the parent, simplifying debugging.
- **Limited Concurrency**: The old Fiber system provided concurrency but not true parallelism. By using separate child processes, we've fixed previous limitations and enabled multiple tasks to genuinely run at the same time.

## [v2.0.0](https://github.com/Thavarshan/matrix/compare/v1.0.1...v2.0.0) - 2024-12-06

### Added

- Support fopr PHP 8.4 and PHP 8.3 (#2)

### Changed

- Dropped support for PHP 8.2

**Full Changelog**: [https://github.com/Thavarshan/matrix/compare/1.0.1...2.0.0](https://github.com/Thavarshan/matrix/compare/1.0.1...2.0.0)

## [v1.0.1](https://github.com/Thavarshan/matrix/compare/v1.0.0...v1.0.1) - 2024-10-19

### Added

- Laravel Pint for code style linting and formating

### Changed

- Update code styling for less strinc Laravel styles
- Update dependencies (testing/mocking)

## v1.0.0 - 2024-09-29

Initial release.
