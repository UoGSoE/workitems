<?php

namespace App\Enums;

/**
 * Where a work item sits for one of the people on it.
 */
enum PlateStatus: string
{
    case InProgress = 'in_progress';
    case Upcoming = 'upcoming';
}
