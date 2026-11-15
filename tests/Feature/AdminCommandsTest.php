<?php

use App\Models\User;

test('the make-admin command makes one person a developer, and refuses an email nobody has', function () {
    $person = User::factory()->create(['email' => 'jenny.mcuser@example.test']);
    $someoneElse = User::factory()->create();

    $this->artisan('workitems:make-admin', ['email' => 'jenny.mcuser@example.test'])->assertSuccessful();
    $this->artisan('workitems:make-admin', ['email' => 'nobody@example.test'])->assertFailed();

    expect($person->fresh()->is_admin)->toBeTrue()
        ->and($someoneElse->fresh()->is_admin)->toBeFalse()
        ->and(User::count())->toBe(2);
});

test('the bootstrap-user command creates a first developer, and refuses details that are already taken', function () {
    User::factory()->create(['username' => 'taken1x', 'email' => 'taken@example.test']);

    $this->artisan('workitems:bootstrap-user', ['username' => 'jma2y', 'email' => 'jenny.macadmin@example.test', 'surname' => 'MacAdmin', 'forenames' => 'Jenny'])->assertSuccessful();
    $this->artisan('workitems:bootstrap-user', ['username' => 'new1x', 'email' => 'taken@example.test', 'surname' => 'McUser', 'forenames' => 'Jenny'])->assertFailed();
    $this->artisan('workitems:bootstrap-user', ['username' => 'taken1x', 'email' => 'new@example.test', 'surname' => 'McUser', 'forenames' => 'Jenny'])->assertFailed();

    expect(User::count())->toBe(2)
        ->and(User::firstWhere('email', 'jenny.macadmin@example.test')->only('username', 'surname', 'forenames', 'is_staff', 'is_admin', 'is_people_admin'))->toBe([
            'username' => 'jma2y', 'surname' => 'MacAdmin', 'forenames' => 'Jenny', 'is_staff' => true, 'is_admin' => true, 'is_people_admin' => false,
        ]);
});
