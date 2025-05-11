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
- **Rate Limiting**: Control the frequency of asynchronous operations.
- **Promise Cancellation**: Cancel pending operations when they're no longer needed.
- **Retry Mechanism**: Automatically retry failed operations with configurable backoff strategies.
- **Batch Processing**: Process items in batches for improved performance.
- **Enhanced Error Handling**: Add context to errors for better debugging.

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

### Core Functions

#### `async(callable $callable): PromiseInterface`

Wraps a callable into an asynchronous function that returns a promise.

```php
use function Matrix\async;

$func = async(fn () => 'Success');

$func->then(fn ($value) => echo $value) // Outputs: Success
    ->catch(fn ($e) => echo 'Error: ' . $e->getMessage());
```

#### `await(PromiseInterface $promise, ?float $timeout = null): mixed`

Awaits the resolution of a promise and returns its value. Optionally accepts a timeout in seconds.

```php
use function Matrix\await;

try {
    $result = await(async(fn () => 'Success'));
    echo $result; // Outputs: Success

    // With timeout
    $result = await(async(fn () => sleep(2) && 'Delayed Success'), 3.0);
    echo $result; // Outputs: Delayed Success (or throws TimeoutException if it takes too long)
} catch (\Throwable $e) {
    echo 'Error: ' . $e->getMessage();
}
```

### Promise Combination

#### `all(array $promises): PromiseInterface`

Runs multiple promises concurrently and returns a promise that resolves with an array of all results.

```php
use function Matrix\{async, await, all};

$promises = [
    async(fn () => 'Result 1'),
    async(fn () => 'Result 2'),
    async(fn () => 'Result 3'),
];

$results = await(all($promises));
// $results = ['Result 1', 'Result 2', 'Result 3']
```

#### `race(array $promises): PromiseInterface`

Returns a promise that resolves with the value of the first resolved promise in the array.

```php
use function Matrix\{async, await, race};

$promises = [
    async(function () { sleep(2); return 'Slow'; }),
    async(function () { sleep(1); return 'Medium'; }),
    async(function () { return 'Fast'; }),
];

$result = await(race($promises));
// $result = 'Fast'
```

#### `any(array $promises): PromiseInterface`

Returns a promise that resolves when any promise resolves, or rejects when all promises reject.

```php
use function Matrix\{async, await, any};

$promises = [
    async(function () { throw new \Exception('Error 1'); }),
    async(function () { return 'Success'; }),
    async(function () { throw new \Exception('Error 2'); }),
];

$result = await(any($promises));
// $result = 'Success'
```

### Concurrency Control

#### `map(array $items, callable $callback, int $concurrency = 0, ?callable $onProgress = null): PromiseInterface`

Maps an array of items through an async function with optional concurrency control and progress tracking.

```php
use function Matrix\{async, await, map};

$urls = ['https://example.com', 'https://example.org', 'https://example.net'];

$results = await(map(
    $urls,
    function ($url) {
        // Fetch the URL contents asynchronously
        return async(function () use ($url) {
            $contents = file_get_contents($url);
            return strlen($contents);
        });
    },
    2, // Process 2 URLs at a time
    function ($done, $total) {
        echo "Processed $done of $total URLs\n";
    }
));

print_r($results); // Array of content lengths
```

#### `batch(array $items, callable $batchCallback, int $batchSize = 10, int $concurrency = 1): PromiseInterface`

Processes items in batches rather than one at a time for improved performance.

```php
use function Matrix\{async, await, batch};

$items = range(1, 100); // 100 items to process

$results = await(batch(
    $items,
    function ($batch) {
        return async(function () use ($batch) {
            // Process the entire batch at once
            return array_map(fn ($item) => $item * 2, $batch);
        });
    },
    25, // 25 items per batch
    2   // Process 2 batches concurrently
));

print_r($results); // Array of processed items
```

#### `pool(array $callables, int $concurrency = 5, ?callable $onProgress = null): PromiseInterface`

Executes an array of callables with limited concurrency.

```php
use function Matrix\{async, await, pool};

$tasks = [
    fn () => async(fn () => performTask(1)),
    fn () => async(fn () => performTask(2)),
    fn () => async(fn () => performTask(3)),
    // ...more tasks
];

$results = await(pool(
    $tasks,
    3, // Run 3 tasks concurrently
    function ($done, $total) {
        echo "Completed $done of $total tasks\n";
    }
));

print_r($results); // Array of task results
```

### Error Handling and Control

#### `timeout(PromiseInterface $promise, float $seconds, string $message = 'Operation timed out'): PromiseInterface`

Creates a promise that times out after a specified period.

```php
use function Matrix\{async, await, timeout};

try {
    $result = await(timeout(
        async(function () {
            sleep(5); // Long operation
            return 'Done';
        }),
        2.0, // 2 second timeout
        'The operation took too long'
    ));
} catch (\Matrix\Exceptions\TimeoutException $e) {
    echo $e->getMessage(); // "The operation took too long"
    echo "Duration: " . $e->getDuration() . " seconds"; // "Duration: 2 seconds"
}
```

#### `retry(callable $factory, int $maxAttempts = 3, ?callable $backoffStrategy = null): PromiseInterface`

Retries a promise-returning function multiple times until success or max attempts reached.

```php
use function Matrix\{async, await, retry};

try {
    $result = await(retry(
        function () {
            return async(function () {
                // Simulate an operation that sometimes fails
                if (rand(1, 3) === 1) {
                    return 'Success';
                }
                throw new \RuntimeException('Failed');
            });
        },
        5, // Try up to 5 times
        function ($attempt, $error) {
            // Exponential backoff with jitter
            $delay = min(pow(2, $attempt - 1) * 0.1, 5.0) * (0.8 + 0.4 * mt_rand() / mt_getrandmax());
            echo "Attempt $attempt failed: {$error->getMessage()}, retrying in {$delay}s...\n";
            return $delay; // Return null to stop retrying
        }
    ));

    echo "Finally succeeded: $result\n";
} catch (\Matrix\Exceptions\RetryException $e) {
    echo "All {$e->getAttempts()} attempts failed\n";

    foreach ($e->getFailures() as $index => $failure) {
        echo "Failure " . ($index + 1) . ": " . $failure->getMessage() . "\n";
    }
}
```

#### `cancellable(PromiseInterface $promise, callable $onCancel): CancellablePromise`

Creates a cancellable promise with a cleanup function.

```php
use function Matrix\{async, await, cancellable};

// Start a long operation
$operation = async(function () {
    // Simulate long computation
    for ($i = 0; $i < 10; $i++) {
        // Check for cancellation at safe points
        if (/* cancelled check */) {
            throw new \RuntimeException('Operation cancelled');
        }
        sleep(1);
    }
    return 'Completed';
});

// Create a clean-up function for when the operation is cancelled
$cleanup = function () {
    echo "Cleaning up resources...\n";
    // Release any held resources
};

// Create a cancellable promise
$cancellable = cancellable($operation, $cleanup);

// Later, when you need to cancel the operation
$cancellable->cancel();

// Check if it was cancelled
if ($cancellable->isCancelled()) {
    echo "Operation was cancelled.\n";
}
```

#### `withErrorContext(PromiseInterface $promise, string $context): PromiseInterface`

Enhances a promise with additional error context.

```php
use function Matrix\{async, await, withErrorContext};

try {
    await(withErrorContext(
        async(function () {
            throw new \RuntimeException('Database connection failed');
        }),
        'While initializing user service'
    ));
} catch (\Matrix\Exceptions\AsyncException $e) {
    // Outputs: "While initializing user service: Database connection failed"
    echo $e->getMessage() . "\n";

    // Original exception is preserved as the previous exception
    echo "Original error: " . $e->getPrevious()->getMessage() . "\n";
}
```

### Timing and Flow Control

#### `delay(float $seconds, mixed $value = null): PromiseInterface`

Creates a promise that resolves after a specified delay.

```php
use function Matrix\{async, await, delay};

$result = await(delay(2.0, 'Delayed result'));
echo $result; // Outputs: Delayed result (after 2 seconds)

// Can be used in promise chains
async(fn () => 'Step 1')
    ->then(function ($result) {
        echo "$result\n";
        return delay(1.0, $result . ' -> Step 2');
    })
    ->then(function ($result) {
        echo "$result\n";
    });
```

#### `waterfall(array $callables, mixed $initialValue = null): PromiseInterface`

Executes promises in sequence, passing the result of each to the next.

```php
use function Matrix\{async, await, waterfall};

$result = await(waterfall(
    [
        function ($value) {
            return async(fn () => $value . ' -> Step 1');
        },
        function ($value) {
            return async(fn () => $value . ' -> Step 2');
        },
        function ($value) {
            return async(fn () => $value . ' -> Step 3');
        }
    ],
    'Initial value'
));

echo $result; // Outputs: Initial value -> Step 1 -> Step 2 -> Step 3
```

#### `rateLimit(callable $fn, int $maxCalls, float $period): callable`

Creates a rate-limited version of an async function.

```php
use function Matrix\{async, await, rateLimit};

// Create a function that's limited to 2 calls per second
$limitedFetch = rateLimit(
    function ($url) {
        return async(function () use ($url) {
            return file_get_contents($url);
        });
    },
    2,  // Maximum 2 calls
    1.0 // Per 1 second
);

// Make multiple calls
$urls = [
    'https://example.com/api/1',
    'https://example.com/api/2',
    'https://example.com/api/3',
    'https://example.com/api/4',
    'https://example.com/api/5',
];

// These will automatically be rate-limited
foreach ($urls as $url) {
    $limitedFetch($url)->then(function ($response) use ($url) {
        echo "Fetched $url: " . strlen($response) . " bytes\n";
    });
}

// Wait for all to complete
await(\Matrix\Async::delay(10)); // Give time for requests to complete
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

### HTTP Requests Example

```php
use function Matrix\{async, await, map};

// Fetch multiple URLs concurrently
$urls = [
    'https://example.com',
    'https://example.org',
    'https://example.net'
];

$results = await(map(
    $urls,
    function ($url) {
        return async(function () use ($url) {
            $start = microtime(true);
            $contents = file_get_contents($url);
            $duration = microtime(true) - $start;

            return [
                'url' => $url,
                'size' => strlen($contents),
                'time' => round($duration, 2) . 's'
            ];
        });
    },
    2 // Process 2 at a time
));

// Display results
foreach ($results as $result) {
    echo "URL: {$result['url']}\n";
    echo "Size: {$result['size']} bytes\n";
    echo "Time: {$result['time']}\n\n";
}
```

### Database Operations Example

```php
use function Matrix\{async, await, pool};

// Define database operations as asynchronous tasks
$tasks = [
    function () {
        return async(function () {
            $db = new PDO('mysql:host=localhost;dbname=test', 'user', 'password');
            return $db->query('SELECT * FROM users LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        });
    },
    function () {
        return async(function () {
            $db = new PDO('mysql:host=localhost;dbname=test', 'user', 'password');
            return $db->query('SELECT * FROM products LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        });
    },
    function () {
        return async(function () {
            $db = new PDO('mysql:host=localhost;dbname=test', 'user', 'password');
            return $db->query('SELECT * FROM orders LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        });
    }
];

// Execute all database queries concurrently
[$users, $products, $orders] = await(pool($tasks));

// Process the data
echo "Found " . count($users) . " users\n";
echo "Found " . count($products) . " products\n";
echo "Found " . count($orders) . " orders\n";
```

### JIRA Integration Example

```php
use function Matrix\{async, await, batch, retry};

// Fetch JIRA tickets with retry support and batch processing
function fetchJiraTickets($projectKey, $batchSize = 50, $maxBatches = 10) {
    $apiUrl = "https://your-jira-instance.com/rest/api/2/search";
    $apiToken = "your-api-token";

    // Create batches of requests
    $batches = [];
    for ($i = 0; $i < $maxBatches; $i++) {
        $batches[] = [
            'url' => $apiUrl,
            'params' => [
                'jql' => "project = {$projectKey} ORDER BY created DESC",
                'startAt' => $i * $batchSize,
                'maxResults' => $batchSize
            ]
        ];
    }

    return await(batch(
        $batches,
        function ($batchItems) use ($apiToken) {
            return retry(
                function () use ($batchItems, $apiToken) {
                    return async(function () use ($batchItems, $apiToken) {
                        $results = [];

                        foreach ($batchItems as $item) {
                            $url = $item['url'] . '?' . http_build_query($item['params']);

                            $ch = curl_init();
                            curl_setopt_array($ch, [
                                CURLOPT_URL => $url,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_HTTPHEADER => [
                                    "Authorization: Bearer {$apiToken}",
                                    "Content-Type: application/json"
                                ]
                            ]);

                            $response = curl_exec($ch);
                            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);

                            if ($status !== 200) {
                                throw new \RuntimeException("API request failed with status {$status}");
                            }

                            $data = json_decode($response, true);
                            if (!isset($data['issues'])) {
                                throw new \RuntimeException("Unexpected response format");
                            }

                            $results = array_merge($results, $data['issues']);

                            // If we've received fewer issues than requested, we've reached the end
                            if (count($data['issues']) < $item['params']['maxResults']) {
                                break 2; // Exit both loops
                            }
                        }

                        return $results;
                    });
                },
                3, // 3 retry attempts
                function ($attempt, $error) {
                    echo "JIRA API request failed (attempt {$attempt}): {$error->getMessage()}\n";
                    return $attempt * 1.5; // Increasing delay between retries
                }
            );
        },
        2, // 2 items per batch
        3  // 3 concurrent batches
    ));
}

// Usage
try {
    $tickets = fetchJiraTickets('PROJ');
    echo "Fetched " . count($tickets) . " JIRA tickets\n";

    // Process tickets
    foreach ($tickets as $ticket) {
        echo "- {$ticket['key']}: {$ticket['fields']['summary']}\n";
    }
} catch (\Throwable $e) {
    echo "Error fetching JIRA tickets: " . $e->getMessage() . "\n";
}
```

## Advanced Testing

Matrix includes a comprehensive test suite to ensure reliability, including:

### Unit Tests

Test individual components in isolation:

```php
public function test_async_returns_promise(): void
{
    $result = async(fn () => 'test');
    $this->assertInstanceOf(PromiseInterface::class, $result);
}
```

### Integration Tests

Test how components work together in real-world scenarios:

```php
public function test_concurrent_operations(): void
{
    $tasks = [
        fn () => async(fn () => 'task1'),
        fn () => async(fn () => 'task2'),
        fn () => async(fn () => 'task3'),
    ];

    $results = await(pool($tasks, 2));
    $this->assertEquals(['task1', 'task2', 'task3'], $results);
}
```

### Error Handling Tests

Test that errors are properly handled and propagated:

```php
public function test_error_context_enhancement(): void
{
    try {
        await(withErrorContext(
            async(fn () => throw new \RuntimeException('Original error')),
            'Error context'
        ));
        $this->fail('Should have thrown an exception');
    } catch (AsyncException $e) {
        $this->assertStringContainsString('Error context', $e->getMessage());
        $this->assertInstanceOf(\RuntimeException::class, $e->getPrevious());
    }
}
```

### Concurrency Pattern Tests

Test various concurrency patterns:

```php
public function test_rate_limiting(): void
{
    $startTime = microtime(true);
    $results = [];

    $limitedFn = rateLimit(
        function ($i) use (&$results) {
            return async(function () use ($i, &$results) {
                $results[] = $i;
                return $i;
            });
        },
        2, // Max 2 calls
        0.5 // Per 0.5 seconds
    );

    $promises = [];
    for ($i = 1; $i <= 5; $i++) {
        $promises[] = $limitedFn($i);
    }

    await(all($promises));
    $duration = microtime(true) - $startTime;

    $this->assertGreaterThanOrEqual(1.0, $duration);
    $this->assertEquals([1, 2, 3, 4, 5], $results);
}
```

## Performance Considerations

- **Event Loop**: Matrix uses ReactPHP's event loop, which should be run only once in your application.
- **Blocking Operations**: Avoid CPU-intensive tasks in async functions as they will block the event loop.
- **Memory Management**: Be mindful of memory usage when creating many promises, as they remain in memory until resolved.
- **Error Handling**: Always handle promise rejections to prevent unhandled promise rejection warnings.
- **Concurrency Limits**: Use the concurrency parameters in `map()`, `batch()`, and `pool()` to control resource usage.
- **Rate Limiting**: Use `rateLimit()` when working with APIs that have rate limits to avoid being throttled.

## How It Works

1. **Event Loop Management**: The `async()` function schedules work on ReactPHP's event loop.
2. **Promise Interface**: Promises provide `then` and `catch` methods for handling success and errors.
3. **Synchronous Await**: The `await()` function runs the event loop until the promise is resolved or rejected.
4. **Concurrency Control**: Functions like `map()`, `batch()`, and `pool()` limit the number of concurrent operations.
5. **Error Handling**: Custom exception classes provide detailed information about failures.

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
