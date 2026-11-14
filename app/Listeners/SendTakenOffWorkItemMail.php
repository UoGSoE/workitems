<?php

namespace App\Listeners;

use App\Events\PersonTakenOffWorkItem;
use App\Mail\TakenOffWorkItemMail;
use Illuminate\Support\Facades\Mail;

class SendTakenOffWorkItemMail
{
    public function handle(PersonTakenOffWorkItem $event): void
    {
        Mail::to($event->tell)->queue(
            new TakenOffWorkItemMail($event->workItem, $event->who, $event->whatHappened, $event->message)
        );
    }
}
