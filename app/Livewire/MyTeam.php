<?php

namespace App\Livewire;

use App\Enums\PlateStatus;
use App\Enums\WorkItemStatus;
use App\Models\User;
use App\Models\WorkItem;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
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

    #[Locked]
    public $showingForId = null;

    public $moveToId = '';

    public $moveAllTo = [];

    #[Locked]
    public $movingAllFromId = null;

    public $draftTitle = '';

    public $draftStartsOn = '';

    public $draftDueOn = '';

    public $draftPersonIds = [];

    public $draftOwnerId = '';

    #[Locked]
    public $tryingDraft = false;

    public function mount(): void
    {
        $this->nobodyIsChosenToMoveEverythingTo();
    }

    public function render()
    {
        $thisWeek = today()->startOfWeek();
        $showing = $this->showingId ? $this->workOfMyPeople()->with('people', 'owner')->find($this->showingId) : null;
        $showingFor = $showing?->people->find($this->showingForId);

        $plans = $this->peopleToPlanFor()->map(fn (User $person) => $this->planFor($person, $thisWeek));

        $movingAllFrom = $plans->first(fn (array $plan) => $plan['person']->id === $this->movingAllFromId);
        $movingAllTo = $movingAllFrom ? $this->peopleICanDraftFor()->find($this->moveAllTo[$this->movingAllFromId]) : null;

        return view('livewire.my-team', [
            'movingAllFrom' => $movingAllFrom,
            'movingAllTo' => $movingAllTo,
            'secretWarning' => $movingAllFrom ? $this->warningAboutSecretWork($movingAllFrom['secretCount'], $movingAllTo) : null,
            'showing' => $showing,
            'showingFor' => $showingFor,
            'canMoveTo' => $showingFor && auth()->user()->can('movePlace', [$showing, $showingFor]) ? $this->peopleICanDraftFor()->except($showingFor->id) : collect(),
            'history' => $showing?->history()->with('user')->oldest()->get() ?? collect(),
            'weeks' => self::WEEKS,
            'months' => $this->monthsAcross($thisWeek),
            'canDraftFor' => $this->peopleICanDraftFor(),
            'plans' => $plans,
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

    /**
     * Show a work item as it sits with one of my people, which is whose place can then be moved.
     */
    public function show(int $workItemId, int $personId): void
    {
        $workItem = $this->workOfMyPeople()->findOrFail($workItemId);

        $this->showingId = $workItem->id;
        $this->showingForId = $workItem->people()->whereKey($this->peopleICanDraftFor()->modelKeys())->findOrFail($personId)->id;
        $this->reset('moveToId');

        Flux::modal('work-item')->show();
    }

    /**
     * Give the place of the person the work item is being shown for to someone else.
     */
    public function move(): void
    {
        $workItem = $this->workOfMyPeople()->with('people', 'owner')->findOrFail($this->showingId);
        $from = $workItem->people->findOrFail($this->showingForId);

        $this->authorize('movePlace', [$workItem, $from]);

        $this->validate(['moveToId' => ['required', Rule::in($this->peopleICanDraftFor()->except($from->id)->modelKeys())]], attributes: ['moveToId' => 'person']);

        $workItem->movePlace($from, $this->peopleICanDraftFor()->find($this->moveToId), by: auth()->user());

        $this->reset('showingId', 'showingForId', 'moveToId');

        Flux::modal('work-item')->close();
        Flux::toast('Moved.', variant: 'success');
    }

    /**
     * Check before giving everything open that one of my people is on to someone else. Who it goes to is kept
     * per person, so that a choice made in one person's drawer is never waiting in another's.
     */
    public function askToMoveAll(int $personId): void
    {
        $from = auth()->user()->reports()->findOrFail($personId);

        $this->validateWhoGetsEverythingFrom($from);

        $this->movingAllFromId = $from->id;

        Flux::modal('confirm-move-all')->show();
    }

    public function moveAll(): void
    {
        $from = auth()->user()->reports()->findOrFail($this->movingAllFromId);

        $this->validateWhoGetsEverythingFrom($from);

        $from->moveOpenWorkTo($this->peopleICanDraftFor()->find($this->moveAllTo[$from->id]), by: auth()->user());

        $this->reset('movingAllFromId');
        $this->nobodyIsChosenToMoveEverythingTo();

        Flux::modal('confirm-move-all')->close();
        Flux::modal('work-of-'.$from->id)->close();
        Flux::toast('Moved.', variant: 'success');
    }

    /**
     * Draw a piece of work that does not exist yet beside the real work of the chosen people. Nothing is saved.
     */
    public function tryDraft(): void
    {
        $this->tryingDraft = false;

        $this->validateDraft();

        $this->tryingDraft = true;
    }

    /**
     * Turn the draft into a real work item on the horizon of the chosen people.
     */
    public function addDraft(): void
    {
        $this->validateDraft();

        if (count($this->draftPersonIds) > 1) {
            $this->reset('draftOwnerId');

            Flux::modal('draft-owner')->show();

            return;
        }

        $this->turnDraftIntoWorkOwnedBy($this->draftPersonIds[0]);
    }

    /**
     * A draft for several people needs one of them picked as its owner before it becomes real.
     */
    public function addDraftWithOwner(): void
    {
        $this->validateDraft();
        $this->validate(['draftOwnerId' => ['required', Rule::in($this->draftPersonIds)]], attributes: ['draftOwnerId' => 'owner']);

        $this->turnDraftIntoWorkOwnedBy($this->draftOwnerId);

        Flux::modal('draft-owner')->close();
    }

    public function clearDraft(): void
    {
        $this->reset('draftTitle', 'draftStartsOn', 'draftDueOn', 'draftPersonIds', 'draftOwnerId', 'tryingDraft');
    }

    /**
     * Each person's "move it all to" starts as an empty choice. Left unset, the browser shows the
     * first name in the list as if it had been picked.
     */
    private function nobodyIsChosenToMoveEverythingTo(): void
    {
        $this->moveAllTo = array_fill_keys(auth()->user()->reports()->modelKeys(), '');
    }

    /**
     * Moving secret work lets someone new see it. A lead already sees the secret work of their own people.
     */
    private function warningAboutSecretWork(int $howMuchIsSecret, User $to): ?string
    {
        if (! $howMuchIsSecret || $to->is(auth()->user())) {
            return null;
        }

        return $howMuchIsSecret === 1
            ? '1 of these is secret. '.$to->full_name.' will be able to see it.'
            : $howMuchIsSecret.' of these are secret. '.$to->full_name.' will be able to see them.';
    }

    private function validateWhoGetsEverythingFrom(User $from): void
    {
        $this->validate(
            ['moveAllTo.'.$from->id => ['required', Rule::in($this->peopleICanDraftFor()->except($from->id)->modelKeys())]],
            attributes: ['moveAllTo.'.$from->id => 'person'],
        );
    }

    private function validateDraft(): void
    {
        $this->validate([
            'draftTitle' => 'required|string|max:255',
            'draftStartsOn' => 'required|date',
            'draftDueOn' => 'required|date|after_or_equal:draftStartsOn',
            'draftPersonIds' => 'required|array',
            'draftPersonIds.*' => Rule::in($this->peopleICanDraftFor()->modelKeys()),
        ]);
    }

    /**
     * A lead can draft work for the people who report to them, and for themselves.
     *
     * @return EloquentCollection<int, User>
     */
    private function peopleICanDraftFor(): EloquentCollection
    {
        return auth()->user()->reports()->prepend(auth()->user());
    }

    /**
     * The people whose timelines are shown: my reports, and me while I am trying a draft against myself.
     *
     * @return EloquentCollection<int, User>
     */
    private function peopleToPlanFor(): EloquentCollection
    {
        if ($this->tryingDraft && in_array(auth()->id(), $this->draftPersonIds)) {
            return $this->peopleICanDraftFor();
        }

        return auth()->user()->reports();
    }

    private function turnDraftIntoWorkOwnedBy(int|string $ownerId): void
    {
        $people = User::findMany($this->draftPersonIds);

        auth()->user()->setUpWork(
            ['title' => $this->draftTitle, 'starts_on' => $this->draftStartsOn, 'due_on' => $this->draftDueOn],
            for: $people,
            ownedBy: $people->find($ownerId),
        );

        $this->clearDraft();

        Flux::toast('Added.', variant: 'success');
    }

    /**
     * The work items that I am on or that someone who reports to me is on. A lead may see all of these.
     */
    private function workOfMyPeople(): Builder
    {
        $myPeople = $this->peopleICanDraftFor()->modelKeys();

        return WorkItem::visibleTo(auth()->user())->whereHas('people', fn (Builder $people) => $people->whereKey($myPeople));
    }

    /**
     * What one person is carrying: the work with ballpark dates as bars for the timeline, and all of it grouped by where it sits.
     *
     * @return array{person: User, name: string, bars: Collection, draft: array|null, openWork: Collection<string, Collection<int, WorkItem>>, openWorkCount: int, secretCount: int}
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
            'name' => ucfirst($person->nameAsSeenBy(auth()->user())),
            'canMoveAllTo' => auth()->user()->isLineManagerOf($person) ? $this->peopleICanDraftFor()->except($person->id) : collect(),
            'bars' => $openWork->filter->hasBallparkDates()->map(fn (WorkItem $workItem) => [
                'workItem' => $workItem,
                'from' => $this->weekOf($workItem->starts_on, $thisWeek),
                'to' => $this->weekOf($workItem->due_on, $thisWeek) + 1,
                'where' => $this->whereItSits($workItem),
            ]),
            'draft' => $this->draftFor($person, $openWork, $thisWeek),
            'openWork' => $openWork->groupBy(fn (WorkItem $workItem) => ucfirst($this->whereItSits($workItem)))->sortKeysDesc(),
            'openWorkCount' => $openWork->count(),
            'secretCount' => $openWork->filter->isSecret()->count(),
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
