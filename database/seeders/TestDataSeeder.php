<?php

namespace Database\Seeders;

use App\Enums\PlateStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        [$adminUser, $standardUser] = $this->createUsers();

        $this->createPlateFor($standardUser);
        $this->createSharedWorkItems($adminUser, $standardUser);
        $colleagues = $this->createColleaguesWithPlates();
        $this->createTeams($adminUser, $standardUser, $colleagues);
    }

    private function createUsers(): array
    {
        $adminUser = User::factory()->create([
            'username' => 'admin2x',
            'email' => 'admin2x@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => true,
            'forenames' => 'Jenny',
            'surname' => 'MacAdmin',
        ]);

        $standardUser = User::factory()->create([
            'username' => 'user2x',
            'email' => 'user2x@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => false,
            'forenames' => 'Olivia',
            'surname' => 'McUser',
        ]);

        return [$adminUser, $standardUser];
    }

    private function createPlateFor(User $user): void
    {
        WorkItem::factory()->done()->onPlateOf($user)->create(['title' => 'Renew the wildcard certificate', 'effort_days' => 1]);
        WorkItem::factory()->parked()->onPlateOf($user)->create(['title' => 'Tidy up the old wiki pages']);

        WorkItem::factory()->onHorizonOf($user)->create(['title' => 'New server for the registry storage', 'notes' => 'Does not exist yet. The registry storage work waits on it.']);
        WorkItem::factory()->onHorizonOf($user)->create(['title' => 'Replace the storage behind the container registry', 'effort_days' => 1, 'elapsed_days' => 3]);
        WorkItem::factory()->onHorizonOf($user)->create(['title' => 'Move the VM-hosted apps into Compose', 'effort_days' => 10, 'elapsed_days' => 30]);
        WorkItem::factory()->onHorizonOf($user)->create(['title' => 'Move the largest research group off the out-of-date host', 'effort_days' => 8, 'elapsed_days' => 20]);
        WorkItem::factory()->onHorizonOf($user)->create(['title' => 'One CI setup in place of two', 'effort_days' => 5]);
        WorkItem::factory()->onHorizonOf($user)->create(['title' => 'Settle the spread of language versions']);

        WorkItem::factory()->onPlateOf($user)->create(['title' => 'Change the base image', 'effort_days' => 3, 'elapsed_days' => 10]);
        WorkItem::factory()->visibleToTeam()->onPlateOf($user)->create(['title' => 'Move container hosting to plain Compose behind the existing proxy', 'effort_days' => 10, 'elapsed_days' => 30, 'due_on' => now()->addMonth()]);
        WorkItem::factory()->lookingForAHand()->onPlateOf($user)->create(['title' => 'Every app on the current framework version', 'notes' => 'One job repeated across every app.', 'blurb' => "A good way to get to know **every app we run**.\n\n- Each one is small\n- You can do one in an afternoon", 'effort_days' => 20, 'elapsed_days' => 60, 'due_on' => now()->addMonths(3)]);
        WorkItem::factory()->secret()->onPlateOf($user)->create(['title' => 'Look into the odd logins on the old file server', 'effort_days' => 2]);
    }

    private function createSharedWorkItems(User $adminUser, User $standardUser): void
    {
        $certificates = WorkItem::factory()
            ->onPlateOf($adminUser)
            ->onHorizonOf($standardUser, addedBy: $adminUser, reason: 'You know how the DNS challenges are set up')
            ->create(['title' => 'Certificates for the new research cluster', 'effort_days' => 2, 'elapsed_days' => 10]);
        $certificates->addHistory($adminUser, 'created it');
        $certificates->addHistory($adminUser, 'added '.$standardUser->full_name);

        $disks = WorkItem::factory()
            ->onPlateOf($standardUser)
            ->onHorizonOf($adminUser, addedBy: $standardUser, reason: 'You hold the purchasing login')
            ->create(['title' => 'Order the replacement disks', 'effort_days' => 1]);
        $disks->addHistory($standardUser, 'created it');
        $disks->addHistory($standardUser, 'added '.$adminUser->full_name);
    }

    /**
     * @return Collection<int, User>
     */
    private function createColleaguesWithPlates(): Collection
    {
        return User::factory()->count(4)->create()->each(function (User $colleague) {
            WorkItem::factory()->count(3)->onPlateOf($colleague)->create();
        });
    }

    /**
     * The shape on the ground: a head, a manager of several teams, team leads, and the people in each team.
     * The regular user leads the developers and reports to the admin user.
     *
     * @param  Collection<int, User>  $colleagues
     */
    private function createTeams(User $manager, User $developmentLead, Collection $colleagues): void
    {
        [$firstDeveloper, $secondDeveloper, $infrastructureLead, $sysadmin] = $colleagues->all();
        $head = User::factory()->create(['job_title' => 'Head of IT']);

        $manager->update(['job_title' => 'Operations Manager', 'team_id' => Team::factory()->ledBy($head)->create(['name' => 'IT Support'])->id]);

        $teamLeads = Team::factory()->ledBy($manager)->create(['name' => 'Team leads']);
        $developmentLead->update(['job_title' => 'Development Lead', 'team_id' => $teamLeads->id]);
        $infrastructureLead->update(['job_title' => 'Infrastructure Lead', 'team_id' => $teamLeads->id, 'is_people_admin' => true]);

        $developers = Team::factory()->ledBy($developmentLead)->create(['name' => 'Developers']);
        $firstDeveloper->update(['job_title' => 'Developer', 'team_id' => $developers->id]);
        $secondDeveloper->update(['job_title' => 'Developer', 'team_id' => $developers->id]);

        $sysadmin->update(['job_title' => 'Systems Administrator', 'team_id' => Team::factory()->ledBy($infrastructureLead)->create(['name' => 'Infrastructure'])->id]);

        User::factory()->create(['job_title' => 'Developer', 'left_on' => now()->subMonths(2)]);

        $this->createWorkToPlanAround($manager, $developmentLead, $firstDeveloper, $secondDeveloper, $infrastructureLead, $sysadmin);
    }

    /**
     * Big pieces of work with rough dates, so that each lead's timeline has something on it,
     * beside the small undated things that fill a real plate.
     */
    private function createWorkToPlanAround(User $manager, User $developmentLead, User $firstDeveloper, User $secondDeveloper, User $infrastructureLead, User $sysadmin): void
    {
        $thisWeek = today()->startOfWeek();

        $this->bigWork($manager, onPlate: true, title: 'Budget and staffing plan for next year', startsInWeeks: 0, weeksLong: 5, effortDays: 8);
        $this->bigWork($manager, onPlate: false, title: 'Tender for the new storage platform', startsInWeeks: 8, weeksLong: 10, effortDays: 15, notes: 'Procurement rules mean nothing can be ordered before the tender closes.');
        $this->smallWork($manager, ['Sort out the on-call rota', 'Reply about the audit findings']);

        $this->bigWork($developmentLead, onPlate: true, title: 'Student records system: move to the new API', startsInWeeks: -2, weeksLong: 9, effortDays: 25, notes: 'The old API is switched off at the end of term. Everything that reads student data has to move before then.');
        $this->bigWork($developmentLead, onPlate: false, title: 'Rewrite the timetabling import', startsInWeeks: 10, weeksLong: 6, effortDays: 12);

        $this->bigWork($firstDeveloper, onPlate: true, title: 'Research data portal: first release', startsInWeeks: -1, weeksLong: 7, effortDays: 20, notes: 'The research office want it live before the grant review.');
        $this->bigWork($firstDeveloper, onPlate: false, title: 'Lab booking app: accessibility fixes', startsInWeeks: 7, weeksLong: 4, effortDays: 8);
        $this->bigWork($firstDeveloper, onPlate: false, title: 'Replace the room booking screens', startsInWeeks: 14, weeksLong: 8, effortDays: 18);
        $this->smallWork($firstDeveloper, ['Fix the broken export on the equipment list', 'Review the pull request for the feedback form', 'Renew the maps API key']);

        $this->bigWork($secondDeveloper, onPlate: true, title: 'Exam marks upload: second marker workflow', startsInWeeks: 0, weeksLong: 4, effortDays: 10);
        WorkItem::firstWhere('title', 'Research data portal: first release')->people()->attach($secondDeveloper, [
            'status' => PlateStatus::Upcoming,
            'added_by_id' => $firstDeveloper->id,
            'reason' => 'For the search page',
        ]);
        $this->bigWork($secondDeveloper, onPlate: false, title: 'Move the placements app to single sign-on', startsInWeeks: 9, weeksLong: 5, effortDays: 9);
        $this->smallWork($secondDeveloper, ['Chase the missing test accounts', 'Write up how the nightly import works']);

        $this->bigWork($infrastructureLead, onPlate: true, title: 'Windows 11 rollout to the teaching labs', startsInWeeks: -3, weeksLong: 12, effortDays: 30, notes: 'Lab by lab, in the gaps between classes. The imaging server is the bottleneck.');
        $this->bigWork($infrastructureLead, onPlate: false, title: 'Replace the print servers', startsInWeeks: 12, weeksLong: 4, effortDays: 6);
        $this->smallWork($infrastructureLead, ['Licence renewal for the CAD software', 'Find out who owns the old file share']);

        $this->bigWork($sysadmin, onPlate: true, title: 'Cluster storage: move from ZFS to Btrfs', startsInWeeks: 1, weeksLong: 6, effortDays: 12, notes: "About a day of work per storage node, then roughly 48 hours syncing each one. The cluster is read-only while a node syncs, so it has to be agreed with the research groups.\n\nNothing can start until the spare disks arrive.");
        $this->bigWork($sysadmin, onPlate: false, title: 'Patch every Linux server before the support cut-off', startsInWeeks: 5, weeksLong: 8, effortDays: 14);
        $this->bigWork($sysadmin, onPlate: false, title: 'New backup server', startsInWeeks: 15, weeksLong: 5, effortDays: 7);
        $this->smallWork($sysadmin, ['Update the samba servers', 'Renew the wildcard certificate', 'Clear the full log partition on the proxy', 'Add the new starters to the VPN group']);

        WorkItem::whereNotNull('starts_on')->where('starts_on', '<', $thisWeek)->get()->each(fn (WorkItem $workItem) => $workItem->addHistory($workItem->owner, 'created it'));
    }

    private function bigWork(User $person, bool $onPlate, string $title, int $startsInWeeks, int $weeksLong, int $effortDays, ?string $notes = null): void
    {
        $startsOn = today()->startOfWeek()->addWeeks($startsInWeeks);
        $factory = $onPlate ? WorkItem::factory()->onPlateOf($person) : WorkItem::factory()->onHorizonOf($person);

        $factory->create([
            'title' => $title,
            'notes' => $notes,
            'effort_days' => $effortDays,
            'starts_on' => $startsOn,
            'due_on' => $startsOn->copy()->addWeeks($weeksLong)->subDays(3),
        ]);
    }

    /**
     * @param  list<string>  $titles
     */
    private function smallWork(User $person, array $titles): void
    {
        foreach ($titles as $position => $title) {
            $factory = $position === 0 ? WorkItem::factory()->onHorizonOf($person) : WorkItem::factory()->onPlateOf($person);

            $factory->create(['title' => $title]);
        }
    }
}
