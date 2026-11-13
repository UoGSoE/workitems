<?php

use App\Enums\PlateStatus;
use App\Models\User;
use Database\Seeders\TestDataSeeder;

test('the test data seeder gives the regular user a plate and a horizon to try out', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'user2x')->sole();
    expect($regularUser->workItems->where('pivot.status', PlateStatus::InProgress))->not->toBeEmpty()
        ->and($regularUser->workItems->where('pivot.status', PlateStatus::Upcoming))->not->toBeEmpty();
});
