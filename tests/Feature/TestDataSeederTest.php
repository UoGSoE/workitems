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

test('the test data seeder gives the regular user a work item a colleague added them to', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'user2x')->sole();
    expect($regularUser->workItems->filter(fn ($workItem) => $workItem->pivot->wasAddedBySomeoneElse()))->not->toBeEmpty();
});

test('the test data seeder gives the shared work items a history to look at', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'user2x')->sole();
    $shared = $regularUser->workItems->filter(fn ($workItem) => $workItem->people()->count() > 1);
    expect($shared)->not->toBeEmpty()
        ->and($shared->every(fn ($workItem) => $workItem->history()->count() >= 2))->toBeTrue();
});
