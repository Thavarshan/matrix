<?php

declare(strict_types=1);

namespace Matrix\Exceptions;

/** Exception used to settle a Matrix promise cancelled by its consumer. */
final class CancellationException extends AsyncException
{
    public function __construct(string $message = 'Operation cancelled')
    {
        parent::__construct($message);
    }
}
