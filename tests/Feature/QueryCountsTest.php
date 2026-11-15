<?php

use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;

test('my plate runs the same number of queries however much is on it', function () {
    $user = User::factory()->create();
    $colleague = User::factory()->create();
    WorkItem::factory()->count(2)->onPlateOf($user)->create();
    WorkItem::factory()->onPlateOf($colleague)->onHorizonOf($user, addedBy: $colleague)->create();

    $this->assertQueryCountStaysTheSame(
        visiting: fn () => $this->actingAs($user->fresh())->get(route('home'))->assertOk(),
        afterAdding: function () use ($user) {
            WorkItem::factory()->count(3)->onPlateOf($user)->create();
            User::factory()->count(3)->create()->each(fn (User $someoneElse) => WorkItem::factory()->onPlateOf($someoneElse)->onHorizonOf($user, addedBy: $someoneElse)->create(['title' => 'Added later by '.$someoneElse->id]));
        },
    )->assertSee('Added later by');
});

test('the classifieds page runs the same number of queries however much is in it', function () {
    $user = User::factory()->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create();
    WorkItem::factory()->count(2)->lookingForAHand()->onPlateOf(User::factory()->create())->create();

    $this->assertQueryCountStaysTheSame(
        visiting: fn () => $this->actingAs($user->fresh())->get(route('looking-for-a-hand'))->assertOk(),
        afterAdding: fn () => User::factory()->count(4)->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create()
            ->each(fn (User $owner) => WorkItem::factory()->lookingForAHand()->onPlateOf($owner)->onHorizonOf(User::factory()->create(), addedBy: $owner)->create(['title' => 'Added later by '.$owner->id])),
    )->assertSee('Added later by');
});

test('the people page runs the same number of queries however many people and teams there are', function () {
    $admin = User::factory()->peopleAdmin()->create();
    User::factory()->count(2)->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create();

    $this->assertQueryCountStaysTheSame(
        visiting: fn () => $this->actingAs($admin->fresh())->get(route('people'))->assertOk(),
        afterAdding: fn () => collect(range(1, 3))->each(fn () => User::factory()->count(2)->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create(['surname' => 'McAddedLater'])),
    )->assertSee('McAddedLater');
});

test('the overview runs the same number of queries however many people and areas are below me', function () {
    $head = User::factory()->create();
    $sectionHeads = Team::factory()->ledBy($head)->create();
    $anArea = function (string $surname) use ($sectionHeads) {
        $lead = User::factory()->inTeam($sectionHeads)->create(['surname' => $surname]);
        User::factory()->count(3)->inTeam(Team::factory()->ledBy($lead)->create())->create()
            ->each(fn (User $person) => WorkItem::factory()->count(2)->onPlateOf($person)->create());
    };
    $anArea('McFirst');

    $this->assertQueryCountStaysTheSame(
        visiting: fn () => $this->actingAs($head->fresh())->get(route('overview'))->assertOk(),
        afterAdding: fn () => collect(['McAddedLater', 'McAddedLaterStill'])->each($anArea),
    )->assertSee('McAddedLaterStill');
});
