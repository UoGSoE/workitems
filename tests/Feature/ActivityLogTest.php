<?php

use App\Livewire\People;
use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(fn () => Mail::fake());

test('changing who leads a team is recorded, with who did it', function () {
    $this->travelTo('2026-11-20 10:00');
    $managesPeople = User::factory()->peopleAdmin()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    $newLead = User::factory()->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $team = Team::factory()->ledBy(User::factory()->create())->create(['name' => 'Developers']);

    Livewire::actingAs($managesPeople)
        ->test(People::class)
        ->call('editTeam', $team->id)
        ->set('leadId', $newLead->id)
        ->call('saveTeam')
        ->assertHasNoErrors();

    $line = ActivityLog::sole();
    expect($line->user->is($managesPeople))->toBeTrue()
        ->and($line->description)->toBe('made Olivia McUser the lead of Developers')
        ->and($line->sentence())->toBe('Jenny MacAdmin made Olivia McUser the lead of Developers on 20 Nov 2026');
});

test('each change to a person is recorded once, and saving without a change records nothing', function () {
    $managesPeople = User::factory()->peopleAdmin()->create();
    $person = User::factory()->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $team = Team::factory()->create(['name' => 'Developers']);

    Livewire::actingAs($managesPeople)
        ->test(People::class)
        ->call('editPerson', $person->id)
        ->set('teamId', $team->id)
        ->set('jobTitle', 'Developer')
        ->set('managesPeople', true)
        ->call('savePerson')
        ->call('editPerson', $person->id)
        ->call('savePerson')
        ->call('editPerson', $person->id)
        ->set('teamId', '')
        ->set('jobTitle', '')
        ->set('managesPeople', false)
        ->call('savePerson')
        ->assertHasNoErrors();

    expect(ActivityLog::orderBy('id')->pluck('description')->all())->toBe([
        'moved Olivia McUser into Developers',
        'changed the job title of Olivia McUser to Developer',
        'let Olivia McUser manage people',
        'took Olivia McUser out of Developers',
        'removed the job title of Olivia McUser',
        'stopped Olivia McUser managing people',
    ])->and(ActivityLog::pluck('user_id')->unique()->all())->toBe([$managesPeople->id]);
});

test('adding a person, and adding, renaming and unleading a team are recorded, and a refused change is not', function () {
    $managesPeople = User::factory()->peopleAdmin()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);

    $page = Livewire::actingAs($managesPeople)
        ->test(People::class)
        ->set('newUsername', 'omu1x')
        ->set('newEmail', 'olivia.mcuser@example.test')
        ->set('newSurname', 'McUser')
        ->set('newForenames', 'Olivia')
        ->call('addPerson')
        ->call('newTeam')
        ->set('teamName', '')
        ->call('saveTeam')
        ->assertHasErrors('teamName')
        ->set('teamName', 'Developers')
        ->set('leadId', $managesPeople->id)
        ->call('saveTeam')
        ->assertHasNoErrors();

    $page->call('editTeam', Team::sole()->id)
        ->set('teamName', 'Development')
        ->set('leadId', '')
        ->call('saveTeam')
        ->assertHasNoErrors();

    expect(ActivityLog::orderBy('id')->pluck('description')->all())->toBe([
        'added Olivia McUser',
        'added the team Developers',
        'made Jenny MacAdmin the lead of Developers',
        'renamed the team Developers to Development',
        'left Development without a lead',
    ]);
});

test('marking someone as gone is recorded, with who got their work and their team, and the page lists the record newest first', function () {
    $this->travelTo('2026-11-20 10:00');
    $managesPeople = User::factory()->peopleAdmin()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    $leaver = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McLeaver']);
    $takesOver = User::factory()->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    Team::factory()->ledBy($leaver)->create(['name' => 'Developers']);
    WorkItem::factory()->onPlateOf($leaver)->create(['title' => 'Rotate the signing keys']);
    ActivityLog::factory()->create(['user_id' => $managesPeople->id, 'description' => 'added Jenny McLeaver', 'created_at' => '2026-11-01 09:00']);

    Livewire::actingAs($managesPeople)
        ->test(People::class)
        ->call('chooseWhoIsGoing', $leaver->id)
        ->set('giveWorkToId', $takesOver->id)
        ->set('newLeadId', $takesOver->id)
        ->call('markAsGone')
        ->assertHasNoErrors()
        ->assertSeeInOrder([
            'Recent changes',
            'Jenny MacAdmin marked Jenny McLeaver as gone, gave their work to Olivia McUser and made Olivia McUser the lead of Developers on 20 Nov 2026',
            'Jenny MacAdmin added Jenny McLeaver on 1 Nov 2026',
        ])
        ->assertDontSee('Rotate the signing keys');
});
