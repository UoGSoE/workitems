<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        [$adminUser, $standardUser] = $this->createUsers();

        $this->createPlateFor($standardUser);
        $this->createSharedWorkItems($adminUser, $standardUser);
        $this->createColleaguesWithPlates();
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
        WorkItem::factory()->onPlateOf($user)->create(['title' => 'Move container hosting to plain Compose behind the existing proxy', 'effort_days' => 10, 'elapsed_days' => 30, 'due_on' => now()->addMonth()]);
        WorkItem::factory()->onPlateOf($user)->create(['title' => 'Every app on the current framework version', 'notes' => 'One job repeated across every app.', 'effort_days' => 20, 'elapsed_days' => 60, 'due_on' => now()->addMonths(3)]);
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

    private function createColleaguesWithPlates(): void
    {
        User::factory()->count(3)->create()->each(function (User $colleague) {
            WorkItem::factory()->count(3)->onPlateOf($colleague)->create();
        });
    }
}
