<?php

use App\Models\Team;
use App\Models\User;

test('someone reports to the lead of their team, and someone in no team reports to nobody', function () {
    $lead = User::factory()->create();
    $team = Team::factory()->ledBy($lead)->create();
    $member = User::factory()->inTeam($team)->create();
    $inNoTeam = User::factory()->create();

    expect($member->lineManager()->is($lead))->toBeTrue()
        ->and($inNoTeam->lineManager())->toBeNull();
});

test('the people who report to a lead are the members of every team they lead', function () {
    $lead = User::factory()->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $sysadmin = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $inSomeoneElsesTeam = User::factory()->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create();

    expect($lead->reports->modelKeys())->toEqualCanonicalizing([$developer->id, $sysadmin->id])
        ->and($lead->isLineManagerOf($developer))->toBeTrue()
        ->and($lead->isLineManagerOf($inSomeoneElsesTeam))->toBeFalse()
        ->and($developer->reports)->toBeEmpty();
});

test('my team, for someone who leads people, is them and the people who report to them', function () {
    $manager = User::factory()->create();
    $managersTeam = Team::factory()->ledBy($manager)->create();
    $lead = User::factory()->inTeam($managersTeam)->create();
    $fellowLead = User::factory()->inTeam($managersTeam)->create();
    $developer = User::factory()->inTeam(Team::factory()->ledBy($lead)->create())->create();
    $fellowLeadsReport = User::factory()->inTeam(Team::factory()->ledBy($fellowLead)->create())->create();

    expect($lead->peopleInMyTeam()->modelKeys())->toEqualCanonicalizing([$lead->id, $developer->id]);
});

test('my team, for someone who leads nobody, is their lead and the others who report to that lead', function () {
    $lead = User::factory()->create();
    $team = Team::factory()->ledBy($lead)->create();
    $developer = User::factory()->inTeam($team)->create();
    $fellowDeveloper = User::factory()->inTeam($team)->create();
    $inAnotherTeam = User::factory()->inTeam(Team::factory()->ledBy(User::factory()->create())->create())->create();
    $inNoTeam = User::factory()->create();

    expect($developer->peopleInMyTeam()->modelKeys())->toEqualCanonicalizing([$developer->id, $fellowDeveloper->id, $lead->id])
        ->and($inNoTeam->peopleInMyTeam()->modelKeys())->toBe([$inNoTeam->id]);
});

test('someone is shown with their job title when they have one', function () {
    $withJobTitle = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'MacAdmin', 'job_title' => 'Infrastructure Lead']);
    $withoutJobTitle = User::factory()->create(['forenames' => 'Jenny', 'surname' => 'McUser']);

    expect($withJobTitle->name_with_job_title)->toBe('Jenny MacAdmin (Infrastructure Lead)')
        ->and($withoutJobTitle->name_with_job_title)->toBe('Jenny McUser');
});
