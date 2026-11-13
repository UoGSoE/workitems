<?php

use App\Enums\WorkItemStatus;
use App\Livewire\MyPlate;
use App\Models\User;
use App\Models\WorkItem;
use Livewire\Livewire;

test('a guest is sent to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('a person sees their own plate and horizon and nobody elses', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $onMyPlate = WorkItem::factory()->heldBy($user)->create();
    $onMyHorizon = WorkItem::factory()->upcoming()->heldBy($user)->create();
    $onColleaguesPlate = WorkItem::factory()->heldBy($colleague)->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSeeLivewire(MyPlate::class)
        ->assertSeeInOrder(['My plate', $onMyPlate->title, 'On the horizon', $onMyHorizon->title])
        ->assertDontSee($onColleaguesPlate->title);
});

test('typing into the whats on box puts a work item on my plate', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->set('whatsOn', 'Update the samba servers')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('whatsOn', '')
        ->assertSee('Update the samba servers');

    $workItem = WorkItem::sole();
    expect($workItem->title)->toBe('Update the samba servers')
        ->and($workItem->status)->toBe(WorkItemStatus::InProgress)
        ->and($workItem->people->modelKeys())->toBe([$user->id])
        ->and($colleague->workItems)->toBeEmpty();
});

test('an empty whats on box adds nothing', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->set('whatsOn', '')
        ->call('add')
        ->assertHasErrors(['whatsOn']);

    expect(WorkItem::count())->toBe(0);
});

test('a person can move one of their work items to another status', function () {
    $user = User::factory()->create();
    $finished = WorkItem::factory()->heldBy($user)->create();
    $stillOnMyPlate = WorkItem::factory()->heldBy($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('moveTo', $finished->id, WorkItemStatus::Done->value)
        ->assertSeeInOrder(['My plate', $stillOnMyPlate->title, 'Done', $finished->title]);

    expect($finished->fresh()->status)->toBe(WorkItemStatus::Done)
        ->and($stillOnMyPlate->fresh()->status)->toBe(WorkItemStatus::InProgress);
});

test('a person cannot move a work item that is not on their plate', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $onColleaguesPlate = WorkItem::factory()->heldBy($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('moveTo', $onColleaguesPlate->id, WorkItemStatus::Done->value)
        ->assertNotFound();

    expect($onColleaguesPlate->fresh()->status)->toBe(WorkItemStatus::InProgress);
});

test('a person can edit the details of one of their work items', function () {
    $user = User::factory()->create();
    $edited = WorkItem::factory()->heldBy($user)->create();
    $untouched = WorkItem::factory()->heldBy($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('edit', $edited->id)
        ->assertSet('title', $edited->title)
        ->set('title', 'Replace the storage server')
        ->set('notes', 'A day of work, then about two days syncing data')
        ->set('effortDays', '1')
        ->set('elapsedDays', '3')
        ->set('dueOn', '2026-12-18')
        ->call('save')
        ->assertHasNoErrors();

    $edited->refresh();
    expect($edited->title)->toBe('Replace the storage server')
        ->and($edited->notes)->toBe('A day of work, then about two days syncing data')
        ->and($edited->effort_days)->toBe(1)
        ->and($edited->elapsed_days)->toBe(3)
        ->and($edited->due_on->toDateString())->toBe('2026-12-18')
        ->and($edited->status)->toBe(WorkItemStatus::InProgress)
        ->and($untouched->fresh()->title)->toBe($untouched->title);
});

test('invalid details are not saved', function () {
    $user = User::factory()->create();
    $workItem = WorkItem::factory()->heldBy($user)->create(['effort_days' => 2]);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->set('title', '')
        ->set('effortDays', '-3')
        ->set('elapsedDays', 'lots')
        ->set('dueOn', 'whenever')
        ->call('save')
        ->assertHasErrors(['title', 'effortDays', 'elapsedDays', 'dueOn']);

    expect($workItem->fresh()->title)->toBe($workItem->title)
        ->and($workItem->fresh()->effort_days)->toBe(2);
});

test('clearing the optional details empties them', function () {
    $user = User::factory()->create();
    $workItem = WorkItem::factory()->heldBy($user)->create([
        'notes' => 'Waiting on the new rack',
        'effort_days' => 2,
        'elapsed_days' => 5,
        'due_on' => '2026-12-18',
    ]);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('edit', $workItem->id)
        ->set('notes', '')
        ->set('effortDays', '')
        ->set('elapsedDays', '')
        ->set('dueOn', '')
        ->call('save')
        ->assertHasNoErrors();

    $workItem->refresh();
    expect($workItem->notes)->toBeNull()
        ->and($workItem->effort_days)->toBeNull()
        ->and($workItem->elapsed_days)->toBeNull()
        ->and($workItem->due_on)->toBeNull();
});

test('a work item shows its rough size and by when', function () {
    $user = User::factory()->create();
    WorkItem::factory()->heldBy($user)->create([
        'effort_days' => 1,
        'elapsed_days' => 3,
        'due_on' => '2026-12-18',
    ]);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertSee('1 day of work, 3 days elapsed, by 18 Dec 2026');
});
