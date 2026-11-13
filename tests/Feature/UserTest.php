<?php

use App\Models\User;

test('a user can be created with the details single sign-on gives us', function () {
    User::create([
        'username' => 'abc1x',
        'email' => 'abc1x@example.test',
        'surname' => 'McUser',
        'forenames' => 'Jenny',
        'is_staff' => true,
        'password' => 'secret',
    ]);

    $user = User::sole();
    expect($user->username)->toBe('abc1x')
        ->and($user->surname)->toBe('McUser')
        ->and($user->forenames)->toBe('Jenny')
        ->and($user->is_staff)->toBeTrue()
        ->and($user->is_admin)->toBeFalse();
});
