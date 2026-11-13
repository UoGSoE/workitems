<?php

use App\Enums\WorkItemStatus;
use App\Models\User;
use Database\Seeders\TestDataSeeder;

test('the test data seeder gives the regular user a plate and a horizon to try out', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'user2x')->sole();
    expect($regularUser->workItems->where('status', WorkItemStatus::InProgress))->not->toBeEmpty()
        ->and($regularUser->workItems->where('status', WorkItemStatus::Upcoming))->not->toBeEmpty();
});
