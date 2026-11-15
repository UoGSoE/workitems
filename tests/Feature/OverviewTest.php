<?php

use App\Livewire\Overview;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

test('someone whose reports have people of their own sees the overview, and nobody else does', function () {
    $head = User::factory()->create();
    $lead = User::factory()->inTeam(Team::factory()->ledBy($head)->create())->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();

    $this->get(route('overview'))->assertRedirect(route('login'));

    $this->actingAs($head)->get(route('overview'))->assertOk()->assertSee('Overview');
    $this->actingAs($head)->get(route('home'))->assertSee(route('overview'));

    $this->actingAs($lead)->get(route('overview'))->assertForbidden();
    $this->actingAs($lead)->get(route('home'))->assertDontSee(route('overview'));
    $this->actingAs($developer)->get(route('overview'))->assertForbidden();
});

test('the numbers at the top count the unfinished work of everyone below me, however far down, and nobody else', function () {
    $this->travelTo('2026-11-18 09:00');
    $head = User::factory()->create();
    $lead = User::factory()->inTeam(Team::factory()->ledBy($head)->create())->create();
    [$developer, $sysadmin] = User::factory()->count(2)->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $someoneElsewhere = User::factory()->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create();
    WorkItem::factory()->onPlateOf($lead)->create(['effort_days' => 10]);
    WorkItem::factory()->onPlateOf($developer)->onHorizonOf($sysadmin, addedBy: $developer)->create(['effort_days' => 5, 'due_on' => '2026-11-17']);
    WorkItem::factory()->onPlateOf($sysadmin)->create(['due_on' => '2026-11-18']);
    WorkItem::factory()->lookingForAHand()->onPlateOf($sysadmin)->create();
    WorkItem::factory()->parked()->onPlateOf($developer)->create(['effort_days' => 3, 'due_on' => '2026-11-01']);
    WorkItem::factory()->done()->onPlateOf($developer)->create(['effort_days' => 100, 'due_on' => '2026-11-01']);
    WorkItem::factory()->onPlateOf($head)->create();
    WorkItem::factory()->onPlateOf($someoneElsewhere)->create(['due_on' => '2026-11-01']);

    Livewire::actingAs($head)
        ->test(Overview::class)
        ->assertSeeInOrder(['Open work', 'Past their by-when date', 'Parked', 'Classifieds'])
        ->assertViewHas('people', 3)
        ->assertViewHas('totals', ['open' => 4, 'days' => 15, 'pastByWhen' => 1, 'parked' => 1, 'lookingForAHand' => 1]);
});

test('each person who reports to me and has people of their own gets a card that rolls up everything beneath them, with no names or titles', function () {
    $this->travelTo('2026-11-18 09:00');
    $head = User::factory()->create();
    $headsTeam = Team::factory()->ledBy($head)->create(['name' => 'Heads of things']);
    $infrastructureLead = User::factory()->inTeam($headsTeam)->create(['forenames' => 'Jenny', 'surname' => 'McInfra']);
    $frontDeskLead = User::factory()->inTeam($headsTeam)->create(['forenames' => 'Olivia', 'surname' => 'McDesk']);
    User::factory()->inTeam($headsTeam)->create(['forenames' => 'Jenny', 'surname' => 'McAlone']);
    $subLead = User::factory()->inTeam(Team::factory()->ledBy($infrastructureLead)->create(['name' => 'Infrastructure leads']))->create(['forenames' => 'Jenny', 'surname' => 'McSublead']);
    $sysadmin = User::factory()->inTeam(Team::factory()->ledBy($subLead)->create(['name' => 'Infrastructure']))->create(['forenames' => 'Jenny', 'surname' => 'McSysadmin']);
    $hpcPerson = User::factory()->inTeam(Team::factory()->ledBy($infrastructureLead)->create(['name' => 'HPC']))->create();
    $frontDeskPerson = User::factory()->inTeam(Team::factory()->ledBy($frontDeskLead)->create(['name' => 'Front desk']))->create();
    WorkItem::factory()->onPlateOf($infrastructureLead)->create(['title' => 'Write the budget', 'effort_days' => 2]);
    WorkItem::factory()->onPlateOf($sysadmin)->onHorizonOf($hpcPerson, addedBy: $sysadmin)->create(['title' => 'Replace the storage array', 'effort_days' => 10, 'due_on' => '2026-11-01']);
    WorkItem::factory()->onPlateOf($hpcPerson)->create(['title' => 'Patch the cluster']);
    WorkItem::factory()->onPlateOf($frontDeskPerson)->create(['title' => 'Image the new laptops']);

    $this->actingAs($head)->get(route('overview'))
        ->assertOk()
        ->assertSeeInOrder([
            'Jenny McInfra', 'HPC and Infrastructure', '4 people, 3 open work items, about 12 days of work', '1 past its by-when date',
            'Olivia McDesk', 'Front desk', '2 people, 1 open work item',
        ])
        ->assertDontSee('Jenny McAlone')
        ->assertDontSee('Jenny McSublead')
        ->assertDontSee('Jenny McSysadmin')
        ->assertDontSee('Infrastructure leads')
        ->assertDontSee('Heads of things')
        ->assertDontSee('Replace the storage array')
        ->assertDontSee('Image the new laptops');
});

test('open work in the classifieds is counted and not listed, because the classifieds page already lists it', function () {
    $head = User::factory()->create();
    $lead = User::factory()->inTeam(Team::factory()->ledBy($head)->create())->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $someoneElsewhere = User::factory()->create();
    WorkItem::factory()->lookingForAHand()->onPlateOf($developer)->create(['title' => 'Move the apps into containers']);
    WorkItem::factory()->lookingForAHand()->parked()->onPlateOf($developer)->create(['title' => 'Tidy up the old wiki pages']);
    WorkItem::factory()->lookingForAHand()->onPlateOf($someoneElsewhere)->create(['title' => 'Update the samba servers']);

    Livewire::actingAs($head)
        ->test(Overview::class)
        ->assertViewHas('totals', fn (array $totals) => $totals['lookingForAHand'] === 1)
        ->assertDontSee('Move the apps into containers')
        ->assertDontSee('Olivia McUser');
});

test('secret work is left out of the overview altogether, even from the numbers', function () {
    $this->travelTo('2026-11-18 09:00');
    $head = User::factory()->create();
    $lead = User::factory()->inTeam(Team::factory()->ledBy($head)->create())->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    WorkItem::factory()->onPlateOf($developer)->create();
    WorkItem::factory()->secret()->onPlateOf($developer)->create(['effort_days' => 5, 'due_on' => '2026-11-01']);
    WorkItem::factory()->secret()->parked()->onPlateOf($developer)->create();

    Livewire::actingAs($head)
        ->test(Overview::class)
        ->assertViewHas('totals', ['open' => 1, 'days' => 0, 'pastByWhen' => 0, 'parked' => 0, 'lookingForAHand' => 0])
        ->assertSee('2 people, 1 open work item')
        ->assertDontSee('past its by-when date');
});
