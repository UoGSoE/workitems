<?php

namespace App\Listeners;

use App\Events\WorkItemTakenOver;
use App\Mail\TakenOverWorkItemMail;
use Illuminate\Support\Facades\Mail;

class SendTakenOverWorkItemMail
{
    public function handle(WorkItemTakenOver $event): void
    {
        Mail::to($event->previousOwner)->queue(
            new TakenOverWorkItemMail($event->workItem, $event->newOwner)
        );
    }
}
