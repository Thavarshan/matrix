# Contributing to Matrix

Matrix is a small **event-driven ReactPHP** async library. Improvements are welcome when they preserve clear cancellation, timeout and event-loop behavior.

## Report a problem usefully

Read the [README](README.md), especially **Cancellation**, **Event-loop integration**, and **Upgrading from 3.x to 4.0**. Please report:

- PHP version, Matrix version, `react/event-loop` and `react/promise` versions.
- A minimal, runnable script and the exact expected result.
- Whether the callback blocks the event loop (`sleep()`, sync I/O, CPU-intensive work) or calls `await()` inside an existing loop.
- Error type, timing, cancellation observations and any mismatch with the documentation.

Do not post credentials or real application payloads. Please follow [private vulnerability reporting](.github/SECURITY.md) for sensitive findings.

## Set up and verify

```bash
git clone https://github.com/Thavarshan/matrix.git
cd matrix
composer install
composer check
```

`composer check` runs strict Composer validation, Pint lint, PHPStan and PHPUnit. During development, `composer test` runs tests and `composer lint` checks formatting.

## Expectations for changes

1. Keep asynchronous work cooperative on the ReactPHP event loop; do not add implicit fibers or hidden blocking behavior.
2. Start with a failing regression test. Cover **resolve, reject, cancel, timeout, idle-loop and cleanup** paths when relevant.
3. Explain what happens to the underlying task on cancellation—do not equate rejecting a wrapper promise with stopping arbitrary work.
4. Preserve key order and input semantics for iterable helpers. Test zero-input and already-settled cases.
5. Update the README's public API and migration examples when behavior changes. Prefer small patches over introducing a new abstraction.
6. State exact checks performed in the PR; flag compatibility or breaking changes explicitly.

## Contributions that help

Focused cancellation tests, a complete PHP example, a documented incompatibility between Matrix and its consumers, a precise error message, or a reproducible performance issue are excellent starts.

Keep discussions respectful and specific; maintainers may have limited review bandwidth.
