<?php

namespace Database\Seeders;

use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        [$adminUser, $standardUser] = $this->createUsers();

        $this->createPlateFor($standardUser);
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
        $workItems = [
            ['title' => 'Renew the wildcard certificate', 'status' => WorkItemStatus::Done, 'effort_days' => 1],
            ['title' => 'Tidy up the old wiki pages', 'status' => WorkItemStatus::Parked],
            ['title' => 'New server for the registry storage', 'status' => WorkItemStatus::Upcoming, 'notes' => 'Does not exist yet. The registry storage work waits on it.'],
            ['title' => 'Replace the storage behind the container registry', 'status' => WorkItemStatus::Upcoming, 'effort_days' => 1, 'elapsed_days' => 3],
            ['title' => 'Move the VM-hosted apps into Compose', 'status' => WorkItemStatus::Upcoming, 'effort_days' => 10, 'elapsed_days' => 30],
            ['title' => 'Move the largest research group off the out-of-date host', 'status' => WorkItemStatus::Upcoming, 'effort_days' => 8, 'elapsed_days' => 20],
            ['title' => 'One CI setup in place of two', 'status' => WorkItemStatus::Upcoming, 'effort_days' => 5],
            ['title' => 'Settle the spread of language versions', 'status' => WorkItemStatus::Upcoming],
            ['title' => 'Change the base image', 'status' => WorkItemStatus::InProgress, 'effort_days' => 3, 'elapsed_days' => 10],
            ['title' => 'Move container hosting to plain Compose behind the existing proxy', 'status' => WorkItemStatus::InProgress, 'effort_days' => 10, 'elapsed_days' => 30, 'due_on' => now()->addMonth()],
            ['title' => 'Every app on the current framework version', 'status' => WorkItemStatus::InProgress, 'effort_days' => 20, 'elapsed_days' => 60, 'due_on' => now()->addMonths(3), 'notes' => 'One job repeated across every app.'],
        ];

        foreach ($workItems as $workItem) {
            $user->workItems()->create($workItem);
        }
    }

    private function createColleaguesWithPlates(): void
    {
        User::factory()->count(3)->create()->each(function (User $colleague) {
            WorkItem::factory()->count(3)->heldBy($colleague)->create();
        });
    }
}
