<?php

use App\Livewire\MyPlate;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('the command palette offers to add a new item and lists my work items, and nobody elses', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $onMyPlate = WorkItem::factory()->onPlateOf($user)->create();
    $onMyHorizon = WorkItem::factory()->onHorizonOf($user)->create();
    $onColleaguesPlate = WorkItem::factory()->onPlateOf($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertSeeInOrder(['Find a work item', 'Add new item'])
        ->assertSeeHtml('wire:click="pick('.$onMyPlate->id.')"')
        ->assertSeeHtml('wire:click="pick('.$onMyHorizon->id.')"')
        ->assertDontSeeHtml('wire:click="pick('.$onColleaguesPlate->id.')"');
});

test('picking a work item in the palette offers the things I can do to it', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $addedByMe = User::factory()->create();
    $picked = WorkItem::factory()
        ->onPlateOf($owner)
        ->onHorizonOf($user, addedBy: $owner)
        ->onHorizonOf($addedByMe, addedBy: $user)
        ->create(['title' => 'Replace the storage array']);
    WorkItem::factory()->onPlateOf($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertDontSee('Back to all work items')
        ->call('pick', $picked->id)
        ->assertSeeInOrder([
            'Replace the storage array: do what?',
            'Done',
            'Put on my plate',
            'Park',
            'Details',
            'Add someone',
            'Take this over',
            'Take me off this',
            'Take '.$addedByMe->full_name.' off this',
            'Back to all work items',
        ])
        ->assertDontSee('Add new item')
        ->call('unpick')
        ->assertSee('Add new item')
        ->assertDontSee('Back to all work items');
});

test('a work item I am not on cannot be picked in the palette', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $onColleaguesPlate = WorkItem::factory()->onPlateOf($colleague)->create(['title' => 'Replace the storage array']);

    Livewire::actingAs($user)->test(MyPlate::class)->call('pick', $onColleaguesPlate->id)->assertNotFound();

    $page = Livewire::actingAs($user)->test(MyPlate::class);
    expect(fn () => $page->set('pickedId', $onColleaguesPlate->id))->toThrow(CannotUpdateLockedPropertyException::class);
    $page->assertDontSee('Replace the storage array');
});
