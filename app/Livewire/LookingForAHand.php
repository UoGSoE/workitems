<?php

namespace App\Livewire;

use App\Enums\WorkItemStatus;
use App\Enums\WorkItemVisibility;
use App\Models\WorkItem;
use Livewire\Component;

class LookingForAHand extends Component
{
    public function render()
    {
        $openWorkICanSee = WorkItem::visibleTo(auth()->user())
            ->where('status', WorkItemStatus::Open)
            ->with('owner', 'people')
            ->latest('id')
            ->get();

        $peopleWhoCountMeInTheirTeam = auth()->user()->peopleWhoCountMeInTheirTeam();

        return view('livewire.looking-for-a-hand', [
            'lookingForAHand' => $openWorkICanSee->filter(fn (WorkItem $workItem) => $workItem->visibility === WorkItemVisibility::LookingForAHand),
            'myTeamsWork' => $openWorkICanSee
                ->filter(fn (WorkItem $workItem) => $workItem->visibility === WorkItemVisibility::Team)
                ->filter(fn (WorkItem $workItem) => $peopleWhoCountMeInTheirTeam->contains($workItem->owner))
                ->reject(fn (WorkItem $workItem) => $workItem->people->contains(auth()->user())),
        ]);
    }
}
