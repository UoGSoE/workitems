<?php

namespace Database\Factories;

use App\Enums\PlateStatus;
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
            'status' => WorkItemStatus::Open,
        ];
    }

    public function parked(): static
    {
        return $this->state(['status' => WorkItemStatus::Parked]);
    }

    public function done(): static
    {
        return $this->state(['status' => WorkItemStatus::Done]);
    }

    public function onPlateOf(User $user): static
    {
        return $this->heldBy($user, PlateStatus::InProgress);
    }

    public function onHorizonOf(User $user): static
    {
        return $this->heldBy($user, PlateStatus::Upcoming);
    }

    private function heldBy(User $user, PlateStatus $status): static
    {
        return $this->hasAttached($user, ['status' => $status, 'added_by_id' => $user->id], 'people');
    }
}
