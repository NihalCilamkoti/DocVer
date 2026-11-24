<?php

namespace App\Enums;

enum FindingType: string
{
    case MATCHING = 'matching';
    case UNCHANGED = 'unchanged';
    case MISSING = 'missing';
    case ADDED = 'added';
    case REMOVED = 'removed';
    case MODIFIED = 'modified';
    case UNIQUE_A = 'unique_a';
    case UNIQUE_B = 'unique_b';
    case NOT_COMPARABLE = 'not_comparable';
    case NUMERICAL = 'numerical';
    case FINANCIAL = 'financial';
    case CONTRADICTION = 'contradiction';
}
