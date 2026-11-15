<?php

use App\Livewire\Settings;
use App\Models\User;
use Livewire\Livewire;

test('the settings page is for anyone signed in', function () {
    $this->get(route('settings'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('settings'))->assertOk()->assertSeeLivewire(Settings::class);
});

test('the sidebar links to the settings page', function () {
    $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertSee(route('settings'));
});

test('someone can change their own job title and whether their team sees their new work items', function () {
    $user = User::factory()->create(['job_title' => 'Developer']);
    $someoneElse = User::factory()->create(['job_title' => 'Developer']);

    Livewire::actingAs($user)
        ->test(Settings::class)
        ->assertSet('jobTitle', 'Developer')
        ->assertSet('showsWorkToTeam', false)
        ->set('jobTitle', 'Senior Developer')
        ->set('showsWorkToTeam', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->only('job_title', 'shows_work_to_team'))->toBe(['job_title' => 'Senior Developer', 'shows_work_to_team' => true])
        ->and($someoneElse->fresh()->only('job_title', 'shows_work_to_team'))->toBe(['job_title' => 'Developer', 'shows_work_to_team' => false]);
});

test('a job title that is too long changes nothing', function () {
    $user = User::factory()->create(['job_title' => 'Developer']);

    Livewire::actingAs($user)
        ->test(Settings::class)
        ->set('jobTitle', str_repeat('a', 256))
        ->set('showsWorkToTeam', true)
        ->call('save')
        ->assertHasErrors('jobTitle');

    expect($user->fresh()->only('job_title', 'shows_work_to_team'))->toBe(['job_title' => 'Developer', 'shows_work_to_team' => false]);
});
