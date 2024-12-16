# Release Notes

## [Unreleased](https://github.com/Thavarshan/matrix/compare/v3.0.0...HEAD)

## [v3.0.0](https://github.com/Thavarshan/matrix/compare/v2.0.0...v3.0.0) - 2024-12-16

### Added

- **`async()` Helper**: Introduce a global `async()` function that starts an asynchronous task in a child process and returns an `AsyncPromise`, simplifying async task initialization.
- **`AsyncProcessManager`**: New class responsible for forking child processes, running callables, and returning results via ReactPHP promises. Enables true parallel execution of tasks.
- **`AsyncPromise`**: A promise-like wrapper around ReactPHP promises, providing a JavaScript-inspired `.then()` and `.catch()` API for handling asynchronous results and errors.
- **Non-blocking Event Loop Integration**: Leverages ReactPHP’s event loop to ensure the parent process never blocks, improving concurrency and throughput.

### Changed

- **Concurrency Model**: Replaced the previous Fiber-based approach with a `pcntl_fork()` and event-loop-driven model, allowing tasks to run in fully parallel child processes rather than cooperatively within a single process.
- **API Simplification**: Removed complex Fiber management and lifecycle control in favor of a simpler, promise-based API that closely resembles JavaScript promises.
- **Error Handling**: Exceptions are now serialized in child processes and rethrown in the parent, streamlining error handling and eliminating Fiber-based error propagation.
- **User Experience**: The entire asynchronous execution flow now relies on `async()` and `AsyncPromise` for a more intuitive and familiar development experience, reducing the mental overhead of previous Fiber lifecycle methods.

### Fixed

- **Blocking Behavior**: Previous code using Fibers sometimes led to partial blocking or complicated flow control. The new process-based approach ensures truly non-blocking behavior, improving application responsiveness.
- **Complex Error Traces**: With Fibers, debugging could be cumbersome. Now, error messages, file, line, and stack traces are cleanly serialized by the child and rethrown in the parent, simplifying debugging.
- **Limited Concurrency**: The old Fiber system provided concurrency but not true parallelism. By using separate child processes, we’ve fixed previous limitations and enabled multiple tasks to genuinely run at the same time.

## [v2.0.0](https://github.com/Thavarshan/matrix/compare/v1.0.1...v2.0.0) - 2024-12-06

### Added

* Support fopr PHP 8.4 and PHP 8.3 (#2)

### Changed

* Dropped support for PHP 8.2

**Full Changelog**: https://github.com/Thavarshan/matrix/compare/1.0.1...2.0.0

## [v1.0.1](https://github.com/Thavarshan/matrix/compare/v1.0.0...v1.0.1) - 2024-10-19

### Added

- Laravel Pint for code style linting and formating

### Changed

- Update code styling for less strinc Laravel styles
- Update dependencies (testing/mocking)

## v1.0.0 - 2024-09-29

Initial release.
