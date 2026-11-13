<?php

namespace App\Livewire;

use App\Enums\WorkItemStatus;
use App\Models\WorkItem;
use Flux\Flux;
use Livewire\Component;

class MyPlate extends Component
{
    public $whatsOn = '';

    public $editingId = null;

    public $title = '';

    public $notes = '';

    public $effortDays = '';

    public $elapsedDays = '';

    public $dueOn = '';

    public function render()
    {
        return view('livewire.my-plate', [
            'statuses' => WorkItemStatus::cases(),
            'done' => WorkItemStatus::Done,
            'workItemsByStatus' => auth()->user()->workItems()->get()->sortByDesc('id')->groupBy('status'),
        ]);
    }

    public function add(): void
    {
        $this->validate(['whatsOn' => 'required|string|max:255']);

        auth()->user()->workItems()->create([
            'title' => $this->whatsOn,
            'status' => WorkItemStatus::InProgress,
        ]);

        $this->reset('whatsOn');
    }

    public function moveTo(int $workItemId, string $status): void
    {
        $this->myWorkItem($workItemId)->update(['status' => WorkItemStatus::from($status)]);
    }

    public function edit(int $workItemId): void
    {
        $workItem = $this->myWorkItem($workItemId);

        $this->editingId = $workItem->id;
        $this->title = $workItem->title;
        $this->notes = $workItem->notes;
        $this->effortDays = $workItem->effort_days;
        $this->elapsedDays = $workItem->elapsed_days;
        $this->dueOn = $workItem->due_on?->toDateString();

        Flux::modal('edit-work-item')->show();
    }

    public function save(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'effortDays' => 'nullable|integer|min:1|max:999',
            'elapsedDays' => 'nullable|integer|min:1|max:999',
            'dueOn' => 'nullable|date',
        ]);

        $this->myWorkItem($this->editingId)->update([
            'title' => $this->title,
            'notes' => $this->notes ?: null,
            'effort_days' => $this->effortDays ?: null,
            'elapsed_days' => $this->elapsedDays ?: null,
            'due_on' => $this->dueOn ?: null,
        ]);

        Flux::modal('edit-work-item')->close();
    }

    private function myWorkItem(int $workItemId): WorkItem
    {
        return auth()->user()->workItems()->findOrFail($workItemId);
    }
}
