[![Matrix](./assets/Banner.jpg)](https://github.com/Thavarshan/matrix)

# Matrix

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jerome/matrix.svg)](https://packagist.org/packages/jerome/matrix)
[![Tests](https://github.com/Thavarshan/matrix/actions/workflows/run-tests.yml/badge.svg?label=tests&branch=main)](https://github.com/Thavarshan/matrix/actions/workflows/run-tests.yml)
[![Check & fix styling](https://github.com/Thavarshan/matrix/actions/workflows/laravel-pint.yml/badge.svg)](https://github.com/Thavarshan/matrix/actions/workflows/laravel-pint.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/jerome/matrix.svg)](https://packagist.org/packages/jerome/matrix)

Matrix is a PHP library that provides asynchronous, non-blocking functionality inspired by JavaScript's `async`/`await` syntax. With Matrix, you can handle asynchronous tasks and manage concurrency in PHP using promises and a familiar, intuitive API.

## Why Matrix?

Matrix simplifies the execution of asynchronous tasks in PHP by combining promises with ReactPHP's event loop. It allows for non-blocking execution, error propagation, and easy integration with existing PHP projects.

### Key Features

- **JavaScript-like API**: Use `async()` and `await()` for straightforward asynchronous programming.
- **Built with ReactPHP**: Ensures non-blocking execution using ReactPHP's event loop.
- **Error Handling**: Catch and handle exceptions seamlessly with `.catch()` or `try-catch`.
- **Automatic Loop Management**: The event loop runs automatically to handle promise resolution.

## Installation

Install via Composer:

```bash
composer require jerome/matrix
```

Ensure the following extensions are enabled:

- `sockets`

Matrix relies on ReactPHP promises and event loop, which are installed automatically via Composer.

## API Overview

### `async(callable $callable): PromiseInterface`

Wraps a callable into an asynchronous function that returns a promise.

Example:

```php
use function async;

$func = async(fn () => 'Success');

$func->then(fn ($value) => echo $value) // Outputs: Success
    ->catch(fn ($e) => echo 'Error: ' . $e->getMessage());
```

### `await(PromiseInterface $promise): mixed`

Awaits the resolution of a promise and returns its value.

Example:

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

### Await Syntax

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
2. **Promise Interface**: Promises provide `then` and `catch` for handling success and errors.
3. **Synchronous Await**: The `await()` function allows synchronous-style code for promise resolution.

---

## Testing

Run the tests to ensure everything is working as expected:

```bash
composer test
```

## Contributing

Contributions are welcome! Fork the repository and create a pull request.

## License

Matrix is licensed under the MIT License.
