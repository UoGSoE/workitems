<?php

namespace App\Listeners;

use App\Events\WorkItemUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordWorkItemHistory implements ShouldQueue
{
    public function handle(WorkItemUpdated $event): void
    {
        $event->workItem->addHistory($event->who, $event->what);
    }
}
