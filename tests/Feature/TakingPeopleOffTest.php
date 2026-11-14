<?php

use App\Livewire\MyPlate;
use App\Mail\TakenOffWorkItemMail;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('taking myself off a work item emails whoever added me, with my message', function () {
    Mail::fake();
    $user = User::factory()->create(['forenames' => 'Jenny', 'surname' => "O'Admin"]);
    $whoAddedMe = User::factory()->create();
    $alsoOnIt = User::factory()->create();
    $workItem = WorkItem::factory()
        ->onPlateOf($alsoOnIt)
        ->onHorizonOf($whoAddedMe, addedBy: $alsoOnIt)
        ->onHorizonOf($user, addedBy: $whoAddedMe)
        ->create(['title' => 'Replace the storage array']);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('chooseToTakeOff', $workItem->id, $user->id)
        ->set('message', 'I am away until the new year')
        ->call('takeOff');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(TakenOffWorkItemMail::class, function (TakenOffWorkItemMail $mail) use ($user, $whoAddedMe) {
        return $mail->hasTo($whoAddedMe->email)
            && $mail->hasSubject('['.config('app.name').'] '.$user->full_name.' removed themselves from a work item')
            && $mail->assertSeeInHtml("Jenny O'Admin removed themselves from", escape: false)
            && $mail->assertSeeInHtml('Replace the storage array')
            && $mail->assertSeeInHtml(route('home'))
            && $mail->assertSeeInHtml('I am away until the new year');
    });
});

test('taking off someone I added removes only them and emails them', function () {
    Mail::fake();
    $user = User::factory()->create();
    $takenOff = User::factory()->create();
    $staysOn = User::factory()->create();
    $workItem = WorkItem::factory()
        ->onPlateOf($user)
        ->onHorizonOf($takenOff, addedBy: $user)
        ->onHorizonOf($staysOn, addedBy: $user)
        ->create();
    $anotherWorkItem = WorkItem::factory()->onPlateOf($user)->onHorizonOf($takenOff, addedBy: $user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('chooseToTakeOff', $workItem->id, $takenOff->id)
        ->call('takeOff')
        ->assertHasNoErrors();

    expect($workItem->people()->pluck('users.id')->all())->toEqualCanonicalizing([$user->id, $staysOn->id])
        ->and($anotherWorkItem->people()->count())->toBe(2);
    Mail::assertQueuedCount(1);
    Mail::assertQueued(TakenOffWorkItemMail::class, function (TakenOffWorkItemMail $mail) use ($user, $takenOff) {
        return $mail->hasTo($takenOff->email)
            && $mail->hasSubject('['.config('app.name').'] '.$user->full_name.' took you off a work item')
            && $mail->assertDontSeeInHtml('Message:');
    });
});

test('a person cannot take off someone they did not add, or anyone on a work item they are not on', function () {
    Mail::fake();
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $addedByOwner = User::factory()->create();
    $sharedWithMe = WorkItem::factory()
        ->onPlateOf($owner)
        ->onHorizonOf($user, addedBy: $owner)
        ->onHorizonOf($addedByOwner, addedBy: $owner)
        ->create();
    $nothingToDoWithMe = WorkItem::factory()->onPlateOf($owner)->onHorizonOf($addedByOwner, addedBy: $owner)->create();

    Livewire::actingAs($user)->test(MyPlate::class)
        ->call('chooseToTakeOff', $sharedWithMe->id, $addedByOwner->id)
        ->assertForbidden();
    Livewire::actingAs($user)->test(MyPlate::class)
        ->call('chooseToTakeOff', $sharedWithMe->id, $owner->id)
        ->assertForbidden();
    Livewire::actingAs($user)->test(MyPlate::class)
        ->call('chooseToTakeOff', $nothingToDoWithMe->id, $addedByOwner->id)
        ->assertNotFound();

    expect($sharedWithMe->people()->count())->toBe(3)
        ->and($nothingToDoWithMe->people()->count())->toBe(2);
    Mail::assertNothingQueued();
});

test('who is being taken off, and from what, cannot be swapped from the browser', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $onColleaguesPlate = WorkItem::factory()->onPlateOf($colleague)->create();
    $page = Livewire::actingAs($user)->test(MyPlate::class);

    expect(fn () => $page->set('takingOffFromId', $onColleaguesPlate->id))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and(fn () => $page->set('takingOffPersonId', $colleague->id))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and($onColleaguesPlate->people()->count())->toBe(1);
});

test('taking someone off is recorded in the history', function () {
    Mail::fake();
    $user = User::factory()->create();
    $whoAddedMe = User::factory()->create();
    $iLeave = WorkItem::factory()->onPlateOf($whoAddedMe)->onHorizonOf($user, addedBy: $whoAddedMe)->create();
    $iTakeSomeoneOff = WorkItem::factory()->onPlateOf($user)->onHorizonOf($whoAddedMe, addedBy: $user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('chooseToTakeOff', $iLeave->id, $user->id)
        ->call('takeOff')
        ->call('chooseToTakeOff', $iTakeSomeoneOff->id, $whoAddedMe->id)
        ->call('takeOff');

    expect($iLeave->history()->sole()->description)->toBe('took themselves off it')
        ->and($iLeave->history()->sole()->user->is($user))->toBeTrue()
        ->and($iTakeSomeoneOff->history()->sole()->description)->toBe('took '.$whoAddedMe->full_name.' off it')
        ->and($iTakeSomeoneOff->history()->sole()->user->is($user))->toBeTrue();
});

test('the page offers to take off me or anyone I added, and says who will be emailed', function () {
    $user = User::factory()->create();
    $whoAddedMe = User::factory()->create();
    $addedByMe = User::factory()->create();
    $addedBySomeoneElse = User::factory()->create();
    $workItem = WorkItem::factory()
        ->onPlateOf($whoAddedMe)
        ->onHorizonOf($user, addedBy: $whoAddedMe)
        ->onHorizonOf($addedByMe, addedBy: $user)
        ->onHorizonOf($addedBySomeoneElse, addedBy: $whoAddedMe)
        ->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->assertSee('Take me off this')
        ->assertSee('Take '.$addedByMe->full_name.' off this')
        ->assertDontSee('Take '.$addedBySomeoneElse->full_name.' off this')
        ->assertDontSee('Take '.$whoAddedMe->full_name.' off this')
        ->assertDontSee('gets an email')
        ->call('chooseToTakeOff', $workItem->id, $user->id)
        ->assertSee($whoAddedMe->full_name.' gets an email to let them know')
        ->call('chooseToTakeOff', $workItem->id, $addedByMe->id)
        ->assertSee($addedByMe->full_name.' gets an email to let them know');
});

test('the email shows the title and message as typed and does not turn them into links or images', function () {
    $mail = new TakenOffWorkItemMail(
        WorkItem::factory()->make(['title' => 'Replace the *storage* array']),
        User::factory()->make(),
        'took you off',
        "See [this page](https://example.test/x) ![](https://example.test/pixel.png)\n\nSecond paragraph with **stars**",
    );

    $mail->assertSeeInHtml('Replace the *storage* array')
        ->assertSeeInHtml('[this page](https://example.test/x)')
        ->assertSeeInHtml('Second paragraph with **stars**')
        ->assertDontSeeInHtml('href="https://example.test/x"', escape: false)
        ->assertDontSeeInHtml('<img', escape: false);
});
