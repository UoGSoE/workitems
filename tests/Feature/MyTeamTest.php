<?php

use App\Livewire\MyTeam;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('a lead sees the people who report to them, and nobody further away', function () {
    $lead = User::factory()->create();
    $team = Team::factory()->ledBy($lead)->create();
    User::factory()->inTeam($team)->create(['forenames' => 'Olivia', 'surname' => 'McUser', 'job_title' => 'Developer']);
    $subLead = User::factory()->inTeam($team)->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    User::factory()->inTeam(Team::factory()->ledBy($subLead)->create())->create(['forenames' => 'Jenny', 'surname' => 'McFurther']);
    User::factory()->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create(['forenames' => 'Jenny', 'surname' => 'McElsewhere']);

    $this->get(route('my-team'))->assertRedirect(route('login'));

    $this->actingAs($lead)->get(route('my-team'))
        ->assertOk()
        ->assertSeeInOrder(['Jenny MacAdmin', 'Olivia McUser'])
        ->assertDontSee('Developer')
        ->assertDontSee('Jenny McFurther')
        ->assertDontSee('Jenny McElsewhere');
    $this->actingAs($lead)->get(route('home'))->assertSee(route('my-team'));
});

test('work with a rough start and a by-when date is drawn on the timeline, and the rest is counted and listed', function () {
    $this->travelTo('2026-11-18 09:00');
    $lead = User::factory()->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    WorkItem::factory()->onPlateOf($developer)->create(['title' => 'Already under way', 'starts_on' => '2026-10-01', 'due_on' => '2026-11-27']);
    WorkItem::factory()->onPlateOf($developer)->create(['title' => 'Replace the storage array', 'starts_on' => '2026-11-30', 'due_on' => '2026-12-18']);
    WorkItem::factory()->onHorizonOf($developer)->create(['title' => 'Move the apps into containers', 'starts_on' => '2027-01-11', 'due_on' => '2027-02-26']);
    WorkItem::factory()->onPlateOf($developer)->create(['title' => 'Update the samba servers']);
    WorkItem::factory()->onPlateOf($developer)->create(['title' => 'Renew the wildcard certificate', 'due_on' => '2026-12-01']);
    WorkItem::factory()->onHorizonOf($developer)->create(['title' => 'Tidy up the old wiki pages']);
    WorkItem::factory()->parked()->onPlateOf($developer)->create(['title' => 'Rotate the signing keys', 'starts_on' => '2026-11-30', 'due_on' => '2026-12-18']);
    WorkItem::factory()->done()->onPlateOf($developer)->create(['title' => 'Order the replacement disks']);

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->assertSeeInOrder(['Already under way, on their plate, 1 Oct 2026 to 27 Nov 2026', 'grid-column: 1 / 3'], escape: false)
        ->assertSeeInOrder(['Replace the storage array, on their plate, 30 Nov 2026 to 18 Dec 2026', 'grid-column: 3 / 6'], escape: false)
        ->assertSeeInOrder(['Move the apps into containers, on their horizon, 11 Jan 2027 to 26 Feb 2027', 'grid-column: 9 / 16'], escape: false)
        ->assertSeeInOrder(['3 other things', '+ 3', 'On their plate', 'Update the samba servers', 'Renew the wildcard certificate', 'On their horizon', 'Tidy up the old wiki pages'])
        ->assertDontSee('Without both dates')
        ->assertDontSee('Rotate the signing keys')
        ->assertDontSee('Order the replacement disks');
});

test('a lead can try a new piece of work against some of their people, and nothing is saved', function () {
    $this->travelTo('2026-11-18 09:00');
    $lead = User::factory()->create();
    $team = Team::factory()->ledBy($lead)->create();
    $developer = User::factory()->inTeam($team)->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $sysadmin = User::factory()->inTeam($team)->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    WorkItem::factory()->onPlateOf($developer)->create(['title' => 'Replace the storage array', 'starts_on' => '2026-11-30', 'due_on' => '2026-12-18']);
    WorkItem::factory()->onHorizonOf($developer)->create(['title' => 'Move the apps into containers', 'starts_on' => '2027-01-11', 'due_on' => '2027-02-26']);

    $page = Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->assertDontSee('Draft for')
        ->set('draftTitle', 'New student portal')
        ->set('draftStartsOn', '2026-12-07')
        ->set('draftDueOn', '2026-12-31')
        ->set('draftPersonIds', [$developer->id])
        ->call('tryDraft')
        ->assertHasNoErrors();

    expect($page->html())
        ->toMatch('#Draft for Olivia McUser: New student portal, 7 Dec 2026 to 31 Dec 2026.*?grid-column: 4 / 8.*?The draft overlaps with Replace the storage array\.#s')
        ->not->toContain('Draft for Jenny MacAdmin')
        ->and($sysadmin->workItems)->toBeEmpty();

    $page->call('clearDraft')->assertDontSee('Draft for')->assertSet('draftTitle', '');

    expect(WorkItem::count())->toBe(2);
});

test('a draft needs a title, both dates in the right order, and people who report to the lead', function () {
    $lead = User::factory()->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $notTheirs = User::factory()->create();

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->call('tryDraft')
        ->assertHasErrors(['draftTitle', 'draftStartsOn', 'draftDueOn', 'draftPersonIds'])
        ->set('draftTitle', 'New student portal')
        ->set('draftStartsOn', '2026-12-31')
        ->set('draftDueOn', '2026-12-07')
        ->set('draftPersonIds', [$developer->id, $notTheirs->id])
        ->call('tryDraft')
        ->assertHasErrors(['draftDueOn', 'draftPersonIds.1'])
        ->assertSet('tryingDraft', false)
        ->assertDontSee('Draft for');
});

test('a lead can read the notes and history of work their people are on, and of nobody else\'s', function () {
    $lead = User::factory()->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $theirs = WorkItem::factory()->secret()->onPlateOf($developer)->create(['title' => 'Replace the storage array', 'notes' => 'A day of work, then two days of syncing.']);
    $theirs->addHistory($developer, 'created it');
    $notTheirs = WorkItem::factory()->onPlateOf(User::factory()->create())->create(['notes' => 'Nothing to do with this team.']);

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->assertDontSee('A day of work, then two days of syncing.')
        ->call('show', $theirs->id)
        ->assertDispatched('modal-show', name: 'work-item')
        ->assertSee('A day of work, then two days of syncing.')
        ->assertSee('Olivia McUser created it')
        ->call('show', $notTheirs->id)
        ->assertNotFound();

    Livewire::actingAs($developer)->test(MyTeam::class)->assertForbidden();
    $this->actingAs($developer)->get(route('home'))->assertOk()->assertDontSee(route('my-team'));
    expect(fn () => Livewire::actingAs($lead)->test(MyTeam::class)->set('showingId', $notTheirs->id))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('picking a start for a draft sets by-when to the day after, unless by-when already fits', function () {
    $lead = User::factory()->create();
    User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->set('draftStartsOn', '2026-12-31')
        ->assertSet('draftDueOn', '2027-01-01')
        ->set('draftDueOn', '2027-03-12')
        ->set('draftStartsOn', '2027-02-01')
        ->assertSet('draftDueOn', '2027-03-12')
        ->set('draftStartsOn', '2027-03-12')
        ->assertSet('draftDueOn', '2027-03-12')
        ->set('draftStartsOn', '2027-03-20')
        ->assertSet('draftDueOn', '2027-03-21')
        ->set('draftStartsOn', '')
        ->assertSet('draftDueOn', '2027-03-21');
});
