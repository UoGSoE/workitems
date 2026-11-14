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

    /**
     * What someone did to bring the work to this state, for the history, eg "parked it".
     */
    public function historyLine(): string
    {
        return match ($this) {
            self::Open => 'reopened it',
            self::Parked => 'parked it',
            self::Done => 'marked it done',
        };
    }
}
