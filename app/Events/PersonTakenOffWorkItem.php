<?php

namespace App\Events;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PersonTakenOffWorkItem
{
    use Dispatchable, SerializesModels;

    /**
     * Someone came off a work item, and there is a person who should hear about it.
     *
     * @param  string  $whatHappened  as the person being told would read it, eg "took you off"
     */
    public function __construct(
        public WorkItem $workItem,
        public User $who,
        public User $tell,
        public string $whatHappened,
        public ?string $message = null,
    ) {}
}
