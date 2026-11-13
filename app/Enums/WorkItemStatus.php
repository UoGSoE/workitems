<?php

namespace App\Enums;

enum WorkItemStatus: string
{
    case InProgress = 'in_progress';
    case Upcoming = 'upcoming';
    case Parked = 'parked';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'My plate',
            self::Upcoming => 'On the horizon',
            self::Parked => 'Parked',
            self::Done => 'Done',
        };
    }

    /**
     * The wording for the action that moves a work item to this status.
     */
    public function moveLabel(): string
    {
        return match ($this) {
            self::InProgress => 'Put on my plate',
            self::Upcoming => 'Put on the horizon',
            self::Parked => 'Park',
            self::Done => 'Done',
        };
    }
}
