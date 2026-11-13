<?php

namespace App\Models;

use App\Enums\PlateSection;
use App\Enums\PlateStatus;
use App\Enums\WorkItemStatus;
use Database\Factories\WorkItemFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class WorkItem extends Model
{
    /** @use HasFactory<WorkItemFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'notes',
        'status',
        'effort_days',
        'elapsed_days',
        'due_on',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkItemStatus::class,
            'due_on' => 'date',
        ];
    }

    /**
     * The people this work item is on the plate of.
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(UserWorkItem::class)
            ->withPivot('status', 'added_by_id', 'reason')
            ->withTimestamps();
    }

    /**
     * The rough size and by-when date in words, eg "1 day of work, 3 days elapsed, by 18 Dec 2026".
     */
    public function summary(): string
    {
        return collect([
            $this->effort_days ? $this->effort_days.' '.Str::plural('day', $this->effort_days).' of work' : null,
            $this->elapsed_days ? $this->elapsed_days.' '.Str::plural('day', $this->elapsed_days).' elapsed' : null,
            $this->due_on ? 'by '.$this->due_on->format('j M Y') : null,
        ])->filter()->join(', ');
    }

    /**
     * A link that starts an email to whoever added the person this work item was loaded for, asking why.
     */
    public function wutLink(): string
    {
        return 'mailto:'.$this->pivot->addedBy->email.'?subject='.rawurlencode($this->title.' - wut?');
    }

    /**
     * Everyone on this work item apart from the given person.
     *
     * @return Collection<int, User>
     */
    public function colleaguesOf(User $user): Collection
    {
        return $this->people->reject(fn (User $person) => $person->is($user));
    }

    /**
     * Put this work item on someone's horizon. They pull it onto their own plate when they start on it.
     */
    public function addPerson(User $person, User $addedBy, ?string $reason = null): void
    {
        $this->people()->attach($person, [
            'status' => PlateStatus::Upcoming,
            'added_by_id' => $addedBy->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Move this work item to a section of the given person's page. Parking it or
     * finishing it applies to everyone on it; plate or horizon is theirs alone.
     */
    public function moveTo(PlateSection $section, User $user): void
    {
        $this->update(['status' => $section->workItemStatus()]);

        if (! $section->plateStatus()) {
            return;
        }

        $this->people()->updateExistingPivot($user, ['status' => $section->plateStatus()]);
    }
}
