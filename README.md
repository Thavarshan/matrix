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

## Installation

Install Matrix via Composer:

```bash
composer require jerome/matrix
```

Ensure the following extension is enabled:

- `sockets`

ReactPHP promises and the event loop will be installed automatically via Composer.

## API Overview

### `async(callable $callable): PromiseInterface`

Wraps a callable into an asynchronous function that returns a promise.

#### Example:

```php
use function async;

$func = async(fn () => 'Success');

$func->then(fn ($value) => echo $value) // Outputs: Success
    ->catch(fn ($e) => echo 'Error: ' . $e->getMessage());
```

### `await(PromiseInterface $promise): mixed`

Awaits the resolution of a promise and returns its value.

#### Example:

```php
use function await;

try {
    $result = await(async(fn () => 'Success'));
    echo $result; // Outputs: Success
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage();
}
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

## How It Works

1. **Event Loop Management**: The `async()` function ensures the event loop runs until the promise is resolved or rejected.
2. **Promise Interface**: Promises provide `then` and `catch` methods for handling success and errors.
3. **Synchronous Await**: The `await()` function allows synchronous-style code to resolve promises.

## Testing

Run the test suite to ensure everything is working as expected:

```bash
composer test
```

## Contributing

We welcome contributions! To get started, fork the repository and create a pull request with your changes.

## License

Matrix is open-source software licensed under the MIT License.

