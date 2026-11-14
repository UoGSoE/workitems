<?php

namespace App\Livewire;

use App\Enums\PlateSection;
use App\Enums\PlateStatus;
use App\Enums\WorkItemStatus;
use App\Events\WorkItemUpdated;
use App\Models\User;
use App\Models\WorkItem;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MyPlate extends Component
{
    public $whatsOn = '';

    #[Locked]
    public $editingId = null;

    public $title = '';

    public $notes = '';

    public $effortDays = '';

    public $elapsedDays = '';

    public $dueOn = '';

    #[Locked]
    public $addingToId = null;

    public $personId = '';

    public $reason = '';

    #[Locked]
    public $takingOffFromId = null;

    #[Locked]
    public $takingOffPersonId = null;

    public $message = '';

    #[Locked]
    public $pickedId = null;

    public function render()
    {
        $workItems = auth()->user()->workItemsWithEveryoneInvolved();
        $takingOffFrom = $workItems->find($this->takingOffFromId);
        $personToTakeOff = $takingOffFrom?->people->find($this->takingOffPersonId);

        return view('livewire.my-plate', [
            'takingOffFrom' => $takingOffFrom,
            'personToTakeOff' => $personToTakeOff,
            'whoGetsTold' => $personToTakeOff ? $takingOffFrom->whoHearsAboutTakingOff($personToTakeOff, auth()->user()) : null,
            'workItems' => $workItems,
            'picked' => $workItems->find($this->pickedId),
            'sections' => collect(PlateSection::cases()),
            'done' => PlateSection::Done,
            'history' => $workItems->find($this->editingId)?->history()->with('user')->oldest()->get() ?? collect(),
            'peopleToAdd' => $workItems->find($this->addingToId)?->peopleWhoCouldBeAdded()->get() ?? collect(),
            'workItemsBySection' => $workItems->groupBy(fn (WorkItem $workItem) => $workItem->section()->value),
        ]);
    }

    public function add(): void
    {
        $this->validate(['whatsOn' => 'required|string|max:255']);

        $workItem = auth()->user()->workItems()->create(
            ['owner_id' => auth()->id(), 'title' => $this->whatsOn, 'status' => WorkItemStatus::Open],
            ['status' => PlateStatus::InProgress, 'added_by_id' => auth()->id()],
        );

        event(new WorkItemUpdated($workItem, auth()->user(), 'created it'));

        $this->reset('whatsOn');
    }

    public function moveTo(int $workItemId, string $section): void
    {
        $this->myWorkItem($workItemId)->moveTo(PlateSection::from($section), auth()->user());
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

        $workItem = $this->myWorkItem($this->editingId);

        $workItem->update([
            'title' => $this->title,
            'notes' => $this->notes ?: null,
            'effort_days' => $this->effortDays ?: null,
            'elapsed_days' => $this->elapsedDays ?: null,
            'due_on' => $this->dueOn ?: null,
        ]);

        if ($workItem->wasChanged()) {
            event(new WorkItemUpdated($workItem, auth()->user(), 'changed the details'));
        }

        Flux::modal('edit-work-item')->close();
    }

    public function choosePersonFor(int $workItemId): void
    {
        $this->addingToId = $this->myWorkItem($workItemId)->id;
        $this->reset('personId', 'reason');

        Flux::modal('add-person')->show();
    }

    public function addPerson(): void
    {
        $workItem = $this->myWorkItem($this->addingToId);

        $this->validate([
            'personId' => ['required', Rule::in($workItem->peopleWhoCouldBeAdded()->pluck('id'))],
            'reason' => 'nullable|string|max:255',
        ]);

        $workItem->addPerson(User::find($this->personId), addedBy: auth()->user(), reason: $this->reason ?: null);

        Flux::modal('add-person')->close();
    }

    public function chooseToTakeOff(int $workItemId, int $personId): void
    {
        $workItem = $this->myWorkItem($workItemId);

        abort_unless($workItem->allowsTakingOff($workItem->people()->findOrFail($personId), auth()->user()), 403);

        $this->takingOffFromId = $workItem->id;
        $this->takingOffPersonId = $personId;
        $this->reset('message');

        Flux::modal('take-off')->show();
    }

    public function takeOff(): void
    {
        $this->validate(['message' => 'nullable|string|max:1000']);

        $workItem = $this->myWorkItem($this->takingOffFromId);
        $person = $workItem->people()->findOrFail($this->takingOffPersonId);

        abort_unless($workItem->allowsTakingOff($person, auth()->user()), 403);

        $workItem->takeOff($person, takenOffBy: auth()->user(), message: $this->message ?: null);

        Flux::modal('take-off')->close();
    }

    public function takeOver(int $workItemId): void
    {
        $workItem = $this->myWorkItem($workItemId);

        abort_if($workItem->owner->is(auth()->user()), 403);

        $workItem->takeOver(auth()->user());
    }

    public function pick(int $workItemId): void
    {
        $this->pickedId = $this->myWorkItem($workItemId)->id;
    }

    public function unpick(): void
    {
        $this->reset('pickedId');
    }

    private function myWorkItem(int $workItemId): WorkItem
    {
        return auth()->user()->workItems()->findOrFail($workItemId);
    }
}
