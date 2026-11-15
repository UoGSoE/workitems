<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('workitems:make-admin {email : The email address of someone who already has an account}')]
#[Description('Make someone a developer, who can impersonate other people and use the people page')]
class MakeAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $person = User::current()->firstWhere('email', $this->argument('email'));

        if (! $person) {
            $this->error('Nobody who is still here has the email address '.$this->argument('email').'.');

            return self::FAILURE;
        }

        $person->update(['is_admin' => true]);

        $this->info($person->full_name.' is now a developer.');

        return self::SUCCESS;
    }
}
