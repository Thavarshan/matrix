[![Matrix](./assets/Banner.jpg)](https://github.com/Thavarshan/matrix)

# Matrix

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jerome/matrix.svg)](https://packagist.org/packages/jerome/matrix)
[![Tests](https://github.com/Thavarshan/matrix/actions/workflows/run-tests.yml/badge.svg?label=tests&branch=main)](https://github.com/Thavarshan/matrix/actions/workflows/run-tests.yml)
[![Check & fix styling](https://github.com/Thavarshan/matrix/actions/workflows/laravel-pint.yml/badge.svg)](https://github.com/Thavarshan/matrix/actions/workflows/laravel-pint.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/jerome/matrix.svg)](https://packagist.org/packages/jerome/matrix)

**Matrix** is a PHP library that brings asynchronous, non-blocking task execution to PHP. Inspired by the JavaScript `async`/`await` pattern, Matrix leverages `pcntl_fork()` and ReactPHP promises to run tasks in parallel child processes. The result is a clean, promise-based API enabling concurrency without blocking your main code.

Matrix’s `async()` helper returns a promise-like object (thanks to `AsyncPromise`) which provides familiar `then()` and `catch()` methods, making asynchronous tasks feel as natural as JavaScript promises.

---

## **Why Matrix?**

Matrix aims to simplify parallel execution of potentially long-running or CPU-intensive operations in PHP. Instead of waiting for each task to complete sequentially, you can spawn child processes, run tasks concurrently, and handle their results asynchronously.

- **JavaScript-like async API**: The `async()` function returns promise-like objects with `then()` and `catch()`.
- **Built on `pcntl_fork()` and ReactPHP**: Achieves true parallelism by using multiple processes, integrated with React’s event loop for non-blocking IO.
- **Error Propagation**: Exceptions thrown in child processes are serialized and rethrown as exceptions in the parent, simplifying error handling.
- **Non-blocking Concurrency**: Your main code continues running while tasks proceed in parallel, improving efficiency for CPU-bound or blocking tasks.

### **Key Features**

- **Familiar `then()` and `catch()` interface** for handling asynchronous results.
- **Parallel execution via processes**: Offload heavy tasks to separate child processes.
- **Automatic error forwarding**: Rethrow child exceptions in the parent for consistent error handling.
- **Easy integration with existing code**: Just wrap your function calls with `async()` and chain handlers as needed.

---

## **Installation**

Install Matrix via Composer:

```bash
composer require jerome/matrix
```

Ensure the following PHP extensions are enabled:

- `pcntl`
- `sockets`

Matrix also relies on ReactPHP promises and event loop, installed automatically via Composer.

---

## **Asynchronous API Inspired by JavaScript**

Matrix provides a helper called `async()` that returns an `AsyncPromise`, mimicking JavaScript’s promise usage.

**Example:**

```php
use function async;

async(fn () => 'Task result')
    ->then(fn($result) => print($result . PHP_EOL))
    ->catch(fn($e) => print("Error: " . $e->getMessage()));
```

**What happens here?**

- `async()` forks a new child process using `AsyncProcessManager`.
- The child runs your callable and serializes the result or error.
- The parent listens on a socket via ReactPHP’s event loop.
- When the child finishes, the parent promise is resolved or rejected.
- The `AsyncPromise` provides a `.then()` for success and `.catch()` for errors, similar to JS promises.

### **Error Handling**

If the child task throws an exception, `catch()` handles it gracefully:

```php
async(fn () => throw new RuntimeException('Something went wrong'))
    ->then(fn($res) => print("Not called"))
    ->catch(fn($e) => print("Caught error: " . $e->getMessage() . PHP_EOL));
```

---

## **Under the Hood**

- **Forked Processes**: Calling `async()` triggers `AsyncProcessManager::fork()`, which spawns a new child process. This child executes your given callable in isolation.
- **IPC (Inter-Process Communication)**: Results or errors are serialized and sent back to the parent via a socket pair, using `stream_socket_pair()`.
- **ReactPHP Integration**: The parent uses `React\EventLoop` to add a read stream listener. This ensures your code never blocks, waiting for the child process’s response asynchronously.
- **Promises**: The `AsyncPromise` class wraps React’s `PromiseInterface` to provide a `.then()` and `.catch()` interface, making asynchronous code more intuitive.

---

## **Examples**

**Running Multiple Tasks in Parallel:**

```php
async(function () {
    usleep(500000); // Simulate half-second work
    return "Task A done";
})->then(fn($res) => print("$res\n"));

async(function () {
    usleep(500000); // Another half-second task
    return "Task B done";
})->then(fn($res) => print("$res\n"));

// Both tasks start around the same time, taking ~0.5s total if concurrent, rather than ~1s if sequential.
```

**Transforming Results with Thenable Chain:**

```php
async(fn() => 21)
    ->then(fn($val) => $val * 2)            // 42
    ->then(fn($val) => "The answer is $val") // "The answer is 42"
    ->then(fn($str) => print($str . PHP_EOL))
    ->catch(fn($e) => print("Error: " . $e->getMessage()));
```

---

## **Testing and Integration**

You can write integration tests to confirm asynchronous behavior. For example, test that two half-second tasks complete in ~0.5s total, ensuring concurrency works as intended:

```php
it('runs tasks concurrently', function () {
    $start = microtime(true);

    $p1 = async(fn() => (usleep(500000), 'Done A'));
    $p2 = async(fn() => (usleep(500000), 'Done B'));

    $bothDone = React\Promise\all([
        $p1->then(fn($r) => $r),
        $p2->then(fn($r) => $r),
    ]);

    $results = awaitPromise($bothDone);
    $elapsed = microtime(true) - $start;

    expect($results)->toEqual(['Done A', 'Done B']);
    expect($elapsed)->toBeLessThan(1.0); // Confirms concurrency
});
```

*(`awaitPromise()` is a helper test function that runs the event loop until the given promise resolves.)*

---

## **Common Issues and Troubleshooting**

- **Sequential Execution Instead of Parallel**:
  Ensure `pcntl` and `sockets` extensions are enabled. Also, ensure you are not blocking the main thread. The event loop must run for asynchronous behavior.

- **No Interleaved Output**:
  Console output might be buffered. If you rely on interleaving stdout, consider using `fflush(STDOUT)` in the child, or rely on timing tests to confirm concurrency.

- **Exception Types**:
  If the child throws an unknown exception type not autoloaded in the parent, it defaults to `RuntimeException`. Basic details (message, file, line, trace) are preserved.

---

## **API Reference**

### Global `async()` function

```php
function async(callable $callable): AsyncPromise
```

- Accepts a callable to run asynchronously.
- Returns `AsyncPromise`, providing `.then()` and `.catch()`.

### `Matrix\AsyncProcessManager`

- `fork(callable $callable)`: Forks a child process to run the callable. Returns a `React\Promise\PromiseInterface`.

### `Matrix\AsyncPromise`

- `then(callable $onFulfilled): self`
  Attaches a success handler.
- `catch(callable $onRejected): self`
  Attaches an error handler (alias to React’s `otherwise()`).

---

## **Contributing**

We welcome contributions. To contribute:

1. Fork the repository.
2. Create a new feature branch (`git checkout -b feature/my-feature`).
3. Commit your changes (`git commit -m 'Add new feature'`).
4. Push to the branch (`git push origin feature/my-feature`).
5. Open a pull request against `main`.

---

## **License**

Matrix is licensed under the MIT License. See the [LICENSE](LICENSE.md) file for more information.

---

## **Authors**

- **[Jerome Thayananthajothy]** - *Initial Work* - [Thavarshan](https://github.com/Thavarshan)

See the [contributors](https://github.com/Thavarshan/matrix/contributors) who participated in this project.
