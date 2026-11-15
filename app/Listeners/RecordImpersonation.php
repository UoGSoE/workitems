<?php

namespace App\Listeners;

use App\Events\SomethingNoteworthyHappened;
use Lab404\Impersonate\Events\LeaveImpersonation;
use Lab404\Impersonate\Events\TakeImpersonation;

/**
 * Keeps a record each time a developer steps into, or out of, someone else's view of the app.
 */
class RecordImpersonation
{
    public function handleTake(TakeImpersonation $event): void
    {
        event(new SomethingNoteworthyHappened($event->impersonator, 'started impersonating '.$event->impersonated->full_name));
    }

    public function handleLeave(LeaveImpersonation $event): void
    {
        event(new SomethingNoteworthyHappened($event->impersonator, 'stopped impersonating '.$event->impersonated->full_name));
    }
}
