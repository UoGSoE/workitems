<?php

namespace Database\Factories;

use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkItem>
 */
class WorkItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'status' => WorkItemStatus::InProgress,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(['status' => WorkItemStatus::Upcoming]);
    }

    public function parked(): static
    {
        return $this->state(['status' => WorkItemStatus::Parked]);
    }

    public function done(): static
    {
        return $this->state(['status' => WorkItemStatus::Done]);
    }

    public function heldBy(User $user): static
    {
        return $this->hasAttached($user, [], 'people');
    }
}
