<?php

use App\Enums\PlateSection;
use App\Livewire\MyPlate;
use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemHistory;
use Livewire\Livewire;

test('adding a work item records who created it', function () {
    $user = User::factory()->create();
    $someoneElsesWorkItem = WorkItem::factory()->onPlateOf(User::factory()->create())->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->set('whatsOn', 'Update the samba servers')
        ->call('add');

    $line = WorkItem::firstWhere('title', 'Update the samba servers')->history()->sole();
    expect($line->user->is($user))->toBeTrue()
        ->and($line->description)->toBe('created it')
        ->and($someoneElsesWorkItem->history)->toBeEmpty();
});

test('adding a colleague records who added whom', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($user)->create();
    $untouched = WorkItem::factory()->onPlateOf($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('choosePersonFor', $workItem->id)
        ->set('personId', $colleague->id)
        ->call('addPerson');

    $line = $workItem->history()->sole();
    expect($line->user->is($user))->toBeTrue()
        ->and($line->description)->toBe('added '.$colleague->full_name)
        ->and($untouched->history)->toBeEmpty();
});

test('finishing, parking and reopening are recorded, but a move between my plate and horizon is not', function () {
    $user = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($user)->create();
    $onlyShuffled = WorkItem::factory()->onPlateOf($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('moveTo', $workItem->id, PlateSection::Done->value)
        ->call('moveTo', $workItem->id, PlateSection::MyPlate->value)
        ->call('moveTo', $workItem->id, PlateSection::Parked->value)
        ->call('moveTo', $onlyShuffled->id, PlateSection::Horizon->value);

    expect($workItem->history->pluck('description')->all())->toBe(['marked it done', 'reopened it', 'parked it'])
        ->and($workItem->history->load('user')->every(fn ($line) => $line->user->is($user)))->toBeTrue()
        ->and($onlyShuffled->history)->toBeEmpty();
});

test('changing the details is recorded, but saving them unchanged is not', function () {
    $user = User::factory()->create();
    $changed = WorkItem::factory()->onPlateOf($user)->create();
    $savedAsItWas = WorkItem::factory()->onPlateOf($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('edit', $changed->id)
        ->set('title', 'Replace the storage server')
        ->call('save')
        ->call('edit', $savedAsItWas->id)
        ->call('save');

    $line = $changed->history()->sole();
    expect($line->user->is($user))->toBeTrue()
        ->and($line->description)->toBe('changed the details')
        ->and($savedAsItWas->history)->toBeEmpty();
});

test('the details of a work item show its history and nobody elses', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($user)->create();
    $anotherWorkItem = WorkItem::factory()->onPlateOf($user)->create();
    WorkItemHistory::factory()->for($workItem)->for($colleague)->create(['description' => 'created it', 'created_at' => '2026-11-02']);
    WorkItemHistory::factory()->for($workItem)->for($user)->create(['description' => 'parked it', 'created_at' => '2026-11-09']);
    WorkItemHistory::factory()->for($anotherWorkItem)->for($user)->create(['description' => 'marked it done']);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertDontSee('created it')
        ->call('edit', $workItem->id)
        ->assertSeeInOrder([
            $colleague->full_name.' created it on 2 Nov 2026',
            $user->full_name.' parked it on 9 Nov 2026',
        ])
        ->assertDontSee('marked it done');
});
