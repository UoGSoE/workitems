<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    /**
     * A department of about thirty people, with five of them to sign in as (the password is "secret"):
     * member2x does the work, line2x leads a small team, lead2x has leads reporting to them,
     * manager2x is at the top, and admin2x is a developer of this app.
     */
    public function run(): void
    {
        $manager = $this->someoneToSignInAs('manager2x', 'Jenny', 'McManager', 'Head of IT');
        $lead = $this->someoneToSignInAs('lead2x', 'Olivia', 'McLead', 'Infrastructure Manager');
        $line = $this->someoneToSignInAs('line2x', 'Jenny', 'McLine', 'Apps and Data Lead');
        $member = $this->someoneToSignInAs('member2x', 'Olivia', 'McMember', 'Developer');
        $admin = $this->someoneToSignInAs('admin2x', 'Jenny', 'MacAdmin', 'Developer', ['is_admin' => true]);

        $frontDeskLead = User::factory()->create(['job_title' => 'Front Desk Lead', 'is_people_admin' => true]);
        $complianceLead = User::factory()->create(['job_title' => 'Compliance and Continuity Lead']);
        $infrastructureTeamLead = User::factory()->create(['job_title' => 'Infrastructure Team Lead']);
        $developer = User::factory()->create(['job_title' => 'Developer']);

        $this->team('Section heads', $manager, new Collection([$lead, $frontDeskLead, $complianceLead]));
        $this->team('Infrastructure and apps leads', $lead, new Collection([$line, $infrastructureTeamLead]));
        $this->team('Apps and data', $line, new Collection([$member, $admin, $developer]));
        $infrastructure = $this->team('Infrastructure', $infrastructureTeamLead, User::factory()->count(10)->create(['job_title' => 'Systems Administrator']));
        $hpc = $this->team('HPC', $lead, User::factory()->count(2)->create(['job_title' => 'Research Computing Specialist']));
        $frontDesk = $this->team('Front desk', $frontDeskLead, User::factory()->count(5)->create(['job_title' => 'Support Analyst']));
        $compliance = $this->team('Compliance', $complianceLead, User::factory()->count(3)->create(['job_title' => 'Compliance Officer']));
        $businessContinuity = $this->team('Business continuity', $complianceLead, User::factory()->count(2)->create(['job_title' => 'Continuity Planner']));

        User::factory()->create(['job_title' => 'Developer', 'left_on' => now()->subMonths(2)]);

        $this->createPlateFor($member);
        $this->createSharedWorkItems($admin, $member);
        $this->createWorkForThePeopleToSignInAs($manager, $lead, $line, $member, $admin, $developer);
        $this->createWorkForTheOtherLeads($infrastructureTeamLead, $frontDeskLead, $complianceLead);

        $this->createWorkForTeam($infrastructure->members, $this->infrastructureWork(), $this->infrastructureSmallWork());
        $this->createWorkForTeam($hpc->members, $this->hpcWork(), $this->hpcSmallWork());
        $this->createWorkForTeam($frontDesk->members, $this->frontDeskWork(), $this->frontDeskSmallWork());
        $this->createWorkForTeam($compliance->members, $this->complianceWork(), $this->complianceSmallWork());
        $this->createWorkForTeam($businessContinuity->members, $this->businessContinuityWork(), $this->businessContinuitySmallWork());

        $this->createWorkForTheClassifieds($infrastructure->members->first(), $frontDesk->members->first());

        WorkItem::whereNotNull('starts_on')->where('starts_on', '<', today()->startOfWeek())->with('owner')->get()
            ->each(fn (WorkItem $workItem) => $workItem->addHistory($workItem->owner, 'created it'));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function someoneToSignInAs(string $username, string $forenames, string $surname, string $jobTitle, array $extra = []): User
    {
        return User::factory()->create([
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => false,
            'forenames' => $forenames,
            'surname' => $surname,
            'job_title' => $jobTitle,
            ...$extra,
        ]);
    }

    /**
     * @param  Collection<int, User>  $members
     */
    private function team(string $name, User $lead, Collection $members): Team
    {
        $team = Team::factory()->ledBy($lead)->create(['name' => $name]);

        $members->each->update(['team_id' => $team->id]);

        return $team->setRelation('members', $members);
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

    private function createSharedWorkItems(User $admin, User $member): void
    {
        $certificates = WorkItem::factory()
            ->onPlateOf($admin)
            ->onHorizonOf($member, addedBy: $admin, reason: 'You know how the DNS challenges are set up')
            ->create(['title' => 'Certificates for the new research cluster', 'effort_days' => 2, 'elapsed_days' => 10]);
        $certificates->addHistory($admin, 'created it');
        $certificates->addHistory($admin, 'added '.$member->full_name);

        $disks = WorkItem::factory()
            ->onPlateOf($member)
            ->onHorizonOf($admin, addedBy: $member, reason: 'You hold the purchasing login')
            ->create(['title' => 'Order the replacement disks', 'effort_days' => 1]);
        $disks->addHistory($member, 'created it');
        $disks->addHistory($member, 'added '.$admin->full_name);
    }

    /**
     * Big pieces of work with rough dates, so that each lead's timeline has something on it,
     * beside the small undated things that fill a real plate.
     */
    private function createWorkForThePeopleToSignInAs(User $manager, User $lead, User $line, User $member, User $admin, User $developer): void
    {
        $this->bigWork($manager, onPlate: true, title: 'IT plan for the next three years', startsInWeeks: -1, weeksLong: 10, effortDays: 10);
        $this->smallWork($manager, ['Reply about the audit findings', 'Agree the training budget']);

        $this->bigWork($lead, onPlate: true, title: 'Budget and staffing plan for next year', startsInWeeks: 0, weeksLong: 5, effortDays: 8);
        $this->bigWork($lead, onPlate: false, title: 'Tender for the new storage platform', startsInWeeks: 8, weeksLong: 10, effortDays: 15, notes: 'Procurement rules mean nothing can be ordered before the tender closes.');
        $this->smallWork($lead, ['Sort out the on-call rota', 'Reply about the storage quote']);

        $this->bigWork($line, onPlate: true, title: 'Student records system: move to the new API', startsInWeeks: -2, weeksLong: 9, effortDays: 25, notes: 'The old API is switched off at the end of term. Everything that reads student data has to move before then.');
        $this->bigWork($line, onPlate: false, title: 'Rewrite the timetabling import', startsInWeeks: 10, weeksLong: 6, effortDays: 12);
        $this->smallWork($line, ['Write the job advert for the new developer', 'Go through the list of apps nobody owns']);

        $this->bigWork($member, onPlate: true, title: 'Equipment loans: new booking form', startsInWeeks: -3, weeksLong: 6, effortDays: 12);
        $this->bigWork($member, onPlate: false, title: 'Lab booking app: accessibility fixes', startsInWeeks: 7, weeksLong: 4, effortDays: 8);
        $this->bigWork($member, onPlate: false, title: 'Replace the room booking screens', startsInWeeks: 14, weeksLong: 8, effortDays: 18);

        $this->bigWork($admin, onPlate: true, title: 'Exam marks upload: second marker workflow', startsInWeeks: 0, weeksLong: 4, effortDays: 10);
        $this->bigWork($admin, onPlate: false, title: 'Move the placements app to single sign-on', startsInWeeks: 9, weeksLong: 5, effortDays: 9);
        $this->smallWork($admin, ['Chase the missing test accounts', 'Write up how the nightly import works']);

        $this->bigWork($developer, onPlate: true, title: 'Research data portal: first release', startsInWeeks: -1, weeksLong: 7, effortDays: 20, notes: 'The research office want it live before the grant review.', helper: $admin, reason: 'For the search page');
        $this->bigWork($developer, onPlate: false, title: 'Feedback forms: move off the old survey tool', startsInWeeks: 6, weeksLong: 5, effortDays: 9);
        $this->smallWork($developer, ['Fix the broken export on the equipment list', 'Review the pull request for the feedback form', 'Renew the maps API key']);
    }

    private function createWorkForTheOtherLeads(User $infrastructureTeamLead, User $frontDeskLead, User $complianceLead): void
    {
        $this->bigWork($infrastructureTeamLead, onPlate: true, title: 'Windows 11 rollout to the teaching labs', startsInWeeks: -3, weeksLong: 12, effortDays: 30, notes: 'Lab by lab, in the gaps between classes. The imaging server is the bottleneck.');
        $this->bigWork($infrastructureTeamLead, onPlate: false, title: 'Replace the print servers', startsInWeeks: 12, weeksLong: 4, effortDays: 6);
        $this->smallWork($infrastructureTeamLead, ['Licence renewal for the CAD software', 'Find out who owns the old file share']);

        $this->bigWork($frontDeskLead, onPlate: true, title: 'New help desk system: choose one and try it out', startsInWeeks: -2, weeksLong: 10, effortDays: 15);
        $this->bigWork($frontDeskLead, onPlate: false, title: 'Rewrite the guide for new starters', startsInWeeks: 9, weeksLong: 4, effortDays: 5);
        $this->smallWork($frontDeskLead, ['Cover for the two weeks of exams', 'Order more loan laptops']);

        $this->bigWork($complianceLead, onPlate: true, title: 'Yearly information security audit', startsInWeeks: -4, weeksLong: 9, effortDays: 20, notes: 'The auditors are on site in the last week.');
        $this->bigWork($complianceLead, onPlate: false, title: 'Rehearse recovering from the loss of the main server room', startsInWeeks: 11, weeksLong: 3, effortDays: 6);
        $this->smallWork($complianceLead, ['Reply to the data protection request', 'Book the fire safety walk-round']);
    }

    /**
     * Give a team its work. Each person owns one big piece and is down to help with the next
     * person's, and has a couple of small undated things of their own.
     *
     * @param  Collection<int, User>  $members
     * @param  list<array{string, int, int, int}>  $bigWork  title, weeks until it starts, weeks long, days of effort
     * @param  list<string>  $smallWork
     */
    private function createWorkForTeam(Collection $members, array $bigWork, array $smallWork): void
    {
        $members->values()->each(function (User $person, int $position) use ($members, $bigWork, $smallWork) {
            [$title, $startsInWeeks, $weeksLong, $effortDays] = $bigWork[$position];
            $helper = $members->values()[($position + 1) % $members->count()];

            $this->bigWork($person, onPlate: true, title: $title, startsInWeeks: $startsInWeeks, weeksLong: $weeksLong, effortDays: $effortDays, helper: $helper);
            $this->smallWork($person, array_slice($smallWork, $position * 2, 2));
        });
    }

    /**
     * Work from more than one team that wants a hand, so that the classifieds page has something on it.
     */
    private function createWorkForTheClassifieds(User $sysadmin, User $supportAnalyst): void
    {
        WorkItem::factory()->lookingForAHand()->onPlateOf($sysadmin)->create([
            'title' => 'Monitoring: move the old checks to the new system',
            'blurb' => "About sixty small checks, each one a few lines.\n\nA good way to **learn what every server is for**.",
            'effort_days' => 10,
            'due_on' => now()->addMonths(2),
        ]);
        WorkItem::factory()->lookingForAHand()->onHorizonOf($supportAnalyst)->create([
            'title' => 'Short how-to videos for the lecture theatre kit',
            'blurb' => 'Would suit anyone who likes **explaining things**. No editing skills needed.',
            'effort_days' => 6,
        ]);
        WorkItem::factory()->parked()->onPlateOf($sysadmin)->create(['title' => 'Try the new hypervisor on the spare hosts', 'effort_days' => 5]);
    }

    private function bigWork(User $person, bool $onPlate, string $title, int $startsInWeeks, int $weeksLong, int $effortDays, ?string $notes = null, ?User $helper = null, ?string $reason = null): void
    {
        $startsOn = today()->startOfWeek()->addWeeks($startsInWeeks);
        $factory = $onPlate ? WorkItem::factory()->onPlateOf($person) : WorkItem::factory()->onHorizonOf($person);

        if ($helper) {
            $factory = $factory->onHorizonOf($helper, addedBy: $person, reason: $reason);
        }

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

    /**
     * Two of these are past their by-when date, as real work sometimes is.
     *
     * @return list<array{string, int, int, int}>
     */
    private function infrastructureWork(): array
    {
        return [
            ['Cluster storage: move from ZFS to Btrfs', 1, 6, 12],
            ['Patch every Linux server before the support cut-off', -8, 6, 14],
            ['New backup server', 15, 5, 7],
            ['Move the mail relay off the old host', -7, 4, 6],
            ['Replace the core switches in the main server room', 3, 8, 16],
            ['Wireless survey of the teaching block', 0, 4, 5],
            ['Move the file shares to the new storage', 6, 10, 20],
            ['Renew the virtualisation licences and tidy the hosts', -2, 5, 6],
            ['Single sign-on for the lab machines', 9, 7, 12],
            ['Replace the door access controller', 4, 3, 4],
        ];
    }

    /**
     * @return list<string>
     */
    private function infrastructureSmallWork(): array
    {
        return [
            'Update the samba servers', 'Renew the wildcard certificate',
            'Clear the full log partition on the proxy', 'Add the new starters to the VPN group',
            'Swap the failed disk in the backup array', 'Label the cables in rack four',
            'Check the UPS batteries', 'Remove the leavers from the admin groups',
            'Update the firmware on the edge switches', 'Find the owner of the unlabelled server',
            'Tidy the DNS records for retired hosts', 'Test restoring a file from last month',
            'Raise the quota for the imaging group', 'Rotate the service account passwords',
            'Fix the time drift on the lab machines', 'Order spare power supplies',
            'Write up the firewall change process', 'Move the licence server to a new VM',
            'Chase the supplier about the late delivery', 'Archive the old project shares',
        ];
    }

    /**
     * One of these is past its by-when date.
     *
     * @return list<array{string, int, int, int}>
     */
    private function hpcWork(): array
    {
        return [
            ['Upgrade the scheduler on the research cluster', -9, 6, 10],
            ['Add the new GPU nodes to the cluster', 2, 6, 9],
        ];
    }

    /**
     * @return list<string>
     */
    private function hpcSmallWork(): array
    {
        return [
            'Rebuild the module for the chemistry package', 'Answer the queue time complaint',
            'Clear out scratch space over ninety days old', 'Write the getting started page for new users',
        ];
    }

    /**
     * @return list<array{string, int, int, int}>
     */
    private function frontDeskWork(): array
    {
        return [
            ['Loan laptops: rebuild the whole pool', 0, 5, 8],
            ['Lecture theatre kit: check every room before term', 2, 4, 6],
            ['Move the printing credit system to the new supplier', 5, 6, 9],
            ['New starter accounts for the autumn intake', 1, 3, 5],
            ['Replace the front desk ticket screens', 8, 4, 4],
        ];
    }

    /**
     * @return list<string>
     */
    private function frontDeskSmallWork(): array
    {
        return [
            'Reset the passwords for the visiting researchers', 'Replace the broken projector remote',
            'Update the opening hours on the website', 'Collect the laptops from the leavers',
            'Restock the adapter drawer', 'Fix the label printer',
            'Reply to the complaint about the queue', 'Show the new starter the phone system',
            'Check the meeting room cameras', 'Send the reminder about the software survey',
        ];
    }

    /**
     * One of these is past its by-when date.
     *
     * @return list<array{string, int, int, int}>
     */
    private function complianceWork(): array
    {
        return [
            ['Review who has access to the finance systems', -6, 4, 6],
            ['Software licence count for the yearly return', 1, 5, 8],
            ['Update the acceptable use policy', 4, 6, 5],
        ];
    }

    /**
     * @return list<string>
     */
    private function complianceSmallWork(): array
    {
        return [
            'Log the lost laptop', 'Check the supplier security form',
            'Remind people about the phishing training', 'File the minutes of the risk meeting',
            'Update the register of data holdings', 'Review the new cloud service request',
        ];
    }

    /**
     * @return list<array{string, int, int, int}>
     */
    private function businessContinuityWork(): array
    {
        return [
            ['Rewrite the plan for losing the main server room', 0, 8, 10],
            ['Test the phone tree for out-of-hours incidents', 3, 3, 3],
        ];
    }

    /**
     * @return list<string>
     */
    private function businessContinuitySmallWork(): array
    {
        return [
            'Update the emergency contact list', 'Check the off-site backups arrive',
            'Book the room for the tabletop exercise', 'Chase the missing recovery plans',
        ];
    }
}
