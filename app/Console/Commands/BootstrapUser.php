<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

#[Signature('workitems:bootstrap-user {username} {email} {surname} {forenames}')]
#[Description('Create a first account, as a developer, so that someone can sign in and set up everyone else')]
class BootstrapUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $validator = Validator::make($this->arguments(), [
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'surname' => 'required|string|max:255',
            'forenames' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            collect($validator->errors()->all())->each(fn (string $message) => $this->error($message));

            return self::FAILURE;
        }

        $person = User::create([
            ...$validator->validated(),
            'is_staff' => true,
            'is_admin' => true,
            'password' => Str::random(64),
        ]);

        $this->info($person->full_name.' can now sign in, as a developer.');

        return self::SUCCESS;
    }
}
