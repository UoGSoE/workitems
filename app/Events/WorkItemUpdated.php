<?php

namespace App\Events;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkItemUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * Someone did something to a work item, eg "created it" or "marked it done".
     */
    public function __construct(
        public WorkItem $workItem,
        public User $who,
        public string $what,
    ) {}
}
