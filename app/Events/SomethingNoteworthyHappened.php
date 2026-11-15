<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SomethingNoteworthyHappened
{
    use Dispatchable, SerializesModels;

    /**
     * Someone did something worth keeping a record of, eg "made Olivia McUser the lead of Developers".
     */
    public function __construct(
        public User $who,
        public string $what,
    ) {}
}
