<?php

namespace App\Listeners;

use App\Events\SomethingNoteworthyHappened;
use App\Models\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordActivity implements ShouldQueue
{
    public function handle(SomethingNoteworthyHappened $event): void
    {
        ActivityLog::create(['user_id' => $event->who->id, 'description' => $event->what]);
    }
}
