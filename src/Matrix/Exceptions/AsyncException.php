<?php

declare(strict_types=1);

namespace Matrix\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base exception class for Matrix async operations.
 */
class AsyncException extends RuntimeException
{
    /**
     * Create a new async exception.
     *
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  Throwable|null  $previous  The previous throwable
     */
    public function __construct(string $message, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function withContext(string $context): static
    {
        // Exceptions cannot be cloned. Copy the existing subtype and its metadata
        // without invoking an unknown subclass constructor.
        $class = new \ReflectionObject($this);
        $copy = $class->newInstanceWithoutConstructor();
        do {
            foreach ($class->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $class->getName()
                    || $property->isStatic() || ! $property->isInitialized($this)) {
                    continue;
                }
                $property->setValue($copy, $property->getValue($this));
            }
            $class = $class->getParentClass();
        } while ($class !== false);

        (new \ReflectionProperty(\Exception::class, 'message'))->setValue($copy, $context . ': ' . $this->getMessage());
        (new \ReflectionProperty(\Exception::class, 'previous'))->setValue($copy, $this);

        return $copy;
    }
}
