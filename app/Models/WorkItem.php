<?php

namespace App\Models;

use App\Enums\PlateSection;
use App\Enums\PlateStatus;
use App\Enums\WorkItemStatus;
use App\Enums\WorkItemVisibility;
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
        'visibility',
        'blurb',
        'effort_days',
        'elapsed_days',
        'starts_on',
        'due_on',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WorkItemStatus::class,
            'visibility' => WorkItemVisibility::class,
            'starts_on' => 'date',
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
     * The work items the given person is allowed to see. Keep this saying the same as canBeSeenBy().
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->whereHas('people', fn (Builder $people) => $people->whereKey($user->id))
                ->orWhereHas('people', fn (Builder $people) => $people->whereIn('users.team_id', $user->ledTeams()->pluck('id')))
                ->orWhere('visibility', WorkItemVisibility::LookingForAHand)
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('visibility', WorkItemVisibility::Team)
                        ->whereIn('owner_id', $user->peopleWhoCountMeInTheirTeam()->modelKeys());
                });
        });
    }

    /**
     * The rough size and by-when date in words, eg "1 day of work, 3 days elapsed, by 18 Dec 2026".
     */
    public function summary(): string
    {
        return collect([
            $this->effort_days ? $this->effort_days.' '.Str::plural('day', $this->effort_days).' of work' : null,
            $this->elapsed_days ? $this->elapsed_days.' '.Str::plural('day', $this->elapsed_days).' elapsed' : null,
            $this->starts_on ? 'from '.$this->starts_on->format('j M Y') : null,
            $this->due_on ? 'by '.$this->due_on->format('j M Y') : null,
        ])->filter()->join(', ');
    }

    /**
     * The owner's pitch for help, turned from markdown into HTML that is safe to show to everyone:
     * raw HTML is stripped out, links that could run script lose their address, and pictures are
     * left out so that nobody's browser fetches something from an address the owner chose.
     */
    public function blurbAsHtml(): string
    {
        $html = Str::markdown($this->blurb ?? '', ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return strip_tags($html, ['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li', 'code', 'pre', 'blockquote']);
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
     * Whether the given person is allowed to see this work item. Keep this saying the same as scopeVisibleTo().
     */
    public function canBeSeenBy(User $user): bool
    {
        if ($this->people->contains($user)) {
            return true;
        }

        if ($this->people->contains(fn (User $person) => $user->isLineManagerOf($person))) {
            return true;
        }

        if ($this->visibility === WorkItemVisibility::LookingForAHand) {
            return true;
        }

        return $this->visibility === WorkItemVisibility::Team && $this->owner->peopleInMyTeam()->contains($user);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner->is($user);
    }

    public function isSecret(): bool
    {
        return $this->visibility === WorkItemVisibility::Secret;
    }

    /**
     * Whether there is a rough start and a by-when date, which is what puts a work item on a team's timeline.
     */
    public function hasBallparkDates(): bool
    {
        return $this->starts_on !== null && $this->due_on !== null;
    }

    /**
     * Everyone still here who is not on this work item yet.
     *
     * @return Builder<User>
     */
    public function peopleWhoCouldBeAdded(): Builder
    {
        return User::current()->whereNotIn('id', $this->people->modelKeys())->orderBy('surname');
    }

    /**
     * Add a line to this work item's history, eg "created it".
     */
    public function addHistory(User $who, string $what): void
    {
        $this->history()->create(['user_id' => $who->id, 'description' => $what]);
    }

    /**
     * Put this work item on someone's horizon and tell them. They pull it onto their own plate when
     * they start on it. Someone who puts it on their own horizon needs no telling.
     */
    public function addPerson(User $person, User $addedBy, ?string $reason = null): void
    {
        $this->putOnHorizonOf($person, $addedBy, $reason);

        if ($person->is($addedBy)) {
            return;
        }

        event(new WorkItemUpdated($this, $addedBy, 'added '.$person->full_name));
        event(new PersonAddedToWorkItem($this, $person, $addedBy, $reason));
    }

    private function putOnHorizonOf(User $person, User $addedBy, ?string $reason): void
    {
        $this->people()->attach($person, [
            'status' => PlateStatus::Upcoming,
            'added_by_id' => $addedBy->id,
            'reason' => $reason,
        ]);
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
     * Hand this work item from its owner to someone else, on the owner's behalf. The
     * new owner is added if they are not already on it, and the old owner comes off.
     * Nobody is emailed here: whoever hands over several at once sends one email for the lot.
     */
    public function giveTo(User $newOwner, User $by): void
    {
        $previousOwner = $this->owner;

        if (! $this->people->contains($newOwner)) {
            $this->putOnHorizonOf($newOwner, addedBy: $by, reason: 'Taking over from '.$previousOwner->full_name);

            event(new WorkItemUpdated($this, $by, 'added '.$newOwner->full_name));
        }

        $this->update(['owner_id' => $newOwner->id]);
        $this->people()->detach($previousOwner);

        event(new WorkItemUpdated($this, $by, 'gave it to '.$newOwner->full_name.' from '.$previousOwner->full_name));
    }

    /**
     * Give one person's place on this work item to someone else, on their lead's say-so. If they
     * owned it, the new person owns it now. The person who came off is told, and so is the owner
     * when the swap was not their own doing.
     */
    public function movePlace(User $from, User $to, User $by): void
    {
        $owner = $this->owner;
        $newToIt = ! $this->people->contains($to);

        $this->movePlaceWithoutTellingAnyone($from, $to, $by);

        if ($newToIt && $to->isNot($by)) {
            event(new PersonAddedToWorkItem($this, $to, $by, 'Taking over from '.$from->full_name));
        }

        event(new PersonTakenOffWorkItem($this, $by, $from, whatHappened: 'gave your place to '.$to->full_name.' on'));

        if ($owner->is($from) || $owner->is($by)) {
            return;
        }

        $whatTheOwnerReads = $owner->is($to) ? 'took '.$from->full_name.' off' : 'swapped '.$from->full_name.' for '.$to->full_name.' on';

        event(new PersonTakenOffWorkItem($this, $by, $owner, whatHappened: $whatTheOwnerReads));
    }

    /**
     * The move itself and its line of history, with no emails. For when several
     * work items move at once and one email covers them all.
     */
    public function movePlaceWithoutTellingAnyone(User $from, User $to, User $by): void
    {
        if (! $this->people->contains($to)) {
            $this->putOnHorizonOf($to, addedBy: $by, reason: 'Taking over from '.$from->full_name);
        }

        if ($this->isOwnedBy($from)) {
            $this->update(['owner_id' => $to->id]);
        }

        $this->people()->detach($from);

        event(new WorkItemUpdated($this, $by, 'moved it from '.$from->full_name.' to '.$to->full_name));
    }

    /**
     * Take someone who has left off this work item. Nobody is emailed, because the person who would be told has gone.
     */
    public function takeOffSomeoneWhoHasLeft(User $leaver, User $by): void
    {
        $this->people()->detach($leaver);

        event(new WorkItemUpdated($this, $by, 'took '.$leaver->full_name.' off it'));
    }

    /**
     * Change who can see this work item, and say so in its history.
     */
    public function changeVisibility(WorkItemVisibility $visibility, User $by): void
    {
        $this->update(['visibility' => $visibility]);

        event(new WorkItemUpdated($this, $by, $visibility->historyLine()));
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
