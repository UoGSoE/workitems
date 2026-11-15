<?php

use App\Enums\WorkItemStatus;
use App\Enums\WorkItemVisibility;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;

/*
 * Who can see a work item is written down twice: as a policy for one work item, and as a
 * scope for lists. Every test here asks both, so the two cannot drift apart.
 */
expect()->extend('toBeSeenBy', function (User $user) {
    expect($user->can('view', $this->value->fresh()))->toBeTrue('The policy says they cannot see it.')
        ->and(WorkItem::visibleTo($user)->whereKey($this->value->id)->exists())->toBeTrue('The scope leaves it out.');

    return $this;
});

expect()->extend('toBeHiddenFrom', function (User $user) {
    expect($user->can('view', $this->value->fresh()))->toBeFalse('The policy says they can see it.')
        ->and(WorkItem::visibleTo($user)->whereKey($this->value->id)->exists())->toBeFalse('The scope includes it.');

    return $this;
});

test('someone on a work item can see it, whoever else is allowed to', function (WorkItemVisibility $visibility) {
    $owner = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->onHorizonOf($colleague, addedBy: $owner)->create(['visibility' => $visibility]);

    expect($workItem)->toBeSeenBy($owner)->toBeSeenBy($colleague);
})->with(WorkItemVisibility::cases());

test('a lead can see what the people who report to them are on, but the lead above them cannot', function (WorkItemVisibility $visibility) {
    $manager = User::factory()->create();
    $lead = User::factory()->inTeam(Team::factory()->ledBy($manager)->create())->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $workItem = WorkItem::factory()->onPlateOf($developer)->create(['visibility' => $visibility]);

    expect($workItem)->toBeSeenBy($lead)->toBeHiddenFrom($manager);
})->with([WorkItemVisibility::Secret, WorkItemVisibility::PeopleOnIt]);

test('a work item that is looking for a hand can be seen by anyone, and nothing else can', function () {
    $owner = User::factory()->create();
    $nothingToDoWithIt = User::factory()->create();
    $admin = User::factory()->admin()->create();

    expect(WorkItem::factory()->lookingForAHand()->onPlateOf($owner)->create())->toBeSeenBy($nothingToDoWithIt)->toBeSeenBy($admin)
        ->and(WorkItem::factory()->visibleToTeam()->onPlateOf($owner)->create())->toBeHiddenFrom($nothingToDoWithIt)->toBeHiddenFrom($admin)
        ->and(WorkItem::factory()->onPlateOf($owner)->create())->toBeHiddenFrom($nothingToDoWithIt)->toBeHiddenFrom($admin)
        ->and(WorkItem::factory()->secret()->onPlateOf($owner)->create())->toBeHiddenFrom($nothingToDoWithIt)->toBeHiddenFrom($admin);
});

test('a work item the owner shows to their team is seen by the people who report to the same lead', function () {
    $lead = User::factory()->create();
    $team = Team::factory()->ledBy($lead)->create();
    $owner = User::factory()->inTeam($team)->create();
    $teamMate = User::factory()->inTeam($team)->create();
    $inAnotherTeam = User::factory()->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create();
    $workItem = WorkItem::factory()->visibleToTeam()->onPlateOf($owner)->create();

    expect($workItem)->toBeSeenBy($teamMate)->toBeSeenBy($lead)->toBeHiddenFrom($inAnotherTeam);
});

test('a work item a lead shows to their team is seen by the people they lead, and not by the other leads', function () {
    $manager = User::factory()->create();
    $managersTeam = Team::factory()->ledBy($manager)->create();
    $lead = User::factory()->inTeam($managersTeam)->create();
    $fellowLead = User::factory()->inTeam($managersTeam)->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $fellowLeadsReport = User::factory()->inTeam(Team::factory()->ledBy($fellowLead)->create())->create();
    $workItem = WorkItem::factory()->visibleToTeam()->onPlateOf($lead)->create();

    expect($workItem)->toBeSeenBy($developer)->toBeSeenBy($manager)->toBeHiddenFrom($fellowLead)->toBeHiddenFrom($fellowLeadsReport);
});

test('a work item shown to the owner\'s team is not shown to the team of someone else who is on it', function () {
    $owner = User::factory()->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create();
    $otherTeam = Team::factory()->ledBy(User::factory()->create())->create();
    $helperFromOtherTeam = User::factory()->inTeam($otherTeam)->create();
    $helpersTeamMate = User::factory()->inTeam($otherTeam)->create();
    $workItem = WorkItem::factory()->visibleToTeam()->onPlateOf($owner)->onHorizonOf($helperFromOtherTeam, addedBy: $owner)->create();

    expect($workItem)->toBeSeenBy($helperFromOtherTeam)->toBeSeenBy($otherTeam->lead)->toBeHiddenFrom($helpersTeamMate);
});

test('narrowing the list of work items someone can see never lets in one they cannot', function () {
    $user = User::factory()->create();
    $someoneElse = User::factory()->create();
    $mine = WorkItem::factory()->onPlateOf($user)->create();
    $mineButDone = WorkItem::factory()->done()->onPlateOf($user)->create();
    $theirsAndPrivate = WorkItem::factory()->onPlateOf($someoneElse)->create();
    $theirsForEveryoneButDone = WorkItem::factory()->done()->lookingForAHand()->onPlateOf($someoneElse)->create();

    expect(WorkItem::visibleTo($user)->where('status', WorkItemStatus::Open)->get()->modelKeys())->toBe([$mine->id])
        ->and(WorkItem::where('status', WorkItemStatus::Open)->visibleTo($user)->get()->modelKeys())->toBe([$mine->id]);
});

test('the policy and the scope agree about every person and every work item in a four-level organisation', function () {
    $head = User::factory()->create();
    $manager = User::factory()->inTeam(Team::factory()->ledBy($head)->create())->create();
    $leads = User::factory()->count(2)->inTeam(Team::factory()->ledBy($manager)->create())->create();
    $leads->each(fn (User $lead) => User::factory()->count(2)->inTeam(Team::factory()->ledBy($lead)->create())->create());
    User::factory()->create();
    Team::factory()->ledBy(User::factory()->inTeam($leads->first()->ledTeams->first())->create())->create();

    $everyone = User::with('reports', 'team.lead.reports')->get();
    $everyone->each(function (User $owner, int $position) use ($everyone) {
        $someoneElse = $everyone[($position + 3) % $everyone->count()];

        foreach (WorkItemVisibility::cases() as $visibility) {
            WorkItem::factory()->onPlateOf($owner)->onHorizonOf($someoneElse, addedBy: $owner)->create(['visibility' => $visibility]);
        }
    });

    $disagreements = WorkItem::with('people', 'owner.reports', 'owner.team.lead.reports')->get()->crossJoin($everyone)
        ->reject(fn (array $pair) => $pair[1]->can('view', $pair[0]) === WorkItem::visibleTo($pair[1])->whereKey($pair[0]->id)->exists())
        ->map(fn (array $pair) => "{$pair[0]->visibility->value} work item {$pair[0]->id} and person {$pair[1]->id}");

    expect($disagreements->all())->toBe([])
        ->and(WorkItem::count())->toBe($everyone->count() * 4);
});
