<?php

namespace App\Events;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkItemTakenOver
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkItem $workItem,
        public User $newOwner,
        public User $previousOwner,
    ) {}
}
