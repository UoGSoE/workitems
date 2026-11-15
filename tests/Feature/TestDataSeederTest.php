<?php

use App\Enums\PlateStatus;
use App\Enums\WorkItemVisibility;
use App\Livewire\Overview;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Database\Seeders\TestDataSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('the test data seeder gives the regular user a plate and a horizon to try out', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'member2x')->sole();
    expect($regularUser->workItems->where('pivot.status', PlateStatus::InProgress))->not->toBeEmpty()
        ->and($regularUser->workItems->where('pivot.status', PlateStatus::Upcoming))->not->toBeEmpty();
});

test('the test data seeder gives the regular user a work item a colleague added them to', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'member2x')->sole();
    expect($regularUser->workItems->filter(fn ($workItem) => $workItem->pivot->wasAddedBySomeoneElse()))->not->toBeEmpty();
});

test('the test data seeder gives five people to sign in as, one for each view of the app', function () {
    $this->seed(TestDataSeeder::class);

    [$member, $line, $lead, $manager, $admin] = collect(['member2x', 'line2x', 'lead2x', 'manager2x', 'admin2x'])
        ->map(fn (string $username) => User::where('username', $username)->sole());

    expect($member->leadsAnyone())->toBeFalse()
        ->and($member->lineManager()->is($line))->toBeTrue()
        ->and($line->reports->count())->toBeGreaterThanOrEqual(3)
        ->and($line->hasLeadsReportingToThem())->toBeFalse()
        ->and($line->lineManager()->is($lead))->toBeTrue()
        ->and($lead->hasLeadsReportingToThem())->toBeTrue()
        ->and($lead->unfinishedWorkItems())->not->toBeEmpty()
        ->and($lead->lineManager()->is($manager))->toBeTrue()
        ->and($manager->lineManager())->toBeNull()
        ->and($manager->everyoneBelow()->count())->toBe(User::current()->count() - 1)
        ->and($admin->is_admin)->toBeTrue()
        ->and(collect([$member, $line, $lead, $manager, $admin])->every(fn (User $person) => Hash::check('secret', $person->password)))->toBeTrue();
});

test('the test data seeder gives the regular user a work item of every kind of visibility', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'member2x')->sole();
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
        ->and(User::current()->where('username', 'member2x')->exists())->toBeTrue();
});

test('the test data seeder gives the shared work items a history to look at', function () {
    $this->seed(TestDataSeeder::class);

    $regularUser = User::where('username', 'member2x')->sole();
    $shared = $regularUser->workItems->filter(fn ($workItem) => $workItem->people()->count() > 1);
    expect($shared)->not->toBeEmpty()
        ->and($shared->every(fn ($workItem) => $workItem->history()->count() >= 2))->toBeTrue();
});

test('the test data seeder lays out a department of about thirty people, with something for the overview and the classifieds to show', function () {
    $this->seed(TestDataSeeder::class);

    $manager = User::where('username', 'manager2x')->sole();
    $lookingForAHand = WorkItem::where('visibility', WorkItemVisibility::LookingForAHand)->with('owner.team')->get();

    expect(User::current()->count())->toBeBetween(28, 34)
        ->and(Team::pluck('name')->all())->toContain('Infrastructure', 'Apps and data', 'Front desk', 'Compliance', 'Business continuity', 'HPC')
        ->and($lookingForAHand->pluck('owner.team.name')->unique()->count())->toBeGreaterThanOrEqual(2);

    Livewire::actingAs($manager)
        ->test(Overview::class)
        ->assertViewHas('areas', fn ($areas) => $areas->count() === 3 && $areas->where('pastByWhen', '>', 0)->count() === 2);
});
