<?php

namespace App\Events;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PersonAddedToWorkItem
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public WorkItem $workItem,
        public User $person,
        public User $addedBy,
        public ?string $reason = null,
    ) {}
}
