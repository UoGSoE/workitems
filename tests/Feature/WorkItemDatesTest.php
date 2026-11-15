<?php

use App\Livewire\MyPlate;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

test('anyone on a work item can say roughly when it starts, and clear it again', function () {
    $owner = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->onHorizonOf($colleague, addedBy: $owner)->create(['effort_days' => 5, 'due_on' => '2027-03-26']);
    $untouched = WorkItem::factory()->onPlateOf($owner)->create();

    $page = Livewire::actingAs($colleague)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertSet('startsOn', null)
        ->set('startsOn', '2027-03-01')
        ->call('save')
        ->assertHasNoErrors();

    expect($workItem->fresh()->starts_on->toDateString())->toBe('2027-03-01')
        ->and($workItem->fresh()->summary())->toBe('5 days of work, from 1 Mar 2027, by 26 Mar 2027')
        ->and($untouched->fresh()->starts_on)->toBeNull();

    $page->call('edit', $workItem->id)->assertSet('startsOn', '2027-03-01')->set('startsOn', '')->call('save')->assertHasNoErrors();

    expect($workItem->fresh()->starts_on)->toBeNull()
        ->and($workItem->fresh()->summary())->toBe('5 days of work, by 26 Mar 2027');
});

test('a work item cannot be due before it starts', function () {
    $owner = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->create(['title' => 'Replace the storage array', 'due_on' => '2027-03-26']);

    Livewire::actingAs($owner)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->set('title', 'Something else')
        ->set('startsOn', '2027-04-01')
        ->call('save')
        ->assertHasErrors('dueOn');

    expect($workItem->fresh()->starts_on)->toBeNull()
        ->and($workItem->fresh()->title)->toBe('Replace the storage array')
        ->and($workItem->history)->toBeEmpty();
});

test('a work item can start and be due on the same day', function () {
    $owner = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->create(['due_on' => '2027-03-26']);

    Livewire::actingAs($owner)->test(MyPlate::class)->call('edit', $workItem->id)->set('startsOn', '2027-03-26')->call('save')->assertHasNoErrors();

    expect($workItem->fresh()->starts_on->toDateString())->toBe('2027-03-26');
});

test('the by-when calendar opens at the month the work starts', function () {
    $owner = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->create();

    Livewire::actingAs($owner)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->assertDontSeeHtml('open-to=')
        ->set('startsOn', '2027-02-01')
        ->assertSeeHtml('open-to="2027-02-01"')
        ->assertSet('dueOn', null);
});
