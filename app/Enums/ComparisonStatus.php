<?php

namespace App\Enums;

enum ComparisonStatus: string
{
    case QUEUED = 'queued';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';
    case PENDING = 'pending';
}
