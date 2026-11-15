<?php

use App\Enums\PlateStatus;
use App\Livewire\MyPlate;
use App\Livewire\People;
use App\Mail\WorkItemsMovedMail;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

test('when someone goes, a work item they owned passes to the chosen person and lands on their horizon', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $leaver = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McLeaver']);
    $takesOver = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McUser']);
    $workItem = WorkItem::factory()->onPlateOf($leaver)->create(['title' => 'Replace the storage array']);
    $anotherTheyOwned = WorkItem::factory()->onPlateOf($leaver)->create(['title' => 'Update the samba servers']);

    $leaver->markAsGone(by: $admin, giveWorkTo: $takesOver);

    $workItem->refresh();
    expect($workItem->owner->is($takesOver))->toBeTrue()
        ->and($anotherTheyOwned->fresh()->owner->is($takesOver))->toBeTrue()
        ->and($workItem->people->modelKeys())->toBe([$takesOver->id])
        ->and($workItem->people->first()->pivot->status)->toBe(PlateStatus::Upcoming)
        ->and($workItem->history()->orderBy('id')->pluck('description')->all())->toBe(['added Jenny McUser', 'gave it to Jenny McUser from Jenny McLeaver'])
        ->and($workItem->history->load('user')->every(fn ($line) => $line->user->is($admin)))->toBeTrue();
    Mail::assertQueuedCount(1);
    Mail::assertQueued(WorkItemsMovedMail::class, fn (WorkItemsMovedMail $mail) => $mail->hasTo($takesOver->email)
        && str($mail->render())->containsAll([$admin->full_name.' gave you work that Jenny McLeaver owned', 'Replace the storage array', 'Update the samba servers']));
});

test('when someone goes, they come off work they did not own, and finished work and other people\'s work are left alone', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $leaver = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McLeaver']);
    $colleague = User::factory()->create();
    $colleaguesWorkItem = WorkItem::factory()->onPlateOf($colleague)->onHorizonOf($leaver, addedBy: $colleague)->create();
    $finished = WorkItem::factory()->done()->onPlateOf($leaver)->create();
    $nothingToDoWithThem = WorkItem::factory()->onPlateOf($colleague)->create();

    $leaver->markAsGone(by: $admin);

    expect($colleaguesWorkItem->fresh()->owner->is($colleague))->toBeTrue()
        ->and($colleaguesWorkItem->people()->get()->modelKeys())->toBe([$colleague->id])
        ->and($colleaguesWorkItem->history()->sole()->description)->toBe('took Jenny McLeaver off it')
        ->and($finished->fresh()->owner->is($leaver))->toBeTrue()
        ->and($finished->people()->get()->modelKeys())->toBe([$leaver->id])
        ->and($finished->history)->toBeEmpty()
        ->and($nothingToDoWithThem->people()->get()->modelKeys())->toBe([$colleague->id])
        ->and($nothingToDoWithThem->history)->toBeEmpty();
    Mail::assertNothingQueued();
});

test('when someone goes, a work item passes to the chosen person without fuss if they are already on it', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $leaver = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McLeaver']);
    $alreadyOnIt = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McUser']);
    $workItem = WorkItem::factory()->onPlateOf($leaver)->onPlateOf($alreadyOnIt)->create();

    $leaver->markAsGone(by: $admin, giveWorkTo: $alreadyOnIt);

    $workItem->refresh();
    expect($workItem->owner->is($alreadyOnIt))->toBeTrue()
        ->and($workItem->people->modelKeys())->toBe([$alreadyOnIt->id])
        ->and($workItem->people->first()->pivot->status)->toBe(PlateStatus::InProgress)
        ->and($workItem->history()->sole()->description)->toBe('gave it to Jenny McUser from Jenny McLeaver');
    Mail::assertNothingQueued();
});

test('an admin can mark someone as gone from the people page, choosing who gets their work and who leads their team', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->create();
    $leaver = User::factory()->inTeam(Team::factory()->ledBy($manager)->create())->create();
    $teamTheyLed = Team::factory()->ledBy($leaver)->create();
    $takesOver = User::factory()->create();
    $workItem = WorkItem::factory()->onPlateOf($leaver)->create();

    Livewire::actingAs($admin)
        ->test(People::class)
        ->call('chooseWhoIsGoing', $leaver->id)
        ->assertDispatched('modal-show', name: 'mark-as-gone')
        ->assertSet('giveWorkToId', '', strict: true)
        ->assertSet('newLeadId', $manager->id)
        ->set('giveWorkToId', $takesOver->id)
        ->call('markAsGone')
        ->assertHasNoErrors();

    expect($leaver->fresh()->hasLeft())->toBeTrue()
        ->and($workItem->fresh()->owner->is($takesOver))->toBeTrue()
        ->and($teamTheyLed->fresh()->lead->is($manager))->toBeTrue()
        ->and(User::current()->count())->toBe(3);
});

test('the admin is told how much work the person going is on, but not what it is', function () {
    $admin = User::factory()->admin()->create();
    $leaver = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McLeaver']);
    $colleague = User::factory()->create();
    WorkItem::factory()->onPlateOf($leaver)->create(['title' => 'Rotate the signing keys']);
    WorkItem::factory()->onPlateOf($colleague)->onHorizonOf($leaver, addedBy: $colleague)->create(['title' => 'Replace the storage array']);
    WorkItem::factory()->done()->onPlateOf($leaver)->create(['title' => 'Renew the wildcard certificate']);

    Livewire::actingAs($admin)
        ->test(People::class)
        ->call('chooseWhoIsGoing', $leaver->id)
        ->assertSee('Jenny McLeaver is on 2 work items that are still going on, and owns 1 of them.')
        ->assertDontSee('Rotate the signing keys')
        ->assertDontSee('Replace the storage array')
        ->assertDontSee('Renew the wildcard certificate');
});

test('someone who has gone is listed as having left, and cannot be chosen to lead a team', function () {
    $admin = User::factory()->admin()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin']);
    $gone = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McLeaver', 'job_title' => 'Developer', 'left_on' => '2026-01-01']);
    $team = Team::factory()->ledBy($admin)->create();

    Livewire::actingAs($admin)
        ->test(People::class)
        ->assertSeeInOrder(['Jenny MacAdmin', 'People who have left', 'Jenny McLeaver (Developer), left on 1 Jan 2026', 'Teams'])
        ->assertSee('What to do about Jenny MacAdmin')
        ->assertDontSee('What to do about Jenny McLeaver')
        ->call('editTeam', $team->id)
        ->set('leadId', $gone->id)
        ->call('saveTeam')
        ->assertHasErrors('leadId');

    expect($team->fresh()->lead->is($admin))->toBeTrue();
});

test('an admin cannot give the work of someone who is going to themselves, unless that person reports to them', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $doesNotReportToTheAdmin = User::factory()->create();
    $reportsToTheAdmin = User::factory()->inTeam(Team::factory()->ledBy($admin)->create())->create();
    $notTheirsToSee = WorkItem::factory()->secret()->onPlateOf($doesNotReportToTheAdmin)->create();
    $alreadyTheirsToSee = WorkItem::factory()->secret()->onPlateOf($reportsToTheAdmin)->create();

    Livewire::actingAs($admin)
        ->test(People::class)
        ->call('chooseWhoIsGoing', $doesNotReportToTheAdmin->id)
        ->set('giveWorkToId', $admin->id)
        ->call('markAsGone')
        ->assertHasErrors('giveWorkToId')
        ->call('chooseWhoIsGoing', $reportsToTheAdmin->id)
        ->set('giveWorkToId', $admin->id)
        ->call('markAsGone')
        ->assertHasNoErrors();

    expect($doesNotReportToTheAdmin->fresh()->hasLeft())->toBeFalse()
        ->and($notTheirsToSee->fresh()->owner->is($doesNotReportToTheAdmin))->toBeTrue()
        ->and($notTheirsToSee->people()->get()->modelKeys())->toBe([$doesNotReportToTheAdmin->id])
        ->and($reportsToTheAdmin->fresh()->hasLeft())->toBeTrue()
        ->and($alreadyTheirsToSee->fresh()->owner->is($admin))->toBeTrue();
});

test('someone who has gone cannot be edited, or be put back in a team', function () {
    $admin = User::factory()->admin()->create();
    $gone = User::factory()->create(['left_on' => '2026-01-01']);
    $stillHere = User::factory()->create();
    $team = Team::factory()->create();
    $pageLeftOpen = Livewire::actingAs($admin)->test(People::class)->call('editPerson', $stillHere->id);

    Livewire::actingAs($admin)->test(People::class)->call('editPerson', $gone->id)->assertNotFound();

    $stillHere->markAsGone(by: $admin);
    $pageLeftOpen->set('teamId', $team->id)->call('savePerson')->assertNotFound();

    expect($stillHere->fresh()->team_id)->toBeNull()
        ->and($team->members)->toBeEmpty();
});

test('someone is not marked as gone while work they own has nobody to go to', function () {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $leaver = User::factory()->create();
    $alreadyGone = User::factory()->create(['left_on' => '2026-01-01']);
    $teamTheyLed = Team::factory()->ledBy($leaver)->create();
    $memberOfThatTeam = User::factory()->inTeam($teamTheyLed)->create();
    $workItem = WorkItem::factory()->onPlateOf($leaver)->create();

    Livewire::actingAs($admin)
        ->test(People::class)
        ->call('chooseWhoIsGoing', $leaver->id)
        ->call('markAsGone')
        ->assertHasErrors('giveWorkToId')
        ->set('giveWorkToId', $leaver->id)
        ->call('markAsGone')
        ->assertHasErrors('giveWorkToId')
        ->set('giveWorkToId', $alreadyGone->id)
        ->set('newLeadId', $memberOfThatTeam->id)
        ->call('markAsGone')
        ->assertHasErrors(['giveWorkToId', 'newLeadId']);

    expect($leaver->fresh()->hasLeft())->toBeFalse()
        ->and($workItem->fresh()->owner->is($leaver))->toBeTrue()
        ->and($workItem->people()->get()->modelKeys())->toBe([$leaver->id])
        ->and($teamTheyLed->fresh()->lead->is($leaver))->toBeTrue();
    Mail::assertNothingQueued();
});

test('an admin cannot mark themselves as gone, and someone already marked as gone is not marked again', function () {
    $this->travelTo('2026-11-20 10:00');
    $admin = User::factory()->admin()->create();
    $leaver = User::factory()->create();
    $pageLeftOpen = Livewire::actingAs($admin)->test(People::class)->call('chooseWhoIsGoing', $leaver->id);

    $leaver->markAsGone(by: $admin);
    $this->travelTo('2026-11-27 10:00');

    $pageLeftOpen->call('markAsGone')->assertNotFound();
    Livewire::actingAs($admin)->test(People::class)->call('chooseWhoIsGoing', $admin->id)->assertNotFound();

    expect($leaver->fresh()->left_on->toDateString())->toBe('2026-11-20')
        ->and($admin->fresh()->hasLeft())->toBeFalse();
});

test('someone who has gone is not offered, and cannot be chosen, when adding a person to a work item', function () {
    Mail::fake();
    $user = User::factory()->create();
    $stillHere = User::factory()->create();
    $gone = User::factory()->create(['left_on' => '2026-01-01']);
    $workItem = WorkItem::factory()->onPlateOf($user)->create();

    expect($workItem->peopleWhoCouldBeAdded()->get()->modelKeys())->toBe([$stillHere->id]);

    Livewire::actingAs($user)
        ->test(MyPlate::class)
        ->call('choosePersonFor', $workItem->id)
        ->set('personId', $gone->id)
        ->call('addPerson')
        ->assertHasErrors('personId');

    expect($workItem->people()->get()->modelKeys())->toBe([$user->id]);
    Mail::assertNothingQueued();
});

test('when a lead goes, they leave their own team and the team they led gets the chosen lead', function () {
    $this->travelTo('2026-11-20 10:00');
    $admin = User::factory()->admin()->create();
    $manager = User::factory()->create();
    $leaver = User::factory()->inTeam(Team::factory()->ledBy($manager)->create())->create();
    $teamTheyLed = Team::factory()->ledBy($leaver)->create();
    $unrelatedLead = User::factory()->create();
    $unrelatedTeam = Team::factory()->ledBy($unrelatedLead)->create();

    $leaver->markAsGone(by: $admin, newLeadOfTheirTeams: $manager);

    expect($leaver->fresh()->left_on->toDateString())->toBe('2026-11-20')
        ->and($leaver->fresh()->hasLeft())->toBeTrue()
        ->and($leaver->fresh()->team_id)->toBeNull()
        ->and($teamTheyLed->fresh()->lead->is($manager))->toBeTrue()
        ->and($unrelatedTeam->fresh()->lead->is($unrelatedLead))->toBeTrue()
        ->and($manager->fresh()->hasLeft())->toBeFalse()
        ->and(User::current()->get()->modelKeys())->toEqualCanonicalizing([$admin->id, $manager->id, $unrelatedLead->id]);
});
