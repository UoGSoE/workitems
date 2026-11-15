<?php

use App\Livewire\MyPlate;
use App\Livewire\People;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

test('a developer can impersonate someone, sees what they see with a banner saying so, and can stop', function () {
    $developer = User::factory()->admin()->create();
    $person = User::factory()->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    WorkItem::factory()->onPlateOf($person)->create(['title' => 'Update the samba servers']);
    WorkItem::factory()->onPlateOf($developer)->create(['title' => 'Rotate the signing keys']);

    $this->actingAs($developer)->get(route('impersonate', $person->id))->assertRedirect('/');

    $this->assertAuthenticatedAs($person);
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Update the samba servers')
        ->assertDontSee('Rotate the signing keys')
        ->assertSee('You are seeing this as Olivia McUser')
        ->assertSee(route('impersonate.leave'));

    $this->get(route('impersonate.leave'))->assertRedirect('/');

    $this->assertAuthenticatedAs($developer);
    $this->get(route('home'))->assertSee('Rotate the signing keys')->assertDontSee('You are seeing this as');
});

test('starting and stopping impersonating are recorded in the developer\'s name', function () {
    $developer = User::factory()->admin()->create();
    $person = User::factory()->create(['forenames' => 'Olivia', 'surname' => 'McUser']);

    $this->actingAs($developer)->get(route('impersonate', $person->id));
    $this->get(route('impersonate.leave'));

    expect(ActivityLog::orderBy('id')->pluck('description')->all())->toBe(['started impersonating Olivia McUser', 'stopped impersonating Olivia McUser'])
        ->and(ActivityLog::pluck('user_id')->unique()->all())->toBe([$developer->id]);
});

test('while impersonating, a developer acts as that person and has only what that person has', function () {
    $developer = User::factory()->admin()->create();
    $person = User::factory()->create();

    $this->actingAs($developer)->get(route('impersonate', $person->id));

    Livewire::test(MyPlate::class)->set('whatsOn', 'Update the samba servers')->call('add')->assertHasNoErrors();
    Livewire::test(People::class)->assertForbidden();
    $this->get(route('people'))->assertForbidden();

    $workItem = WorkItem::sole();
    expect($workItem->owner->is($person))->toBeTrue()
        ->and($workItem->history()->sole()->user->is($person))->toBeTrue();
});

test('the people page offers impersonating to developers only, and never for themselves', function () {
    $developer = User::factory()->admin()->create();
    $managesPeople = User::factory()->peopleAdmin()->create();
    $person = User::factory()->create();

    Livewire::actingAs($developer)->test(People::class)
        ->assertSeeHtml(route('impersonate', $person->id))
        ->assertDontSeeHtml(route('impersonate', $developer->id));

    Livewire::actingAs($managesPeople)->test(People::class)
        ->assertDontSeeHtml(route('impersonate', $person->id));
});

test('only a developer can impersonate, and nobody can impersonate someone who has gone', function (string $who) {
    $person = User::factory()->create();
    $gone = User::factory()->create(['left_on' => '2026-01-01']);
    $actor = match ($who) {
        'someone who manages people' => User::factory()->peopleAdmin()->create(),
        'someone with no special role' => User::factory()->create(),
        'a developer' => User::factory()->admin()->create(),
    };

    $this->actingAs($actor)->get(route('impersonate', $gone->id));
    $this->assertAuthenticatedAs($actor);

    if ($who !== 'a developer') {
        $this->get(route('impersonate', $person->id))->assertForbidden();
        $this->assertAuthenticatedAs($actor);
    }
})->with(['someone who manages people', 'someone with no special role', 'a developer']);
