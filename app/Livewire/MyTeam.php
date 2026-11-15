<?php

namespace App\Livewire;

use App\Enums\PlateStatus;
use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MyTeam extends Component
{
    /**
     * How far ahead the timeline looks. It is drawn in weeks, starting with this one.
     */
    private const WEEKS = 26;

    #[Locked]
    public $showingId = null;

    public $draftTitle = '';

    public $draftStartsOn = '';

    public $draftDueOn = '';

    public $draftPersonIds = [];

    #[Locked]
    public $tryingDraft = false;

    public function render()
    {
        $thisWeek = today()->startOfWeek();
        $showing = $this->showingId ? $this->workOfMyPeople()->with('people', 'owner')->find($this->showingId) : null;

        return view('livewire.my-team', [
            'showing' => $showing,
            'history' => $showing?->history()->with('user')->oldest()->get() ?? collect(),
            'weeks' => self::WEEKS,
            'months' => $this->monthsAcross($thisWeek),
            'plans' => auth()->user()->reports()->map(fn (User $person) => $this->planFor($person, $thisWeek)),
        ]);
    }

    public function boot(): void
    {
        abort_if(auth()->user()->reports()->isEmpty(), 403);
    }

    /**
     * A draft cannot be due before it starts, so picking a start moves by-when to the day after unless it already fits.
     */
    public function updatedDraftStartsOn($startsOn): void
    {
        if (blank($startsOn) || (filled($this->draftDueOn) && $this->draftDueOn >= $startsOn)) {
            return;
        }

        $this->draftDueOn = Carbon::parse($startsOn)->addDay()->toDateString();
    }

    public function show(int $workItemId): void
    {
        $this->showingId = $this->workOfMyPeople()->findOrFail($workItemId)->id;

        Flux::modal('work-item')->show();
    }

    /**
     * Draw a piece of work that does not exist yet beside the real work of the chosen people. Nothing is saved.
     */
    public function tryDraft(): void
    {
        $this->tryingDraft = false;

        $this->validate([
            'draftTitle' => 'required|string|max:255',
            'draftStartsOn' => 'required|date',
            'draftDueOn' => 'required|date|after_or_equal:draftStartsOn',
            'draftPersonIds' => 'required|array',
            'draftPersonIds.*' => Rule::in(auth()->user()->reports()->modelKeys()),
        ]);

        $this->tryingDraft = true;
    }

    public function clearDraft(): void
    {
        $this->reset('draftTitle', 'draftStartsOn', 'draftDueOn', 'draftPersonIds', 'tryingDraft');
    }

    /**
     * The work items that someone who reports to me is on. A lead may see all of these.
     */
    private function workOfMyPeople(): Builder
    {
        $myPeople = auth()->user()->reports()->modelKeys();

        return WorkItem::visibleTo(auth()->user())->whereHas('people', fn (Builder $people) => $people->whereKey($myPeople));
    }

    /**
     * What one person is carrying: the work with ballpark dates as bars for the timeline, and the rest grouped by where it sits.
     *
     * @return array{person: User, bars: Collection, draft: array|null, otherWork: Collection<string, Collection<int, WorkItem>>}
     */
    private function planFor(User $person, Carbon $thisWeek): array
    {
        $openWork = $person->workItems()
            ->visibleTo(auth()->user())
            ->where('work_items.status', WorkItemStatus::Open)
            ->with('people', 'owner')
            ->orderBy('starts_on')
            ->orderBy('work_items.id')
            ->get();

        return [
            'person' => $person,
            'bars' => $openWork->filter->hasBallparkDates()->map(fn (WorkItem $workItem) => [
                'workItem' => $workItem,
                'from' => $this->weekOf($workItem->starts_on, $thisWeek),
                'to' => $this->weekOf($workItem->due_on, $thisWeek) + 1,
                'where' => $this->whereItSits($workItem),
            ]),
            'draft' => $this->draftFor($person, $openWork, $thisWeek),
            'otherWork' => $openWork->reject->hasBallparkDates()->groupBy(fn (WorkItem $workItem) => ucfirst($this->whereItSits($workItem)))->sortKeysDesc(),
        ];
    }

    /**
     * Where the draft being tried out would sit on this person's timeline, and which of their dated work it would run alongside.
     *
     * @param  Collection<int, WorkItem>  $openWork
     * @return array{from: int, to: int, startsOn: Carbon, dueOn: Carbon, overlaps: Collection}|null
     */
    private function draftFor(User $person, Collection $openWork, Carbon $thisWeek): ?array
    {
        if (! $this->tryingDraft || ! in_array($person->id, $this->draftPersonIds)) {
            return null;
        }

        $startsOn = Carbon::parse($this->draftStartsOn);
        $dueOn = Carbon::parse($this->draftDueOn);

        return [
            'from' => $this->weekOf($startsOn, $thisWeek),
            'to' => $this->weekOf($dueOn, $thisWeek) + 1,
            'startsOn' => $startsOn,
            'dueOn' => $dueOn,
            'overlaps' => $openWork->filter->hasBallparkDates()
                ->filter(fn (WorkItem $workItem) => $workItem->starts_on <= $dueOn && $workItem->due_on >= $startsOn)
                ->pluck('title'),
        ];
    }

    private function whereItSits(WorkItem $workItem): string
    {
        return $workItem->pivot->status === PlateStatus::InProgress ? 'on their plate' : 'on their horizon';
    }

    /**
     * Which week of the timeline a date falls in, counting this week as 1. Anything
     * earlier sits in the first week and anything later than the timeline in the last.
     */
    private function weekOf(Carbon $date, Carbon $thisWeek): int
    {
        $week = intdiv((int) $thisWeek->diffInDays($date->copy()->startOfWeek()), 7) + 1;

        return max(1, min(self::WEEKS, $week));
    }

    /**
     * The month names to print across the top, each with the week it starts in and how many weeks it covers.
     *
     * @return Collection<int, array{name: string, from: int, span: int}>
     */
    private function monthsAcross(Carbon $thisWeek): Collection
    {
        return collect(range(1, self::WEEKS))
            ->groupBy(fn (int $week) => $thisWeek->copy()->addWeeks($week - 1)->format('M Y'))
            ->map(fn (Collection $weeks, string $name) => ['name' => $name, 'from' => $weeks->first(), 'span' => $weeks->count()])
            ->values();
    }
}
