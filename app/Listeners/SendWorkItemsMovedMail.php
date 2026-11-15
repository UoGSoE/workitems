<?php

namespace App\Listeners;

use App\Events\WorkItemsMoved;
use App\Mail\WorkItemsMovedMail;
use Illuminate\Support\Facades\Mail;

class SendWorkItemsMovedMail
{
    public function handle(WorkItemsMoved $event): void
    {
        Mail::to($event->tell)->queue(
            new WorkItemsMovedMail($event->who, $event->whatHappened, $event->workItems)
        );
    }
}
