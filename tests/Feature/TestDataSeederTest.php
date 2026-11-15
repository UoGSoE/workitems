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

test('the test data seeder gives the regular user a team to lead and someone to report to', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'user2x')->sole();
    $admin = User::where('username', 'admin2x')->sole();
    expect($regularUser->reports())->toHaveCount(2)
        ->and($regularUser->lineManager()->is($admin))->toBeTrue()
        ->and($regularUser->job_title)->not->toBeNull()
        ->and($admin->lineManager())->not->toBeNull();
});

test('the test data seeder gives the regular user a work item of every kind of visibility', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'user2x')->sole();
    $kindsTheyHave = $regularUser->workItems->pluck('visibility.value')->unique()->sort()->values()->all();
    expect($kindsTheyHave)->toBe(['looking_for_a_hand', 'people_on_it', 'secret', 'team']);
});

test('the test data seeder gives everyone who reports to someone big work with rough dates and small work without', function () {
    $this->seed(TestDataSeeder::class);

    $peopleWithALead = User::current()->whereNotNull('team_id')->get();
    expect($peopleWithALead->count())->toBeGreaterThanOrEqual(5)
        ->and($peopleWithALead->every(fn (User $person) => $person->unfinishedWorkItems()->filter->hasBallparkDates()->count() >= 2))->toBeTrue()
        ->and($peopleWithALead->every(fn (User $person) => $person->unfinishedWorkItems()->reject->hasBallparkDates()->count() >= 2))->toBeTrue();
});

test('the test data seeder includes someone who manages people without being a developer', function () {
    $this->seed(TestDataSeeder::class);

    expect(User::where('is_people_admin', true)->where('is_admin', false)->count())->toBe(1)
        ->and(User::where('username', 'admin2x')->sole()->is_admin)->toBeTrue();
});

test('the test data seeder includes someone who has left', function () {
    $this->seed(TestDataSeeder::class);

    expect(User::whereNotNull('left_on')->count())->toBe(1)
        ->and(User::current()->where('username', 'user2x')->exists())->toBeTrue();
});

test('the test data seeder gives the shared work items a history to look at', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'user2x')->sole();
    $shared = $regularUser->workItems->filter(fn ($workItem) => $workItem->people()->count() > 1);
    expect($shared)->not->toBeEmpty()
        ->and($shared->every(fn ($workItem) => $workItem->history()->count() >= 2))->toBeTrue();
});
