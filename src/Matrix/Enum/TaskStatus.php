<?php

declare(strict_types=1);

namespace Matrix\Enum;

enum TaskStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PAUSED = 'paused';
    case CANCELED = 'canceled';
}
