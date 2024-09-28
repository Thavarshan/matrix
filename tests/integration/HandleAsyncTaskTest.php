<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Matrix\Exceptions\Handler;
use Matrix\Task;

define('MAX_RETRIES', 3);

/**
 * Integration test for asynchronous HTTP requests using Task and Fiber.
 */
test('handles multiple asynchronous HTTP requests', function () {
    $client = new Client(); // Guzzle client for making HTTP requests
    $errorHandler = new Handler(MAX_RETRIES, [\RuntimeException::class]); // Error handler
    $requests = [
        new Request('GET', 'https://jsonplaceholder.typicode.com/todos/2'),
        new Request('GET', 'https://jsonplaceholder.typicode.com/todos/1'),
        new Request('GET', 'https://jsonplaceholder.typicode.com/todos/3')
    ];

    // Array to store the results
    $results = [];

    // Create tasks for each request
    $tasks = array_map(function ($request, $key) use ($client, &$results, $errorHandler) {
        return new Task(function () use ($client, $request, $key, &$results, $errorHandler) {
            try {
                // Make the HTTP request and store the result
                $response = $client->send($request);
                $results[$key] = (string) $response->getBody();
            } catch (\Throwable $e) {
                // Handle any exceptions that occur during the request
                $errorHandler->handle($key, new Task(fn () => 'Dummy task'), $e);
            }
        });
    }, $requests, array_keys($requests));

    // Start all tasks asynchronously
    foreach ($tasks as $task) {
        $task->start();
    }

    // Assert that we have received results for all requests
    expect(count($results))->toEqual(3);

    // Assert that all responses contain valid JSON
    foreach ($results as $result) {
        expect(json_decode($result, true))->toBeArray();
    }

    // Optionally, check specific response data for each task
    expect(json_decode($results[0], true))->toMatchArray([
        'id' => 2,
        'title' => 'quis ut nam facilis et officia qui',
        'completed' => false,
    ]);

    expect(json_decode($results[1], true))->toMatchArray([
        'id' => 1,
        'title' => 'delectus aut autem',
        'completed' => false,
    ]);

    expect(json_decode($results[2], true))->toMatchArray([
        'id' => 3,
        'title' => 'fugiat veniam minus',
        'completed' => false,
    ]);
});

/**
 * Integration test for testing the async helper function and AsyncHelper class.
 */
test('executes multiple asynchronous tasks using async helper', function () {
    $client = new Client();
    $responses = [];
    $errors = [];

    // Define the async tasks (HTTP GET requests in this case)
    $requests = [
        new Request('GET', 'https://jsonplaceholder.typicode.com/todos/1'),
        new Request('GET', 'https://jsonplaceholder.typicode.com/todos/2'),
        new Request('GET', 'https://jsonplaceholder.typicode.com/todos/3'),
    ];

    // Create async tasks for each request
    foreach ($requests as $key => $request) {
        async(fn () => $client->send($request))
            ->then(function ($response) use (&$responses, $key) {
                // Store successful responses
                $responses[$key] = json_decode($response->getBody()->getContents(), true);
            })
            ->catch(function ($e) use (&$errors, $key) {
                // Store errors if any occur
                $errors[$key] = $e->getMessage();
            });
    }

    // Assert no errors occurred
    expect($errors)->toBeEmpty();

    // Verify we received responses for all tasks
    expect(count($responses))->toBe(count($requests));

    // Optional: Validate specific response data for each async task
    expect($responses[0])->toMatchArray([
        'id' => 1,
        'title' => 'delectus aut autem',
        'completed' => false,
    ]);

    expect($responses[1])->toMatchArray([
        'id' => 2,
        'title' => 'quis ut nam facilis et officia qui',
        'completed' => false,
    ]);

    expect($responses[2])->toMatchArray([
        'id' => 3,
        'title' => 'fugiat veniam minus',
        'completed' => false,
    ]);
});

/**
 * Integration test to validate asynchronous task behavior with error handling.
 */
test('handles asynchronous errors using async helper', function () {
    $client = new Client();
    $responses = [];
    $errors = [];

    // Simulate a mix of valid and invalid URLs
    $requests = [
        new Request('GET', 'https://jsonplaceholder.typicode.com/todos/1'),
        new Request('GET', 'https://invalid-url.com'), // Invalid URL to trigger an error
    ];

    // Create async tasks for each request
    foreach ($requests as $key => $request) {
        async(fn () => $client->send($request))
            ->then(function ($response) use (&$responses, $key) {
                // Store successful responses
                $responses[$key] = json_decode($response->getBody()->getContents(), true);
            })
            ->catch(function ($e) use (&$errors, $key) {
                // Store errors if any occur
                $errors[$key] = $e->getMessage();
            });
    }

    // Assert that an error occurred for the invalid URL
    expect($errors)->toHaveKey(1);
    expect($errors[1])->toContain('Could not resolve host');

    // Assert that the valid request succeeded
    expect($responses[0])->toMatchArray([
        'id' => 1,
        'title' => 'delectus aut autem',
        'completed' => false,
    ]);
});

/**
 * Integration test for async helper to verify asynchronous execution order.
 */
test('executes asynchronous tasks concurrently', function () {
    $responses = [];
    $timings = [];

    // Define the tasks with simulated delays
    async(function () use (&$timings) {
        usleep(500000); // 500ms delay
        $timings[] = 'Task 1 completed';

        return 'Task 1 result';
    })
    ->then(function ($res) use (&$responses) {
        $responses[] = $res;
    });

    async(function () use (&$timings) {
        usleep(300000); // 300ms delay
        $timings[] = 'Task 2 completed';

        return 'Task 2 result';
    })
    ->then(function ($res) use (&$responses) {
        $responses[] = $res;
    });

    // Ensure we have exactly 2 responses (both tasks completed)
    expect(count($responses))->toBe(2);

    // Check that both tasks are completed, ignoring the exact order
    expect($timings)->toContain('Task 1 completed');
    expect($timings)->toContain('Task 2 completed');

    // Optionally check the results, ignoring the order
    expect($responses)->toContain('Task 1 result');
    expect($responses)->toContain('Task 2 result');
});
