<?php

use App\Enums\WorkItemVisibility;
use App\Livewire\MyPlate;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

test('a new work item is seen by just the people on it, unless its owner lets their team see their work', function () {
    $keepsWorkPrivate = User::factory()->create();
    $showsWorkToTeam = User::factory()->create(['shows_work_to_team' => true]);

    Livewire::actingAs($keepsWorkPrivate)->test(MyPlate::class)->set('whatsOn', 'Update the samba servers')->call('add');
    Livewire::actingAs($showsWorkToTeam)->test(MyPlate::class)->set('whatsOn', 'Replace the storage array')->call('add');

    expect(WorkItem::firstWhere('title', 'Update the samba servers')->visibility)->toBe(WorkItemVisibility::PeopleOnIt)
        ->and(WorkItem::firstWhere('title', 'Replace the storage array')->visibility)->toBe(WorkItemVisibility::Team);
});

test('the owner of a work item can change who sees it, and the history says so', function () {
    $owner = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->create();
    $untouched = WorkItem::factory()->onPlateOf($owner)->create();

    Livewire::actingAs($owner)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertSet('visibility', WorkItemVisibility::PeopleOnIt->value)
        ->set('visibility', WorkItemVisibility::LookingForAHand->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($workItem->fresh()->visibility)->toBe(WorkItemVisibility::LookingForAHand)
        ->and($workItem->history()->sole()->description)->toBe('made it visible to everyone, in the classifieds')
        ->and($workItem->history()->sole()->user->is($owner))->toBeTrue()
        ->and($untouched->fresh()->visibility)->toBe(WorkItemVisibility::PeopleOnIt)
        ->and($untouched->history)->toBeEmpty();
});

test('the owner can make a work item secret, and a kind of visibility that does not exist is refused', function () {
    $owner = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->create();

    Livewire::actingAs($owner)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->set('visibility', 'everyone')
        ->call('save')
        ->assertHasErrors('visibility')
        ->set('visibility', WorkItemVisibility::Secret->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($workItem->fresh()->visibility)->toBe(WorkItemVisibility::Secret)
        ->and($workItem->history()->sole()->description)->toBe('made it secret');
});

test('saving a work item without changing who sees it leaves the history alone', function () {
    $owner = User::factory()->create();
    $workItem = WorkItem::factory()->visibleToTeam()->onPlateOf($owner)->create();

    Livewire::actingAs($owner)->test(MyPlate::class)->call('edit', $workItem->id)->call('save')->assertHasNoErrors();

    expect($workItem->fresh()->visibility)->toBe(WorkItemVisibility::Team)
        ->and($workItem->history)->toBeEmpty();
});

test('someone on a work item who does not own it cannot change who sees it', function () {
    $owner = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->onHorizonOf($colleague, addedBy: $owner)->create(['title' => 'Rotate the signing keys']);

    Livewire::actingAs($colleague)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->set('title', 'Something else')
        ->set('visibility', WorkItemVisibility::LookingForAHand->value)
        ->call('save')
        ->assertForbidden();

    expect($workItem->fresh()->visibility)->toBe(WorkItemVisibility::PeopleOnIt)
        ->and($workItem->fresh()->title)->toBe('Rotate the signing keys')
        ->and($workItem->history)->toBeEmpty();
});

test('only the owner is offered the choice of who sees a work item, and not once it is secret', function () {
    $owner = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->visibleToTeam()->onPlateOf($owner)->onHorizonOf($colleague, addedBy: $owner)->create();
    $secret = WorkItem::factory()->secret()->onPlateOf($owner)->create();

    Livewire::actingAs($owner)->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertSeeHtml('wire:model.live="visibility"')
        ->assertSee('In the classifieds (everyone can see it)')
        ->assertDontSee('Secret cannot be undone')
        ->set('visibility', WorkItemVisibility::Secret->value)
        ->assertSee('Secret cannot be undone')
        ->assertSee('Save, and keep it secret for good')
        ->call('edit', $secret->id)
        ->assertDontSeeHtml('wire:model.live="visibility"')
        ->assertSee('Only the people on it, and who they report to, can ever see it.');

    Livewire::actingAs($colleague)->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertDontSeeHtml('wire:model.live="visibility"')
        ->assertSee('My team can see it')
        ->assertDontSee('In the classifieds (everyone can see it)');
});

test('a secret work item stays secret, even for its owner and for someone who takes it over', function () {
    $owner = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->secret()->onPlateOf($owner)->onHorizonOf($colleague, addedBy: $owner)->create();

    Livewire::actingAs($owner)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->set('visibility', WorkItemVisibility::Team->value)
        ->call('save')
        ->assertForbidden();

    Livewire::actingAs($colleague)
        ->test(MyPlate::class)
        ->call('takeOver', $workItem->id)
        ->call('edit', $workItem->id)
        ->set('visibility', WorkItemVisibility::LookingForAHand->value)
        ->call('save')
        ->assertForbidden();

    expect($workItem->fresh()->visibility)->toBe(WorkItemVisibility::Secret)
        ->and($workItem->fresh()->owner->is($colleague))->toBeTrue();
});
