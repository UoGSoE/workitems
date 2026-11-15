<?php

use App\Enums\PlateStatus;
use App\Livewire\MyTeam;
use App\Mail\AddedToWorkItemMail;
use App\Mail\TakenOffWorkItemMail;
use App\Mail\WorkItemsMovedMail;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('a lead can move a work item one of their people owns to another of their people, who then owns it', function () {
    Mail::fake();
    $lead = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    $team = Team::factory()->ledBy($lead)->create();
    $from = User::factory()->inTeam($team)->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $to = User::factory()->inTeam($team)->create(['forenames' => 'Jenny', 'surname' => 'McTaker']);
    $fromAnotherTeam = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($from)->onHorizonOf($fromAnotherTeam, addedBy: $from)->create(['title' => 'Replace the storage array']);
    $untouched = WorkItem::factory()->onPlateOf($from)->create();

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->call('show', $workItem->id, $from->id)
        ->assertSee('Move it from Olivia McUser to')
        ->set('moveToId', $to->id)
        ->call('move')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'work-item')
        ->assertSet('moveToId', '');

    $workItem->refresh();
    expect($workItem->owner->is($to))->toBeTrue()
        ->and($workItem->people->modelKeys())->toEqualCanonicalizing([$to->id, $fromAnotherTeam->id])
        ->and($workItem->people->find($to)->pivot->status)->toBe(PlateStatus::Upcoming)
        ->and($workItem->people->find($to)->pivot->added_by_id)->toBe($lead->id)
        ->and($workItem->people->find($to)->pivot->reason)->toBe('Taking over from Olivia McUser')
        ->and($workItem->history->pluck('description')->all())->toBe(['moved it from Olivia McUser to Jenny McTaker'])
        ->and($workItem->history->pluck('user_id')->unique()->all())->toBe([$lead->id])
        ->and($untouched->fresh()->owner->is($from))->toBeTrue()
        ->and($untouched->people->modelKeys())->toBe([$from->id]);

    Mail::assertQueuedCount(2);
    Mail::assertQueued(AddedToWorkItemMail::class, fn (AddedToWorkItemMail $mail) => $mail->hasTo($to->email));
    Mail::assertQueued(TakenOffWorkItemMail::class, fn (TakenOffWorkItemMail $mail) => $mail->hasTo($from->email)
        && str_contains($mail->render(), 'Jenny MacAdmin gave your place to Jenny McTaker on'));
});

test('moving the place of someone who does not own the work item leaves its owner alone, and tells them', function () {
    Mail::fake();
    $lead = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    $team = Team::factory()->ledBy($lead)->create();
    $from = User::factory()->inTeam($team)->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $to = User::factory()->inTeam($team)->create();
    $alreadyOnIt = User::factory()->inTeam($team)->create(['forenames' => 'Jenny', 'surname' => 'McTaker']);
    $owner = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($owner)->onHorizonOf($from, addedBy: $owner)->onHorizonOf($alreadyOnIt, addedBy: $owner)->create();

    Livewire::actingAs($lead)->test(MyTeam::class)->call('show', $workItem->id, $from->id)->set('moveToId', $alreadyOnIt->id)->call('move')->assertHasNoErrors();

    expect($workItem->fresh()->owner->is($owner))->toBeTrue()
        ->and($workItem->fresh()->people->modelKeys())->toEqualCanonicalizing([$owner->id, $alreadyOnIt->id]);
    Mail::assertQueuedCount(2);
    Mail::assertQueued(TakenOffWorkItemMail::class, fn (TakenOffWorkItemMail $mail) => $mail->hasTo($from->email));
    Mail::assertQueued(TakenOffWorkItemMail::class, fn (TakenOffWorkItemMail $mail) => $mail->hasTo($owner->email)
        && str_contains($mail->render(), $lead->full_name.' swapped '.$from->full_name.' for '.$alreadyOnIt->full_name.' on'));

    Livewire::actingAs($lead)->test(MyTeam::class)->call('show', $workItem->id, $alreadyOnIt->id)->set('moveToId', $to->id)->call('move')->assertHasNoErrors();

    expect($workItem->fresh()->owner->is($owner))->toBeTrue()
        ->and($workItem->fresh()->people->modelKeys())->toEqualCanonicalizing([$owner->id, $to->id]);
});

test('a lead can only move the places of their own people, and only to their own people', function () {
    Mail::fake();
    $lead = User::factory()->create();
    $from = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $fromAnotherTeam = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($from)->onHorizonOf($fromAnotherTeam, addedBy: $from)->onHorizonOf($lead, addedBy: $from)->create();

    Livewire::actingAs($lead)->test(MyTeam::class)->call('show', $workItem->id, $fromAnotherTeam->id)->assertNotFound();

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->call('show', $workItem->id, $from->id)
        ->call('move')
        ->assertHasErrors('moveToId')
        ->set('moveToId', $fromAnotherTeam->id)
        ->call('move')
        ->assertHasErrors('moveToId')
        ->set('moveToId', $from->id)
        ->call('move')
        ->assertHasErrors('moveToId');

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->call('show', $workItem->id, $lead->id)
        ->assertDontSee('Move it from')
        ->set('moveToId', $from->id)
        ->call('move')
        ->assertForbidden();

    expect(fn () => Livewire::actingAs($lead)->test(MyTeam::class)->set('showingForId', $fromAnotherTeam->id))->toThrow(CannotUpdateLockedPropertyException::class)
        ->and($workItem->fresh()->owner->is($from))->toBeTrue()
        ->and($workItem->fresh()->people)->toHaveCount(3);
    Mail::assertNothingQueued();
});

test('a lead can move a work item from one of their people to themselves, without being emailed about it', function () {
    Mail::fake();
    $lead = User::factory()->create();
    $from = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $workItem = WorkItem::factory()->onPlateOf($from)->create();

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->call('show', $workItem->id, $from->id)
        ->assertSeeInOrder(['Choose someone', 'Me'])
        ->set('moveToId', $lead->id)
        ->call('move')
        ->assertHasNoErrors();

    $workItem->refresh();
    expect($workItem->owner->is($lead))->toBeTrue()
        ->and($workItem->people->modelKeys())->toBe([$lead->id])
        ->and($workItem->people->first()->pivot->status)->toBe(PlateStatus::Upcoming);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(TakenOffWorkItemMail::class, fn (TakenOffWorkItemMail $mail) => $mail->hasTo($from->email));
});

test('the owner is not emailed about a swap they made themselves, and is not told they were swapped for themselves', function () {
    Mail::fake();
    $lead = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    $team = Team::factory()->ledBy($lead)->create();
    $from = User::factory()->inTeam($team)->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $to = User::factory()->inTeam($team)->create();
    $ownedByTheLead = WorkItem::factory()->onPlateOf($lead)->onHorizonOf($from, addedBy: $lead)->create();
    $ownedByTo = WorkItem::factory()->onPlateOf($to)->onHorizonOf($from, addedBy: $to)->create();

    Livewire::actingAs($lead)->test(MyTeam::class)->call('show', $ownedByTheLead->id, $from->id)->set('moveToId', $to->id)->call('move')->assertHasNoErrors();

    Mail::assertQueuedCount(2);
    Mail::assertNotQueued(TakenOffWorkItemMail::class, fn (TakenOffWorkItemMail $mail) => $mail->hasTo($lead->email));

    Livewire::actingAs($lead)->test(MyTeam::class)->call('show', $ownedByTo->id, $from->id)->set('moveToId', $to->id)->call('move')->assertHasNoErrors();

    Mail::assertQueuedCount(4);
    Mail::assertQueued(TakenOffWorkItemMail::class, fn (TakenOffWorkItemMail $mail) => $mail->hasTo($to->email)
        && str_contains($mail->render(), 'Jenny MacAdmin took Olivia McUser off'));
});

test('a lead can move all of one person\'s open work to another of their people in one go', function () {
    Mail::fake();
    $lead = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    $team = Team::factory()->ledBy($lead)->create();
    $from = User::factory()->inTeam($team)->create(['forenames' => 'Olivia', 'surname' => 'McUser']);
    $to = User::factory()->inTeam($team)->create(['forenames' => 'Jenny', 'surname' => 'McTaker']);
    $bystander = User::factory()->inTeam($team)->create();
    $ownerElsewhere = User::factory()->create();
    $owned = WorkItem::factory()->onPlateOf($from)->create(['title' => 'Replace the storage array']);
    $helpingOn = WorkItem::factory()->onPlateOf($ownerElsewhere)->onHorizonOf($from, addedBy: $ownerElsewhere)->create(['title' => 'Update the samba servers']);
    $parked = WorkItem::factory()->parked()->onPlateOf($from)->create();
    $done = WorkItem::factory()->done()->onPlateOf($from)->create();
    $notTheirs = WorkItem::factory()->onPlateOf($bystander)->create();

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->assertSee('Move it all from Olivia McUser to')
        ->assertSetStrict('moveAllTo.'.$from->id, '')
        ->set('moveAllTo.'.$from->id, $to->id)
        ->call('askToMoveAll', $from->id)
        ->assertDispatched('modal-show', name: 'confirm-move-all')
        ->assertSeeInOrder(['Move all 2 work items?', 'From Olivia McUser to Jenny McTaker.'])
        ->call('moveAll')
        ->assertHasNoErrors()
        ->assertDispatched('modal-close', name: 'work-of-'.$from->id)
        ->assertSetStrict('moveAllTo.'.$from->id, '')
        ->assertSetStrict('moveAllTo.'.$to->id, '');

    expect($owned->fresh()->owner->is($to))->toBeTrue()
        ->and($owned->people->modelKeys())->toBe([$to->id])
        ->and($helpingOn->fresh()->owner->is($ownerElsewhere))->toBeTrue()
        ->and($helpingOn->people->modelKeys())->toEqualCanonicalizing([$ownerElsewhere->id, $to->id])
        ->and($parked->fresh()->owner->is($from))->toBeTrue()
        ->and($parked->people->modelKeys())->toBe([$from->id])
        ->and($done->fresh()->owner->is($from))->toBeTrue()
        ->and($notTheirs->fresh()->owner->is($bystander))->toBeTrue()
        ->and($notTheirs->people->modelKeys())->toBe([$bystander->id])
        ->and($owned->history->pluck('description')->all())->toContain('moved it from Olivia McUser to Jenny McTaker');

    $says = fn (User $reader, string ...$lines) => fn (WorkItemsMovedMail $mail) => $mail->hasTo($reader->email) && str($mail->render())->containsAll($lines);

    Mail::assertQueuedCount(3);
    Mail::assertQueued(WorkItemsMovedMail::class, $says($from, $lead->full_name.' moved all of your open work to Jenny McTaker', 'Replace the storage array', 'Update the samba servers'));
    Mail::assertQueued(WorkItemsMovedMail::class, $says($to, $lead->full_name.' moved everything Olivia McUser was working on to you', 'Replace the storage array', 'Update the samba servers'));
    Mail::assertQueued(WorkItemsMovedMail::class, $says($ownerElsewhere, $lead->full_name.' swapped Olivia McUser for Jenny McTaker on work you own', 'Update the samba servers'));
    Mail::assertNotQueued(WorkItemsMovedMail::class, fn (WorkItemsMovedMail $mail) => $mail->hasTo($ownerElsewhere->email) && str_contains($mail->render(), 'Replace the storage array'));
});

test('moving all of someone\'s work needs a choice of person, and only works between a lead\'s own people', function () {
    Mail::fake();
    $lead = User::factory()->create();
    $from = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $notTheirs = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($from)->create();
    $workItemOfSomeoneElse = WorkItem::factory()->onPlateOf($notTheirs)->create();

    Livewire::actingAs($lead)
        ->test(MyTeam::class)
        ->call('askToMoveAll', $from->id)
        ->assertHasErrors('moveAllTo.'.$from->id)
        ->assertNotDispatched('modal-show')
        ->set('moveAllTo.'.$from->id, $notTheirs->id)
        ->call('askToMoveAll', $from->id)
        ->assertHasErrors('moveAllTo.'.$from->id)
        ->assertNotDispatched('modal-show')
        ->set('moveAllTo.'.$from->id, $from->id)
        ->call('askToMoveAll', $from->id)
        ->assertHasErrors('moveAllTo.'.$from->id)
        ->assertNotDispatched('modal-show');

    Livewire::actingAs($lead)->test(MyTeam::class)->set('moveAllTo.'.$notTheirs->id, $from->id)->call('askToMoveAll', $notTheirs->id)->assertNotFound();
    Livewire::actingAs($lead)->test(MyTeam::class)->set('moveAllTo.'.$lead->id, $from->id)->call('askToMoveAll', $lead->id)->assertNotFound();
    Livewire::actingAs($lead)->test(MyTeam::class)->set('moveAllTo.'.$from->id, $lead->id)->call('moveAll')->assertNotFound();
    expect(fn () => Livewire::actingAs($lead)->test(MyTeam::class)->set('movingAllFromId', $from->id))->toThrow(CannotUpdateLockedPropertyException::class);

    expect($workItem->fresh()->owner->is($from))->toBeTrue()
        ->and($workItem->people->modelKeys())->toBe([$from->id])
        ->and($workItemOfSomeoneElse->fresh()->owner->is($notTheirs))->toBeTrue();
    Mail::assertNothingQueued();
});

test('a lead who moves all of someone\'s work to themselves is not emailed about it', function () {
    Mail::fake();
    $lead = User::factory()->create();
    $from = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $workItem = WorkItem::factory()->onPlateOf($from)->create();
    $ownedByTheLead = WorkItem::factory()->onPlateOf($lead)->onHorizonOf($from, addedBy: $lead)->create();

    Livewire::actingAs($lead)->test(MyTeam::class)->set('moveAllTo.'.$from->id, $lead->id)->call('askToMoveAll', $from->id)->call('moveAll')->assertHasNoErrors();

    expect($workItem->fresh()->owner->is($lead))->toBeTrue()
        ->and($workItem->people->modelKeys())->toBe([$lead->id])
        ->and($ownedByTheLead->fresh()->people->modelKeys())->toBe([$lead->id]);

    Mail::assertQueuedCount(1);
    Mail::assertQueued(WorkItemsMovedMail::class, fn (WorkItemsMovedMail $mail) => $mail->hasTo($from->email));
});

test('a work item title cannot smuggle a picture or a link into the email about several moves', function () {
    $who = User::factory()->make(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    $workItems = WorkItem::factory()->count(1)->make(['title' => "Update the samba servers\n\n![x](https://example.test/a.png) [link](https://example.test)"]);

    $html = (new WorkItemsMovedMail($who, 'moved all of your open work to Olivia McUser', $workItems))->render();

    expect($html)->toContain('Update the samba servers')->not->toContain('<img')->not->toContain('href="https://example.test"');
});

test('secret work is marked as secret in the list of everything a person has', function () {
    $lead = User::factory()->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    WorkItem::factory()->secret()->onPlateOf($developer)->create(['title' => 'Plan the surprise party']);
    WorkItem::factory()->onPlateOf($developer)->create(['title' => 'Update the samba servers']);

    $page = Livewire::actingAs($lead)->test(MyTeam::class)->assertSeeInOrder(['On their plate', 'Plan the surprise party', 'Secret', 'Update the samba servers']);

    expect(substr_count($page->html(), 'Secret'))->toBe(1);
});

test('before moving everything, a lead is told how much of it is secret and who will now see it', function () {
    $lead = User::factory()->create();
    $team = Team::factory()->ledBy($lead)->create();
    $from = User::factory()->inTeam($team)->create();
    $to = User::factory()->inTeam($team)->create(['forenames' => 'Jenny', 'surname' => 'McTaker']);
    $nothingSecret = User::factory()->inTeam($team)->create();
    WorkItem::factory()->secret()->onPlateOf($from)->create();
    WorkItem::factory()->onPlateOf($from)->create();
    WorkItem::factory()->onPlateOf($nothingSecret)->create();

    Livewire::actingAs($lead)->test(MyTeam::class)->set('moveAllTo.'.$from->id, $to->id)->call('askToMoveAll', $from->id)
        ->assertSee('1 of these is secret. Jenny McTaker will be able to see it.');

    WorkItem::factory()->secret()->onHorizonOf($from)->create();

    Livewire::actingAs($lead)->test(MyTeam::class)->set('moveAllTo.'.$from->id, $to->id)->call('askToMoveAll', $from->id)
        ->assertSee('2 of these are secret. Jenny McTaker will be able to see them.');

    Livewire::actingAs($lead)->test(MyTeam::class)->set('moveAllTo.'.$from->id, $lead->id)->call('askToMoveAll', $from->id)
        ->assertDontSee('will be able to see');

    Livewire::actingAs($lead)->test(MyTeam::class)->set('moveAllTo.'.$nothingSecret->id, $to->id)->call('askToMoveAll', $nothingSecret->id)
        ->assertSee('Move all 1 work item?')
        ->assertDontSee('will be able to see');
});

test('a lead moving one secret work item is told that the person who gets it will see it', function () {
    $lead = User::factory()->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $secret = WorkItem::factory()->secret()->onPlateOf($developer)->create();
    $notSecret = WorkItem::factory()->onPlateOf($developer)->create();

    Livewire::actingAs($lead)->test(MyTeam::class)->call('show', $secret->id, $developer->id)
        ->assertSee('This work item is secret. Whoever you move it to will be able to see it.');

    Livewire::actingAs($lead)->test(MyTeam::class)->call('show', $notSecret->id, $developer->id)
        ->assertSee('Move it from')
        ->assertDontSee('will be able to see it');
});
