<?php

use App\Enums\WorkItemVisibility;
use App\Livewire\MyPlate;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

test('the owner of a work item that is looking for a hand can say why someone might want to help, and nobody else on it can', function () {
    $owner = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->lookingForAHand()->onPlateOf($owner)->onHorizonOf($colleague, addedBy: $owner)->create();
    $untouched = WorkItem::factory()->lookingForAHand()->onPlateOf($owner)->create();

    Livewire::actingAs($owner)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertSet('blurb', '', strict: true)
        ->set('blurb', 'A good one for **learning Docker**.')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($colleague)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertSet('blurb', 'A good one for **learning Docker**.')
        ->set('title', 'Move the apps into containers')
        ->call('save')
        ->assertHasNoErrors()
        ->call('edit', $workItem->id)
        ->set('blurb', 'Ignore this, it is dull.')
        ->call('save')
        ->assertForbidden();

    expect($workItem->fresh()->blurb)->toBe('A good one for **learning Docker**.')
        ->and($workItem->fresh()->title)->toBe('Move the apps into containers')
        ->and($untouched->fresh()->blurb)->toBeNull();
});

test('the owner is asked for a pitch only once they choose looking for a hand', function () {
    $owner = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->onHorizonOf($colleague, addedBy: $owner)->create();

    Livewire::actingAs($owner)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertDontSee('Why might someone want to help?')
        ->set('visibility', WorkItemVisibility::LookingForAHand->value)
        ->assertSee('Why might someone want to help?');

    $workItem->update(['visibility' => WorkItemVisibility::LookingForAHand]);

    Livewire::actingAs($colleague)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertDontSee('Why might someone want to help?');
});

test('the looking for a hand page shows work from any team that wants help, with its pitch, and nothing more private', function () {
    $this->get(route('looking-for-a-hand'))->assertRedirect(route('login'));

    $user = User::factory()->create();
    $theirLead = User::factory()->create();
    $inAnotherTeam = User::factory()->inTeam(Team::factory()->ledBy($theirLead)->create())->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin', 'job_title' => 'Infrastructure Lead']);
    WorkItem::factory()->lookingForAHand()->onPlateOf($inAnotherTeam)->create(['title' => 'Move the apps into containers', 'blurb' => 'A good one for **learning Docker**.', 'effort_days' => 10]);
    WorkItem::factory()->lookingForAHand()->done()->onPlateOf($inAnotherTeam)->create(['title' => 'Renew the wildcard certificate']);
    WorkItem::factory()->visibleToTeam()->onPlateOf($inAnotherTeam)->create(['title' => 'Tidy up the old wiki pages']);
    WorkItem::factory()->onPlateOf($inAnotherTeam)->create(['title' => 'Replace the storage array']);
    WorkItem::factory()->secret()->onPlateOf($inAnotherTeam)->create(['title' => 'Rotate the signing keys']);

    $this->actingAs($user)->get(route('looking-for-a-hand'))
        ->assertOk()
        ->assertSeeInOrder(['Move the apps into containers', 'Jenny MacAdmin (Infrastructure Lead)', '10 days of work'])
        ->assertSee('A good one for <strong>learning Docker</strong>.', escape: false)
        ->assertDontSee('Renew the wildcard certificate')
        ->assertDontSee('Tidy up the old wiki pages')
        ->assertDontSee('Replace the storage array')
        ->assertDontSee('Rotate the signing keys');

    $this->actingAs($theirLead)->get(route('looking-for-a-hand'))
        ->assertOk()
        ->assertSee('Move the apps into containers')
        ->assertDontSee('Replace the storage array')
        ->assertDontSee('Rotate the signing keys');
});

test('the page also lists what my team has chosen to show me, and not what another team shows its own people', function () {
    $lead = User::factory()->create();
    $team = Team::factory()->ledBy($lead)->create();
    $user = User::factory()->inTeam($team)->create();
    $teamMate = User::factory()->inTeam($team)->create(['forenames' => 'Jenny', 'surname' => 'McUser']);
    $inAnotherTeam = User::factory()->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create();
    WorkItem::factory()->visibleToTeam()->onPlateOf($teamMate)->create(['title' => 'Update the samba servers']);
    WorkItem::factory()->visibleToTeam()->onPlateOf($teamMate)->onHorizonOf($user, addedBy: $teamMate)->create(['title' => 'Already on my own plate']);
    WorkItem::factory()->onPlateOf($teamMate)->create(['title' => 'Replace the storage array']);
    WorkItem::factory()->visibleToTeam()->onPlateOf($inAnotherTeam)->create(['title' => 'Tidy up the old wiki pages']);

    $this->actingAs($user)->get(route('looking-for-a-hand'))
        ->assertOk()
        ->assertSeeInOrder(["My team's work", 'Update the samba servers', 'Jenny McUser'], escape: false)
        ->assertDontSee('Already on my own plate')
        ->assertDontSee('Replace the storage array')
        ->assertDontSee('Tidy up the old wiki pages');
});

test('the sidebar links to the looking for a hand page', function () {
    $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()->assertSee(route('looking-for-a-hand'));
});

test('a pitch cannot carry script, a link that runs script, or a picture loaded from somewhere else', function () {
    $owner = User::factory()->create();
    WorkItem::factory()->lookingForAHand()->onPlateOf($owner)->create([
        'blurb' => 'Hello <script>alert("one")</script> and [click me](javascript:alert("two")) and ![a picture](https://example.test/pixel.png) and [the docs](https://example.test/docs).',
    ]);

    $this->actingAs(User::factory()->create())->get(route('looking-for-a-hand'))
        ->assertOk()
        ->assertSee('<a href="https://example.test/docs">the docs</a>', escape: false)
        ->assertDontSee('<script>alert("one")', escape: false)
        ->assertDontSee('javascript:alert', escape: false)
        ->assertDontSee('pixel.png', escape: false);
});
