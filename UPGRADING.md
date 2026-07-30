# Upgrading to Matrix 4.0

Matrix 4.0 is a major release focused on predictable promise behavior and a portable development workflow.

## Runtime and dependencies

- PHP 8.3 is required.
- `pcntl`, `posix`, and `sockets` are not required.
- ReactPHP Event Loop 1.6 and Promise 3.3 are required.

## API changes

- Import all helpers from `Matrix\Support`; the documented helper set now includes `pool`, `waterfall`, `cancellable`, `withErrorContext`, and `rateLimit`.
- Collection helpers accept iterables and preserve keys for `all`, `map`, and `pool`.
- Callbacks may return plain values or promises.
- `reject()` accepts a `Throwable` and follows ReactPHP 3.
- Invalid durations, limits, and attempt counts now throw `InvalidArgumentException` synchronously.
- `CancellablePromise` has been removed. `cancellable()` returns a native `PromiseInterface`; replace `$wrapper->getPromise()` with `$wrapper` and remove `isCancelled()` checks.
- `await()` is a top-level bridge and rejects nested event-loop use. It cancels the source promise when its timeout wins.
- Pools and maps stop scheduling queued work after the first failure; active work is still observed.

## Observability

Lifecycle events now use one operation ID and type from creation through settlement. Timeout events are emitted only when a timeout wins. Metrics no longer retain every completed promise and timing samples are bounded to the latest 1,024 values.
