[![Matrix](./assets/Banner.jpg)](https://github.com/Thavarshan/matrix)

# Matrix

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jerome/matrix.svg)](https://packagist.org/packages/jerome/matrix)
[![Tests](https://github.com/Thavarshan/matrix/actions/workflows/run-tests.yml/badge.svg?label=tests&branch=main)](https://github.com/Thavarshan/matrix/actions/workflows/run-tests.yml)
[![Check & Fix Styling](https://github.com/Thavarshan/matrix/actions/workflows/laravel-pint.yml/badge.svg)](https://github.com/Thavarshan/matrix/actions/workflows/laravel-pint.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/jerome/matrix.svg)](https://packagist.org/packages/jerome/matrix)

Matrix is a PHP library that brings asynchronous, non-blocking functionality to PHP, inspired by JavaScript's `async`/`await` syntax. With Matrix, you can handle asynchronous tasks and manage concurrency using promises and a simple, intuitive API.

## Why Choose Matrix?

Matrix simplifies asynchronous programming in PHP by combining promises with ReactPHP's event loop. It supports non-blocking execution, seamless error handling, and easy integration with existing projects.

### Key Features

- **JavaScript-like API**: Use `async()` and `await()` for straightforward asynchronous programming.
- **Powered by ReactPHP**: Ensures non-blocking execution using ReactPHP's event loop.
- **Robust Error Handling**: Catch and handle exceptions with `.catch()` or `try-catch`.
- **Automatic Loop Management**: The event loop runs automatically to handle promise resolution.
- **Concurrent Operations**: Run multiple asynchronous tasks in parallel.

## Installation

Install Matrix via Composer:

```bash
composer require jerome/matrix
```

### Requirements

- PHP 8.0 or higher
- `sockets` extension enabled

ReactPHP promises and the event loop will be installed automatically via Composer.

## API Overview

### `async(callable $callable): PromiseInterface`

Wraps a callable into an asynchronous function that returns a promise.

#### Example

```php
use function Jerome\Matrix\async;

$func = async(fn () => 'Success');

$func->then(fn ($value) => echo $value) // Outputs: Success
    ->catch(fn ($e) => echo 'Error: ' . $e->getMessage());
```

### `await(PromiseInterface $promise): mixed`

Awaits the resolution of a promise and returns its value.

#### Example

```php
use function Jerome\Matrix\await;

try {
    $result = await(async(fn () => 'Success'));
    echo $result; // Outputs: Success
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage();
}
```

### `all(array $promises): PromiseInterface`

Runs multiple promises concurrently and returns a promise that resolves with an array of all results.

#### Example

```php
use function Jerome\Matrix\{async, await, all};

$promises = [
    async(fn () => 'Result 1'),
    async(fn () => 'Result 2'),
    async(fn () => 'Result 3'),
];

$results = await(all($promises));
// $results = ['Result 1', 'Result 2', 'Result 3']
```

### `race(array $promises): PromiseInterface`

Returns a promise that resolves with the value of the first resolved promise in the array.

#### Example

```php
use function Jerome\Matrix\{async, await, race};

$promises = [
    async(function () { sleep(2); return 'Slow'; }),
    async(function () { sleep(1); return 'Medium'; }),
    async(function () { return 'Fast'; }),
];

$result = await(race($promises));
// $result = 'Fast'
```

### `any(array $promises): PromiseInterface`

Returns a promise that resolves when any promise resolves, or rejects when all promises reject.

#### Example

```php
use function Jerome\Matrix\{async, await, any};

$promises = [
    async(function () { throw new \Exception('Error 1'); }),
    async(function () { return 'Success'; }),
    async(function () { throw new \Exception('Error 2'); }),
];

$result = await(any($promises));
// $result = 'Success'
```

## Examples

### Running Asynchronous Tasks

```php
$promise = async(fn () => 'Task Completed');

$promise->then(fn ($value) => echo $value) // Outputs: Task Completed
    ->catch(fn ($e) => echo 'Error: ' . $e->getMessage());
```

### Using the Await Syntax

```php
try {
    $result = await(async(fn () => 'Finished Task'));
    echo $result; // Outputs: Finished Task
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage();
}
```

### Handling Errors

```php
$promise = async(fn () => throw new \RuntimeException('Task Failed'));

$promise->then(fn ($value) => echo $value)
    ->catch(fn ($e) => echo 'Caught Error: ' . $e->getMessage()); // Outputs: Caught Error: Task Failed
```

### Chaining Promises

```php
$promise = async(fn () => 'First Operation')
    ->then(function ($result) {
        echo $result . "\n"; // Outputs: First Operation
        return async(fn () => $result . ' -> Second Operation');
    })
    ->then(function ($result) {
        echo $result; // Outputs: First Operation -> Second Operation
        return $result;
    });

await($promise); // Wait for all operations to complete
```

### Running Concurrent Operations

```php
$promise1 = async(fn () => 'Task 1');
$promise2 = async(fn () => 'Task 2');
$promise3 = async(fn () => 'Task 3');

// Use Matrix's all() helper to run promises concurrently
$allPromise = all([$promise1, $promise2, $promise3]);

$results = await($allPromise);
print_r($results); // Outputs: Array ( [0] => Task 1 [1] => Task 2 [2] => Task 3 )
```

#### Using race() to Get the First Resolved Promise

```php
$promise1 = async(function () {
    sleep(2);
    return 'Task 1 (slow)';
});

$promise2 = async(function () {
    sleep(1);
    return 'Task 2 (medium)';
});

$promise3 = async(function () {
    // This completes immediately
    return 'Task 3 (fast)';
});

// Get the result of whichever promise resolves first
$result = await(race([$promise1, $promise2, $promise3]));
echo $result; // Outputs: Task 3 (fast)
```

#### Using any() to Get the First Successful Promise

```php
$promise1 = async(function () {
    throw new \Exception('Task 1 failed');
});

$promise2 = async(function () {
    sleep(1);
    return 'Task 2 succeeded';
});

$promise3 = async(function () {
    throw new \Exception('Task 3 failed');
});

// Get the result of the first promise that succeeds
$result = await(any([$promise1, $promise2, $promise3]));
echo $result; // Outputs: Task 2 succeeded
```

## Performance Considerations

- **Event Loop**: Matrix uses ReactPHP's event loop, which should be run only once in your application.
- **Blocking Operations**: Avoid CPU-intensive tasks in async functions as they will block the event loop.
- **Memory Management**: Be mindful of memory usage when creating many promises, as they remain in memory until resolved.
- **Error Handling**: Always handle promise rejections to prevent unhandled promise rejection warnings.

## How It Works

1. **Event Loop Management**: The `async()` function schedules work on ReactPHP's event loop.
2. **Promise Interface**: Promises provide `then` and `catch` methods for handling success and errors.
3. **Synchronous Await**: The `await()` function runs the event loop until the promise is resolved or rejected.

## Testing

Run the test suite to ensure everything is working as expected:

```bash
composer test
```

## Contributing

We welcome contributions! To get started:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please make sure your code follows the project's coding standards and includes appropriate tests.

## License

Matrix is open-source software licensed under the MIT License.
