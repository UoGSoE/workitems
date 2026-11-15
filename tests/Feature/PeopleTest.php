<?php

use App\Livewire\People;
use App\Models\Team;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('the people page is for admins only', function () {
    $this->get(route('people'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('people'))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get(route('people'))->assertOk()->assertSeeLivewire(People::class);
});

test('someone who manages people can use the people page without being a developer', function () {
    $managesPeople = User::factory()->peopleAdmin()->create();

    $this->actingAs($managesPeople)->get(route('people'))->assertOk();
    $this->actingAs($managesPeople)->get(route('home'))->assertSee(route('people'));

    Livewire::actingAs($managesPeople)->test(People::class)->call('newTeam')->set('teamName', 'Developers')->call('saveTeam')->assertHasNoErrors();

    expect(Team::sole()->name)->toBe('Developers')
        ->and($managesPeople->is_admin)->toBeFalse();
});

test('the sidebar offers the people page to admins only', function () {
    $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertDontSee(route('people'));

    $this->actingAs(User::factory()->admin()->create())->get(route('home'))->assertOk()->assertSee(route('people'));
});

test('someone who stops being an admin while on the people page is refused on their next action', function () {
    $admin = User::factory()->admin()->create();
    $page = Livewire::actingAs($admin)->test(People::class)->set('teamName', 'Developers');

    $admin->update(['is_admin' => false]);

    $page->call('saveTeam')->assertForbidden();
    expect(Team::count())->toBe(0);
});

test('an admin can add a person, with or without a team and a job title', function () {
    $admin = User::factory()->admin()->create();
    $team = Team::factory()->create();

    Livewire::actingAs($admin)
        ->test(People::class)
        ->set('newUsername', 'jmu1x')
        ->set('newEmail', 'jenny.mcuser@example.test')
        ->set('newSurname', 'McUser')
        ->set('newForenames', 'Jenny')
        ->set('newJobTitle', 'Systems Administrator')
        ->set('newTeamId', $team->id)
        ->call('addPerson')
        ->assertHasNoErrors()
        ->set('newUsername', 'jma2y')
        ->set('newEmail', 'jenny.macadmin@example.test')
        ->set('newSurname', 'MacAdmin')
        ->set('newForenames', 'Jenny')
        ->set('newManagesPeople', true)
        ->call('addPerson')
        ->assertHasNoErrors();

    $inATeam = User::firstWhere('email', 'jenny.mcuser@example.test');
    $inNoTeam = User::firstWhere('email', 'jenny.macadmin@example.test');
    expect(User::count())->toBe(3)
        ->and($inATeam->only('username', 'surname', 'forenames', 'job_title', 'is_staff', 'is_admin', 'is_people_admin'))->toBe([
            'username' => 'jmu1x', 'surname' => 'McUser', 'forenames' => 'Jenny', 'job_title' => 'Systems Administrator', 'is_staff' => true, 'is_admin' => false, 'is_people_admin' => false,
        ])
        ->and($inATeam->team->is($team))->toBeTrue()
        ->and($inNoTeam->only('job_title', 'team_id', 'is_staff', 'is_admin', 'is_people_admin'))->toBe([
            'job_title' => null, 'team_id' => null, 'is_staff' => true, 'is_admin' => false, 'is_people_admin' => true,
        ]);
});

test('a person is not added from details that are wrong, or by someone who is not an admin', function () {
    $admin = User::factory()->admin()->create(['username' => 'taken1x', 'email' => 'taken@example.test']);
    $notAnAdmin = User::factory()->create();

    Livewire::actingAs($admin)
        ->test(People::class)
        ->set('newUsername', 'taken1x')
        ->set('newEmail', 'taken@example.test')
        ->set('newSurname', '')
        ->set('newForenames', '')
        ->set('newJobTitle', str_repeat('a', 256))
        ->set('newTeamId', 999)
        ->call('addPerson')
        ->assertHasErrors(['newUsername', 'newEmail', 'newSurname', 'newForenames', 'newJobTitle', 'newTeamId']);

    Livewire::actingAs($notAnAdmin)->test(People::class)->assertForbidden();

    expect(User::count())->toBe(2);
});

test('someone who manages people can change the team and job title of one person, and whether they manage people too', function () {
    $managesPeople = User::factory()->peopleAdmin()->create();
    $team = Team::factory()->create();
    $person = User::factory()->create(['job_title' => 'Developer']);
    $someoneElse = User::factory()->create(['job_title' => 'Developer']);

    Livewire::actingAs($managesPeople)
        ->test(People::class)
        ->call('editPerson', $person->id)
        ->assertSet('jobTitle', 'Developer')
        ->assertSet('teamId', '', strict: true)
        ->assertSet('managesPeople', false)
        ->assertSeeHtml('wire:model="managesPeople"')
        ->assertDispatched('modal-show', name: 'edit-person')
        ->set('jobTitle', 'Senior Developer')
        ->set('teamId', $team->id)
        ->set('managesPeople', true)
        ->call('savePerson')
        ->assertHasNoErrors();

    expect($person->fresh()->only('job_title', 'team_id', 'is_people_admin', 'is_admin'))->toBe(['job_title' => 'Senior Developer', 'team_id' => $team->id, 'is_people_admin' => true, 'is_admin' => false])
        ->and($someoneElse->fresh()->only('job_title', 'team_id', 'is_people_admin', 'is_admin'))->toBe(['job_title' => 'Developer', 'team_id' => null, 'is_people_admin' => false, 'is_admin' => false]);
});

test('nobody can change whether they manage people themselves', function () {
    $managesPeople = User::factory()->peopleAdmin()->create(['job_title' => 'Office Manager']);

    Livewire::actingAs($managesPeople)
        ->test(People::class)
        ->call('editPerson', $managesPeople->id)
        ->assertDontSeeHtml('wire:model="managesPeople"')
        ->set('jobTitle', 'Operations Manager')
        ->set('managesPeople', false)
        ->call('savePerson')
        ->assertHasNoErrors();

    expect($managesPeople->fresh()->only('job_title', 'is_people_admin'))->toBe(['job_title' => 'Operations Manager', 'is_people_admin' => true]);
});

test('an admin can add a team, then rename it and change who leads it', function () {
    $admin = User::factory()->admin()->create();
    $firstLead = User::factory()->create();
    $secondLead = User::factory()->create();
    $untouched = Team::factory()->ledBy($firstLead)->create(['name' => 'Infrastructure']);

    $page = Livewire::actingAs($admin)
        ->test(People::class)
        ->call('newTeam')
        ->assertDispatched('modal-show', name: 'team')
        ->set('teamName', 'Developers')
        ->set('leadId', $firstLead->id)
        ->call('saveTeam')
        ->assertHasNoErrors();

    $team = Team::firstWhere('name', 'Developers');
    expect($team->lead->is($firstLead))->toBeTrue();

    $page->call('editTeam', $team->id)
        ->assertSet('teamName', 'Developers')
        ->assertSet('leadId', $firstLead->id)
        ->set('teamName', 'Development')
        ->set('leadId', $secondLead->id)
        ->call('saveTeam')
        ->assertHasNoErrors();

    expect(Team::count())->toBe(2)
        ->and($team->fresh()->name)->toBe('Development')
        ->and($team->fresh()->lead->is($secondLead))->toBeTrue()
        ->and($untouched->fresh()->name)->toBe('Infrastructure')
        ->and($untouched->fresh()->lead->is($firstLead))->toBeTrue();
});

test('a team is not saved without a name or with a lead who does not exist', function () {
    Livewire::actingAs(User::factory()->admin()->create())
        ->test(People::class)
        ->call('newTeam')
        ->set('teamName', '')
        ->set('leadId', 999)
        ->call('saveTeam')
        ->assertHasErrors(['teamName', 'leadId']);

    expect(Team::count())->toBe(0);
});

test('the page lists everyone with their job title, team and who they report to, and every team with its lead', function () {
    $admin = User::factory()->admin()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin', 'job_title' => 'Infrastructure Lead']);
    $team = Team::factory()->ledBy($admin)->create(['name' => 'Infrastructure']);
    User::factory()->inTeam($team)->create(['forenames' => 'Jenny', 'surname' => 'McUser', 'job_title' => null]);

    Livewire::actingAs($admin)
        ->test(People::class)
        ->assertSeeInOrder([
            'Jenny MacAdmin (Infrastructure Lead)', 'Jenny McUser', 'Infrastructure', 'Jenny MacAdmin',
            'Teams', 'Infrastructure', 'Jenny MacAdmin', '1 member',
        ]);
});

test('which person or team is being edited cannot be changed from the browser', function () {
    $page = Livewire::actingAs(User::factory()->admin()->create())->test(People::class);

    expect(fn () => $page->set('editingPersonId', 1))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $page->set('editingTeamId', 1))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('the lead of a team cannot also be a member of it', function () {
    $admin = User::factory()->admin()->create();
    $lead = User::factory()->create();
    $team = Team::factory()->ledBy($lead)->create();
    $member = User::factory()->inTeam($team)->create();

    Livewire::actingAs($admin)
        ->test(People::class)
        ->call('editPerson', $lead->id)
        ->set('teamId', $team->id)
        ->call('savePerson')
        ->assertHasErrors('teamId')
        ->call('editTeam', $team->id)
        ->set('leadId', $member->id)
        ->call('saveTeam')
        ->assertHasErrors('leadId');

    expect($lead->fresh()->team_id)->toBeNull()
        ->and($team->fresh()->lead->is($lead))->toBeTrue();
});
