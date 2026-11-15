<?php

namespace App\Events;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkItemsMoved
{
    use Dispatchable, SerializesModels;

    /**
     * Several work items moved from one person to another at once, and there is someone who should hear about it in one go.
     *
     * @param  string  $whatHappened  as the person being told would read it, eg "moved all of your open work to Jenny MacAdmin"
     * @param  Collection<int, WorkItem>  $workItems  the ones this person should hear about
     */
    public function __construct(
        public User $who,
        public User $tell,
        public string $whatHappened,
        public Collection $workItems,
    ) {}
}
