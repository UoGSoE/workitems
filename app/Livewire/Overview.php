<?php

namespace App\Livewire;

use App\Enums\WorkItemStatus;
use App\Enums\WorkItemVisibility;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

class Overview extends Component
{
    public function render()
    {
        $everyoneBelowMe = auth()->user()->everyoneBelow()->loadMissing('team');

        $unfinishedWork = WorkItem::where('status', '!=', WorkItemStatus::Done)
            ->where('visibility', '!=', WorkItemVisibility::Secret)
            ->whereHas('people', fn (Builder $people) => $people->whereKey($everyoneBelowMe->modelKeys()))
            ->with('people')
            ->get();

        return view('livewire.overview', [
            'people' => $everyoneBelowMe->count(),
            'totals' => $this->totalsFor($unfinishedWork),
            'areas' => auth()->user()->reports
                ->filter->leadsAnyone()
                ->map(fn (User $lead) => $this->areaOf($lead, $unfinishedWork))
                ->sortByDesc('pastByWhen')
                ->values(),
        ]);
    }

    public function boot(): void
    {
        abort_unless(auth()->user()->hasLeadsReportingToThem(), 403);
    }

    /**
     * One of my people and everyone beneath them, rolled up. The teams named are the ones the
     * people doing the work are in, which leaves out a team that only holds other leads.
     *
     * @param  Collection<int, WorkItem>  $unfinishedWork
     * @return array{lead: User, teams: string, summary: string, pastByWhen: int}
     */
    private function areaOf(User $lead, Collection $unfinishedWork): array
    {
        $people = $lead->everyoneBelow()->push($lead);
        $totals = $this->totalsFor($unfinishedWork->filter(fn (WorkItem $workItem) => $workItem->people->intersect($people)->isNotEmpty()));

        return [
            'lead' => $lead,
            'teams' => $people->reject->leadsAnyone()->pluck('team.name')->unique()->sort()->join(', ', ' and '),
            'summary' => collect([
                $people->count().' '.Str::plural('person', $people->count()),
                $totals['open'].' open work '.Str::plural('item', $totals['open']),
                $totals['days'] ? 'about '.$totals['days'].' '.Str::plural('day', $totals['days']).' of work' : null,
            ])->filter()->join(', '),
            'pastByWhen' => $totals['pastByWhen'],
        ];
    }

    /**
     * The handful of numbers that sum up some work. Only open work counts towards the days, the by-when dates and looking for a hand.
     *
     * @param  Collection<int, WorkItem>  $unfinishedWork
     * @return array{open: int, days: int, pastByWhen: int, parked: int, lookingForAHand: int}
     */
    private function totalsFor(Collection $unfinishedWork): array
    {
        $open = $unfinishedWork->where('status', WorkItemStatus::Open);

        return [
            'open' => $open->count(),
            'days' => (int) $open->sum('effort_days'),
            'pastByWhen' => $open->filter->isPastItsByWhenDate()->count(),
            'parked' => $unfinishedWork->where('status', WorkItemStatus::Parked)->count(),
            'lookingForAHand' => $open->where('visibility', WorkItemVisibility::LookingForAHand)->count(),
        ];
    }
}
