<?php

namespace App\Listeners;

use App\Events\PersonAddedToWorkItem;
use App\Mail\AddedToWorkItemMail;
use Illuminate\Support\Facades\Mail;

class SendAddedToWorkItemMail
{
    public function handle(PersonAddedToWorkItem $event): void
    {
        Mail::to($event->person)->queue(
            new AddedToWorkItemMail($event->workItem, $event->addedBy, $event->reason)
        );
    }
}
