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
            'owner_id' => null,
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

    public function ownedBy(User $user): static
    {
        return $this->state(['owner_id' => $user->id]);
    }

    public function onPlateOf(User $user): static
    {
        return $this->heldBy($user, PlateStatus::InProgress, addedBy: $user);
    }

    public function onHorizonOf(User $user, ?User $addedBy = null, ?string $reason = null): static
    {
        return $this->heldBy($user, PlateStatus::Upcoming, $addedBy ?? $user, $reason);
    }

    /**
     * The first person put on a work item owns it, unless ownedBy() has said otherwise.
     */
    private function heldBy(User $user, PlateStatus $status, User $addedBy, ?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => ['owner_id' => $attributes['owner_id'] ?? $user->id])->hasAttached($user, [
            'status' => $status,
            'added_by_id' => $addedBy->id,
            'reason' => $reason,
        ], 'people');
    }
}
