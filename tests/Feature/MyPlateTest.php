<?php

use App\Enums\PlateSection;
use App\Enums\PlateStatus;
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
    $onMyPlate = WorkItem::factory()->onPlateOf($user)->create();
    $onMyHorizon = WorkItem::factory()->onHorizonOf($user)->create();
    $onColleaguesPlate = WorkItem::factory()->onPlateOf($colleague)->create();

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
        ->and($workItem->status)->toBe(WorkItemStatus::Open)
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
    $finished = WorkItem::factory()->onPlateOf($user)->create();
    $stillOnMyPlate = WorkItem::factory()->onPlateOf($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('moveTo', $finished->id, PlateSection::Done->value)
        ->assertSeeInOrder(['My plate', $stillOnMyPlate->title, 'Done', $finished->title]);

    expect($finished->fresh()->status)->toBe(WorkItemStatus::Done)
        ->and($stillOnMyPlate->fresh()->status)->toBe(WorkItemStatus::Open);
});

test('moving a shared work item to my horizon leaves it on my colleagues plate', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $shared = WorkItem::factory()->onPlateOf($user)->onPlateOf($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('moveTo', $shared->id, PlateSection::Horizon->value);

    expect($user->workItems()->sole()->pivot->status)->toBe(PlateStatus::Upcoming)
        ->and($colleague->workItems()->sole()->pivot->status)->toBe(PlateStatus::InProgress)
        ->and($shared->fresh()->status)->toBe(WorkItemStatus::Open);
});

test('parking a shared work item parks it for everyone on it', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $shared = WorkItem::factory()->onPlateOf($user)->onPlateOf($colleague)->create();
    $colleaguesOwn = WorkItem::factory()->onPlateOf($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('moveTo', $shared->id, PlateSection::Parked->value);

    Livewire::actingAs($colleague)
        ->test(MyPlate::class)
        ->assertSeeInOrder(['My plate', $colleaguesOwn->title, 'Parked', $shared->title]);
});

test('adding a colleague to a work item puts it on their horizon and says who added them and why', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $bystander = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('choosePersonFor', $workItem->id)
        ->set('personId', $colleague->id)
        ->set('reason', 'You know how the DNS challenges are set up')
        ->call('addPerson')
        ->assertHasNoErrors();

    $colleaguesRow = $colleague->workItems()->sole()->pivot;
    expect($colleaguesRow->status)->toBe(PlateStatus::Upcoming)
        ->and($colleaguesRow->addedBy->is($user))->toBeTrue()
        ->and($colleaguesRow->reason)->toBe('You know how the DNS challenges are set up')
        ->and($user->workItems()->sole()->pivot->status)->toBe(PlateStatus::InProgress)
        ->and($bystander->workItems)->toBeEmpty();
});

test('nobody, a stranger or someone already on the work item cannot be added', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($user)->onPlateOf($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('choosePersonFor', $workItem->id)
        ->set('personId', $colleague->id)
        ->call('addPerson')
        ->assertHasErrors(['personId'])
        ->set('personId', '')
        ->call('addPerson')
        ->assertHasErrors(['personId'])
        ->set('personId', 999999)
        ->call('addPerson')
        ->assertHasErrors(['personId']);

    expect($workItem->people()->count())->toBe(2);
});

test('a person cannot add someone to a work item they are not on', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $onColleaguesPlate = WorkItem::factory()->onPlateOf($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('choosePersonFor', $onColleaguesPlate->id)
        ->assertNotFound();

    expect($onColleaguesPlate->people()->count())->toBe(1);
});

test('a person cannot add themselves to a colleagues work item by tampering with the page', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $onColleaguesPlate = WorkItem::factory()->onPlateOf($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->set('addingToId', $onColleaguesPlate->id)
        ->set('personId', $user->id)
        ->call('addPerson')
        ->assertNotFound();

    expect($user->workItems)->toBeEmpty();
});

test('a shared work item shows who else is on it', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $anotherColleague = User::factory()->create();
    WorkItem::factory()->onPlateOf($user)->onPlateOf($colleague)->onHorizonOf($anotherColleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertSee('With '.$colleague->full_name.' and '.$anotherColleague->full_name)
        ->assertDontSee('With '.$user->full_name);
});

test('a work item a colleague added me to says who added me and why, with a way to ask them', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    WorkItem::factory()
        ->onPlateOf($colleague)
        ->onHorizonOf($user, addedBy: $colleague, reason: 'You know how the DNS challenges are set up')
        ->create(['title' => 'Move the cluster']);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertSee('Added by '.$colleague->full_name.': You know how the DNS challenges are set up')
        ->assertSeeHtml('href="mailto:'.$colleague->email.'?subject=Move%20the%20cluster%20-%20wut%3F"');
});

test('a work item I added myself does not say who added it', function () {
    $user = User::factory()->create();
    WorkItem::factory()->onPlateOf($user)->create();
    WorkItem::factory()->onHorizonOf($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertDontSee('Added by')
        ->assertDontSee('Wut?');
});

test('a person can take themselves off a work item a colleague added them to', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $notForMe = WorkItem::factory()->onPlateOf($colleague)->onHorizonOf($user, addedBy: $colleague)->create();
    $stillMine = WorkItem::factory()->onPlateOf($colleague)->onHorizonOf($user, addedBy: $colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('takeMeOff', $notForMe->id)
        ->assertDontSee($notForMe->title)
        ->assertSee($stillMine->title);

    expect($notForMe->fresh()->people->modelKeys())->toBe([$colleague->id])
        ->and($stillMine->fresh()->people()->count())->toBe(2);
});

test('a person cannot take themselves off a work item they added themselves', function () {
    $user = User::factory()->create();
    $myOwn = WorkItem::factory()->onPlateOf($user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertDontSee('Take me off this')
        ->call('takeMeOff', $myOwn->id)
        ->assertForbidden();

    expect($myOwn->fresh()->people->modelKeys())->toBe([$user->id]);
});

test('a person cannot move a work item that is not on their plate', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $onColleaguesPlate = WorkItem::factory()->onPlateOf($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('moveTo', $onColleaguesPlate->id, PlateSection::Done->value)
        ->assertNotFound();

    expect($onColleaguesPlate->fresh()->status)->toBe(WorkItemStatus::Open);
});

test('a person can edit the details of one of their work items', function () {
    $user = User::factory()->create();
    $edited = WorkItem::factory()->onPlateOf($user)->create();
    $untouched = WorkItem::factory()->onPlateOf($user)->create();

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
        ->and($edited->status)->toBe(WorkItemStatus::Open)
        ->and($untouched->fresh()->title)->toBe($untouched->title);
});

test('invalid details are not saved', function () {
    $user = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($user)->create(['effort_days' => 2]);

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
    $workItem = WorkItem::factory()->onPlateOf($user)->create([
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
    WorkItem::factory()->onPlateOf($user)->create([
        'effort_days' => 1,
        'elapsed_days' => 3,
        'due_on' => '2026-12-18',
    ]);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertSee('1 day of work, 3 days elapsed, by 18 Dec 2026');
});
