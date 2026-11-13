<?php

namespace App\Enums;

/**
 * The state of the work itself, shared by everyone on it.
 */
enum WorkItemStatus: string
{
    case Open = 'open';
    case Parked = 'parked';
    case Done = 'done';
}
