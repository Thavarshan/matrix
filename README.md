[![Matrix](./assets/Banner.jpg)](https://github.com/Thavarshan/matrix)

# Matrix

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jerome/matrix.svg)](https://packagist.org/packages/jerome/matrix)
[![Tests](https://github.com/Thavarshan/matrix/actions/workflows/run-tests.yml/badge.svg?label=tests&branch=main)](https://github.com/Thavarshan/matrix/actions/workflows/run-tests.yml)
[![Check & fix styling](https://github.com/Thavarshan/matrix/actions/workflows/laravel-pint.yml/badge.svg)](https://github.com/Thavarshan/matrix/actions/workflows/laravel-pint.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/jerome/matrix.svg)](https://packagist.org/packages/jerome/matrix)

**Matrix** is a cutting-edge PHP library for asynchronous task management, inspired by JavaScript’s `async`/`await` paradigm but leveraging PHP's native Fibers. Matrix provides a streamlined, non-blocking API to run tasks, manage errors, and handle results—all without the need for explicit task starting.

Matrix also supports manual task management through the `Task` class and robust error handling through a customizable `ErrorHandler`.

---

## **Why Matrix?**

Matrix brings a **JavaScript-like async experience** to PHP, providing developers with the simplicity and power of managing asynchronous tasks without complexity.

- **JavaScript-like async API**: Matrix introduces a simple and elegant API inspired by JavaScript’s async functions.
- **Built on PHP Fibers**: Using PHP 8.1+ Fibers, Matrix delivers true non-blocking concurrency.
- **Customizable Error Handling**: Handle task failures gracefully with an extensible error handler.
- **Manage Task Lifecycles**: Direct control over task status with methods for pausing, resuming, and canceling tasks.

### **Key Features**

- **JavaScript-like async/await API** for seamless asynchronous task execution.
- **Non-blocking concurrency**: Built on top of PHP 8.1+ Fibers.
- **Error handling** with custom error recovery using the `Handler` class.
- **Task lifecycle management**: Start, pause, resume, cancel, or retry tasks.

---

## **Installation**

Install Matrix via Composer:

```bash
composer require jerome/matrix
```

Matrix requires PHP 8.1 or above.

---

## **JavaScript-like Async API**

Matrix brings the familiarity of JavaScript's `async`/`await` into PHP, making it incredibly easy to work with asynchronous tasks. Here's how you can use it:

```php
use Matrix\AsyncHelper;
use function Matrix\async;

// Execute an asynchronous task with success and error handling
async(fn () => 'Task result')
    ->then(function ($result) {
        echo $result; // Output: Task result
    })
    ->catch(function ($e) {
        echo $e->getMessage(); // Handle any errors
    });
```

This **JavaScript-like API** allows you to define tasks, handle success, and catch errors seamlessly—without needing to call a `start` method explicitly.

### **Handling Errors in Async Tasks**

Matrix also makes it easy to handle errors in asynchronous tasks:

```php
async(fn () => throw new \RuntimeException('Error occurred'))
    ->catch(function ($e) {
        echo "Caught error: " . $e->getMessage(); // Output: Caught error: Error occurred
    });
```

The `catch()` method allows you to define an error handler, making it straightforward to manage exceptions during task execution.

---

## **Task Management with the Task Class**

If you prefer more manual control over your tasks, Matrix provides the `Task` class, allowing you to directly manage task lifecycles.

### **Creating and Managing Tasks**

```php
use Matrix\Task;

// Define a task that performs an operation
$task = new Task(function () {
    for ($i = 0; $i < 3; $i++) {
        echo "Working...\n";
        \Fiber::suspend(); // Pause execution and yield control
    }
    return "Task completed";
});

// Start the task
$task->start();

// Resume the task until it completes
while (!$task->isCompleted()) {
    $task->resume();
}

echo $task->getResult(); // Output: Task completed
```

### **Pausing and Resuming Tasks**

Matrix allows you to pause and resume tasks at will:

```php
$task->pause(); // Pause the task
$task->resume(); // Resume the task
```

### **Task Status Management**

Each task has a status (`PENDING`, `RUNNING`, `PAUSED`, `COMPLETED`, `FAILED`, `CANCELED`) that can be queried using the `getStatus()` method.

---

## **Error Handling with the Handler Class**

Matrix provides robust error handling through the `Handler` class. This class allows you to define retry logic, error logging, and final failure handling.

### **Example of Error Handling with Task**

```php
use Matrix\Task;
use Matrix\Exceptions\Handler;

// Define an error handler
$errorHandler = new Handler(3, [\RuntimeException::class]);

// Create a task that throws an exception
$task = new Task(function () {
    throw new \RuntimeException('Something went wrong');
}, $errorHandler);

try {
    $task->start();
} catch (\Throwable $e) {
    $errorHandler->handle('task_1', $task, $e);
}

// Retry the task if it failed
if ($task->getStatus() === TaskStatus::FAILED) {
    $task->retry();
}
```

The `Handler` class can automatically retry tasks or log errors, making it highly customizable.

---

## **API Reference**

### `Matrix\AsyncHelper`

- `then(callable $callback)`: Registers a success callback and starts the task.
- `catch(callable $callback)`: Registers an error callback and starts the task.
- `start()`: Manually starts the task (called automatically when using `then` or `catch`).

### `Matrix\Task`

- `start()`: Starts the task execution.
- `pause()`: Pauses the task if it’s running.
- `resume()`: Resumes a paused task.
- `cancel()`: Cancels the task.
- `retry()`: Retries a task if it has failed or completed.
- `getStatus()`: Returns the task status (`PENDING`, `RUNNING`, `PAUSED`, `COMPLETED`, etc.).
- `getResult()`: Retrieves the result of the task once completed.

### `Matrix\Exceptions\Handler`

- `handle($contextId, $context, Throwable $e)`: Handles task errors.
- `retryTask($taskId, Task $task)`: Retries a task.
- `handleFinalFailure($taskId, Task $task)`: Handles a task’s final failure after all retries are exhausted.

---

## **Contributing**

We welcome contributions! To contribute:

1. Fork the repository.
2. Create a new branch (`git checkout -b feature/new-feature`).
3. Make your changes and commit (`git commit -m 'Add new feature'`).
4. Push your branch (`git push origin feature/new-feature`).
5. Open a pull request!

---

## **License**

Matrix is licensed under the MIT License. See the [LICENSE](LICENSE.md) file for more information.

---

## Authors

- **[Jerome Thayananthajothy]** - *Initial work* - [Thavarshan](https://github.com/Thavarshan)

See also the list of [contributors](https://github.com/Thavarshan/fetch-php/contributors) who participated in this project.

## Acknowledgments

- Special thanks to the PHP community for their support and inspiration for this project.

## **Get Involved**

Matrix offers a unique PHP async experience, bringing true concurrency and fiber-based task management to PHP developers. **Star the repository on GitHub** to help Matrix grow and to stay updated on new features.
