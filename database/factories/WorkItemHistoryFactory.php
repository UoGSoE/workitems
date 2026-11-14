<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkItem;
use App\Models\WorkItemHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkItemHistory>
 */
class WorkItemHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_item_id' => WorkItem::factory(),
            'user_id' => User::factory(),
            'description' => 'created it',
        ];
    }
}
