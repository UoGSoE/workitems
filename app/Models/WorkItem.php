<?php

namespace App\Models;

use App\Enums\PlateSection;
use App\Enums\PlateStatus;
use App\Enums\WorkItemStatus;
use App\Events\PersonAddedToWorkItem;
use App\Events\PersonTakenOffWorkItem;
use App\Events\WorkItemTakenOver;
use App\Events\WorkItemUpdated;
use Database\Factories\WorkItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WorkItem extends Model
{
    /** @use HasFactory<WorkItemFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
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
     * The one person who cannot walk away from this work item until someone else takes it over.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
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
     * Who did what to this work item, oldest first.
     */
    public function history(): HasMany
    {
        return $this->hasMany(WorkItemHistory::class);
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
     * Where this work item sits on the page of the person it was loaded for.
     */
    public function section(): PlateSection
    {
        return PlateSection::of($this->status, $this->pivot->status);
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
     * Who else is on this work item, in words, eg "With Jenny MacAdmin (owner) and Olivia McUser".
     */
    public function withLine(User $reader): string
    {
        return 'With '.$this->colleaguesOf($reader)
            ->map(fn (User $person) => $person->full_name.($person->is($this->owner) ? ' (owner)' : ''))
            ->join(', ', ' and ');
    }

    /**
     * Everyone who is not on this work item yet.
     *
     * @return Builder<User>
     */
    public function peopleWhoCouldBeAdded(): Builder
    {
        return User::whereNotIn('id', $this->people->modelKeys())->orderBy('surname');
    }

    /**
     * Add a line to this work item's history, eg "created it".
     */
    public function addHistory(User $who, string $what): void
    {
        $this->history()->create(['user_id' => $who->id, 'description' => $what]);
    }

    /**
     * Put this work item on someone's horizon and tell them. They pull it onto their own plate when they start on it.
     */
    public function addPerson(User $person, User $addedBy, ?string $reason = null): void
    {
        $this->people()->attach($person, [
            'status' => PlateStatus::Upcoming,
            'added_by_id' => $addedBy->id,
            'reason' => $reason,
        ]);

        event(new WorkItemUpdated($this, $addedBy, 'added '.$person->full_name));
        event(new PersonAddedToWorkItem($this, $person, $addedBy, $reason));
    }

    /**
     * The owner stays until someone else takes over. Anyone else can
     * take themselves off, and can be taken off by whoever added them.
     */
    public function allowsTakingOff(User $person, User $by): bool
    {
        if ($person->is($this->owner)) {
            return false;
        }

        if ($person->is($by)) {
            return true;
        }

        return $this->people->find($person)->pivot->added_by_id === $by->id;
    }

    /**
     * The people on this work item the given person is allowed to take off it.
     *
     * @return Collection<int, User>
     */
    public function peopleWhoCanBeTakenOffBy(User $user): Collection
    {
        return $this->people->filter(fn (User $person) => $this->allowsTakingOff($person, $user));
    }

    /**
     * Who gets told when someone comes off this work item. Someone taken off is told
     * themselves. When someone leaves, whoever added them is told if they are still on
     * it, and otherwise the owner.
     */
    public function whoHearsAboutTakingOff(User $person, User $takenOffBy): User
    {
        if ($person->isNot($takenOffBy)) {
            return $person;
        }

        $whoAddedThem = $this->people->find($person)->pivot->addedBy;

        if ($whoAddedThem->is($person) || ! $this->people->contains($whoAddedThem)) {
            return $this->owner;
        }

        return $whoAddedThem;
    }

    /**
     * Take someone off this work item, and let the other person involved know.
     */
    public function takeOff(User $person, User $takenOffBy, ?string $message = null): void
    {
        $tell = $this->whoHearsAboutTakingOff($person, $takenOffBy);

        $this->people()->detach($person);

        if ($person->is($takenOffBy)) {
            event(new WorkItemUpdated($this, $person, 'took themselves off it'));
            event(new PersonTakenOffWorkItem($this, $person, $tell, whatHappened: 'removed themselves from', message: $message));

            return;
        }

        event(new WorkItemUpdated($this, $takenOffBy, 'took '.$person->full_name.' off it'));
        event(new PersonTakenOffWorkItem($this, $takenOffBy, $tell, whatHappened: 'took you off', message: $message));
    }

    /**
     * Make someone already on this work item its owner, and tell whoever owned it before.
     */
    public function takeOver(User $newOwner): void
    {
        $previousOwner = $this->owner;

        $this->update(['owner_id' => $newOwner->id]);

        event(new WorkItemUpdated($this, $newOwner, 'took it over from '.$previousOwner->full_name));
        event(new WorkItemTakenOver($this, $newOwner, $previousOwner));
    }

    /**
     * Move this work item to a section of the given person's page. Parking it or
     * finishing it applies to everyone on it; plate or horizon is theirs alone.
     */
    public function moveTo(PlateSection $section, User $user): void
    {
        $this->update(['status' => $section->workItemStatus()]);

        if ($this->wasChanged('status')) {
            event(new WorkItemUpdated($this, $user, $this->status->historyLine()));
        }

        if (! $section->plateStatus()) {
            return;
        }

        $this->people()->updateExistingPivot($user, ['status' => $section->plateStatus()]);
    }
}
