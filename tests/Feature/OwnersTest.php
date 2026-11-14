<?php

use App\Livewire\MyPlate;
use App\Mail\TakenOffWorkItemMail;
use App\Mail\TakenOverWorkItemMail;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('whoever adds a work item owns it', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $colleaguesWorkItem = WorkItem::factory()->onPlateOf($colleague)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->set('whatsOn', 'Update the samba servers')
        ->call('add');

    expect(WorkItem::firstWhere('title', 'Update the samba servers')->owner->is($user))->toBeTrue()
        ->and($colleaguesWorkItem->fresh()->owner->is($colleague))->toBeTrue();
});

test('someone on a work item can take it over, and the previous owner is told', function () {
    Mail::fake();
    $user = User::factory()->create();
    $previousOwner = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($previousOwner)->onHorizonOf($user, addedBy: $previousOwner)->create(['title' => 'Replace the storage array']);
    $untouched = WorkItem::factory()->onPlateOf($previousOwner)->onHorizonOf($user, addedBy: $previousOwner)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('takeOver', $workItem->id);

    $workItem->refresh();
    expect($workItem->owner->is($user))->toBeTrue()
        ->and($workItem->people()->count())->toBe(2)
        ->and($workItem->history()->sole()->description)->toBe('took it over from '.$previousOwner->full_name)
        ->and($workItem->history()->sole()->user->is($user))->toBeTrue()
        ->and($untouched->fresh()->owner->is($previousOwner))->toBeTrue()
        ->and($untouched->history)->toBeEmpty();
    Mail::assertQueuedCount(1);
    Mail::assertQueued(TakenOverWorkItemMail::class, function (TakenOverWorkItemMail $mail) use ($user, $previousOwner) {
        return $mail->hasTo($previousOwner->email)
            && $mail->hasSubject('['.config('app.name').'] '.$user->full_name.' took over a work item')
            && $mail->assertSeeInHtml('Replace the storage array')
            && $mail->assertSeeInHtml(route('home'));
    });
});

test('a work item cannot be taken over by its owner, or by someone who is not on it', function () {
    Mail::fake();
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $alreadyMine = WorkItem::factory()->onPlateOf($user)->create();
    $nothingToDoWithMe = WorkItem::factory()->onPlateOf($colleague)->create();

    Livewire::actingAs($user)->test(MyPlate::class)->call('takeOver', $alreadyMine->id)->assertForbidden();
    Livewire::actingAs($user)->test(MyPlate::class)->call('takeOver', $nothingToDoWithMe->id)->assertNotFound();

    expect($alreadyMine->fresh()->owner->is($user))->toBeTrue()
        ->and($nothingToDoWithMe->fresh()->owner->is($colleague))->toBeTrue()
        ->and($alreadyMine->history)->toBeEmpty()
        ->and($nothingToDoWithMe->history)->toBeEmpty();
    Mail::assertNothingQueued();
});

test('the owner cannot take themselves off, but the previous owner can once someone has taken over', function () {
    Mail::fake();
    $newOwner = User::factory()->create();
    $previousOwner = User::factory()->create();
    $workItem = WorkItem::factory()
        ->onPlateOf($previousOwner)
        ->onHorizonOf($newOwner, addedBy: $previousOwner)
        ->ownedBy($newOwner)
        ->create();

    Livewire::actingAs($newOwner)
        ->test(MyPlate::class)
        ->assertDontSee('Take me off this')
        ->call('chooseToTakeOff', $workItem->id, $newOwner->id)
        ->assertForbidden();

    Livewire::actingAs($previousOwner)
        ->test(MyPlate::class)
        ->assertSee('Take me off this')
        ->call('chooseToTakeOff', $workItem->id, $previousOwner->id)
        ->call('takeOff')
        ->assertHasNoErrors();

    expect($workItem->people()->sole()->is($newOwner))->toBeTrue();
    Mail::assertQueuedCount(1);
    Mail::assertQueued(TakenOffWorkItemMail::class, fn (TakenOffWorkItemMail $mail) => $mail->hasTo($newOwner->email));
});

test('if whoever added me has since left, the owner is emailed when I take myself off', function () {
    Mail::fake();
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $addedMeThenLeft = User::factory()->create();
    $workItem = WorkItem::factory()
        ->onPlateOf($owner)
        ->onHorizonOf($addedMeThenLeft, addedBy: $owner)
        ->onHorizonOf($user, addedBy: $addedMeThenLeft)
        ->create();
    $workItem->people()->detach($addedMeThenLeft);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('chooseToTakeOff', $workItem->id, $user->id)
        ->assertSee($owner->full_name.' gets an email to let them know')
        ->call('takeOff');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(TakenOffWorkItemMail::class, fn (TakenOffWorkItemMail $mail) => $mail->hasTo($owner->email));
});

test('the page marks the owner of a shared work item and offers to take over only what I do not own', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $alsoOnIt = User::factory()->create();
    $theirs = WorkItem::factory()->onPlateOf($owner)->onHorizonOf($user, addedBy: $owner)->onHorizonOf($alsoOnIt, addedBy: $owner)->create();
    $mine = WorkItem::factory()->onPlateOf($user)->onHorizonOf($alsoOnIt, addedBy: $user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertSee('With '.$owner->full_name.' (owner) and '.$alsoOnIt->full_name)
        ->assertSee('With '.$alsoOnIt->full_name)
        ->assertDontSee($alsoOnIt->full_name.' (owner)')
        ->assertSee('Take this over')
        ->assertSeeHtml('wire:click="takeOver('.$theirs->id.')"')
        ->assertDontSeeHtml('wire:click="takeOver('.$mine->id.')"');
});
