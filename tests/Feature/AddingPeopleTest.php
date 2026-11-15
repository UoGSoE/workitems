<?php

use App\Livewire\MyPlate;
use App\Mail\AddedToWorkItemMail;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('adding someone to a work item emails them, with the reason', function () {
    Mail::fake();
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    $alreadyOnIt = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($user)->onHorizonOf($alreadyOnIt, addedBy: $user)->create(['title' => 'Replace the storage array']);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('choosePersonFor', $workItem->id)
        ->set('personId', $colleague->id)
        ->set('reason', 'You know how the DNS challenges are set up')
        ->call('addPerson');

    Mail::assertQueuedCount(1);
    Mail::assertQueued(AddedToWorkItemMail::class, function (AddedToWorkItemMail $mail) use ($user, $colleague) {
        return $mail->hasTo($colleague->email)
            && $mail->hasSubject('['.config('app.name').'] '.$user->full_name.' added you to a work item')
            && $mail->assertSeeInHtml('Replace the storage array')
            && $mail->assertSeeInHtml(route('home'))
            && $mail->assertSeeInHtml('You know how the DNS challenges are set up');
    });
});

test('an add that fails validation emails nobody', function () {
    Mail::fake();
    $user = User::factory()->create();
    $alreadyOnIt = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($user)->onHorizonOf($alreadyOnIt, addedBy: $user)->create();

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('choosePersonFor', $workItem->id)
        ->set('personId', $alreadyOnIt->id)
        ->call('addPerson')
        ->assertHasErrors(['personId']);

    expect($workItem->people()->count())->toBe(2);
    Mail::assertNothingQueued();
});

test('the added email leaves out the reason line when none was given, and shows a reason as typed', function () {
    $workItem = WorkItem::factory()->make(['title' => 'Replace the storage array']);
    $addedBy = User::factory()->make();

    (new AddedToWorkItemMail($workItem, $addedBy))->assertDontSeeInHtml('Why you');
    (new AddedToWorkItemMail($workItem, $addedBy, 'See [this page](https://example.test/x)'))
        ->assertSeeInHtml('Why you: See [this page](https://example.test/x)')
        ->assertDontSeeInHtml('href="https://example.test/x"', escape: false);
});

test('the added email shows the reason on one line however it was typed', function () {
    $workItem = WorkItem::factory()->make();

    (new AddedToWorkItemMail($workItem, User::factory()->make(), "You know\n\nthe   old one"))
        ->assertSeeInHtml('Why you: You know the old one');
});
