<?php

declare(strict_types=1);

namespace Matrix;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;

class AsyncProcessManager
{
    /**
     * Forks the current process and runs the given callable in a child process asynchronously.
     *
     * The child process executes the callable and sends back serialized results or errors.
     * The parent process attaches a read listener to the event loop and returns a promise
     * that resolves or rejects when the child process data is available.
     *
     * @param  callable  $callable  The function to run in the child process.
     * @return PromiseInterface<mixed, \Throwable> Resolves with the child's result or rejects with an exception.
     *
     * @throws \RuntimeException If forking or IPC setup fails.
     */
    public function fork(callable $callable): PromiseInterface
    {
        [$parentSocket, $childSocket] = $this->createSocketPair();

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new \RuntimeException('Failed to fork process. Ensure pcntl extension is enabled.');
        }

        if ($pid === 0) {
            // Child process
            fclose($parentSocket);
            $this->runChildCallable($callable, $childSocket);
            exit(0);
        }

        // Parent process
        fclose($childSocket);

        return $this->createDeferredPromise($parentSocket, $pid);
    }

    /**
     * Create a pair of connected stream sockets for IPC.
     *
     * @return array{0: resource, 1: resource}
     *
     * @throws \RuntimeException If socket creation fails.
     */
    protected function createSocketPair(): array
    {
        $pipes = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if (! $pipes) {
            throw new \RuntimeException('Failed to create IPC pipe. Ensure sockets extension is enabled.');
        }

        return $pipes;
    }

    /**
     * Executes the given callable in the child process and writes the serialized result or error to the child socket.
     *
     * @param  callable  $callable  The function to execute in the child.
     * @param  resource  $childSocket  The socket to write the serialized result or error to.
     */
    protected function runChildCallable(callable $callable, $childSocket): void
    {
        try {
            $result = $callable();
            $data = ['result' => $result];
        } catch (\Throwable $e) {
            $data = [
                'error' => [
                    'type'    => get_class($e),
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ],
            ];
        }

        fwrite($childSocket, serialize($data));
        fclose($childSocket);
    }

    /**
     * Creates a deferred promise that resolves/rejects when the child's data is read.
     *
     * Registers a read event on the event loop. When data arrives, it processes it and fulfills or rejects the promise.
     *
     * @param  resource  $parentSocket  The parent socket to read data from.
     * @param  int  $pid  The process ID of the child process.
     * @return PromiseInterface<mixed, \Throwable>
     */
    protected function createDeferredPromise($parentSocket, int $pid): PromiseInterface
    {
        $deferred = new Deferred;

        Loop::addReadStream($parentSocket, function ($stream) use ($deferred, $parentSocket, $pid): void {
            $data = stream_get_contents($stream);
            fclose($parentSocket);

            pcntl_waitpid($pid, $status);
            Loop::removeReadStream($stream);

            $this->handleChildData($data, $deferred);
        });

        return $deferred->promise();
    }

    /**
     * Handles the raw data returned by the child process, unserializing and resolving/rejecting the promise.
     *
     * @param  string|false  $data  The raw serialized data or false on failure.
     * @param  Deferred  $deferred  The deferred object to resolve or reject.
     */
    protected function handleChildData($data, Deferred $deferred): void
    {
        if ($data === false || $data === '') {
            $deferred->reject(new \RuntimeException('No data received from child process.'));

            return;
        }

        $unserialized = unserialize($data, ['allowed_classes' => false]);

        if ($unserialized === false) {
            $deferred->reject(new \RuntimeException('Failed to unserialize data from child process.'));

            return;
        }

        if (! is_array($unserialized)) {
            // Given the current code always serializes an array, this scenario is unlikely.
            $deferred->reject(new \RuntimeException('Unserialized data is not an array. Invalid child process response.'));

            return;
        }

        if (isset($unserialized['error'])) {
            $exception = $this->createErrorException($unserialized['error']);
            $deferred->reject($exception);

            return;
        }

        $deferred->resolve($unserialized['result'] ?? null);
    }

    /**
     * Creates an exception instance from the child's error details.
     *
     * @param  array{type?: string, message?: string, file?: string, line?: int, trace?: string}  $errorDetails  The error details from the child.
     */
    protected function createErrorException(array $errorDetails): \Throwable
    {
        $errorType = $errorDetails['type'] ?? \RuntimeException::class;
        $errorMessage = $errorDetails['message'] ?? 'Unknown error in child process.';
        $errorFile = $errorDetails['file'] ?? 'unknown file';
        $errorLine = $errorDetails['line'] ?? 'unknown line';
        $errorTrace = $errorDetails['trace'] ?? '(no trace)';

        if (! is_subclass_of($errorType, \Throwable::class) && $errorType !== \Throwable::class) {
            $errorType = \RuntimeException::class;
        }

        $fullMessage = sprintf(
            "%s in %s on line %s\nTrace:\n%s",
            $errorMessage,
            $errorFile,
            (string) $errorLine,
            $errorTrace
        );

        return new $errorType($fullMessage);
    }
}
