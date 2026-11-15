<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\WorkItemStatus;
use App\Enums\WorkItemVisibility;
use App\Events\WorkItemsMoved;
use App\Events\WorkItemUpdated;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Impersonate, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'surname',
        'forenames',
        'is_staff',
        'is_admin',
        'is_people_admin',
        'team_id',
        'job_title',
        'shows_work_to_team',
        'left_on',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_staff' => 'boolean',
            'is_admin' => 'boolean',
            'is_people_admin' => 'boolean',
            'shows_work_to_team' => 'boolean',
            'left_on' => 'date',
        ];
    }

    public function workItems(): BelongsToMany
    {
        return $this->belongsToMany(WorkItem::class)
            ->using(UserWorkItem::class)
            ->withPivot('status', 'added_by_id', 'reason')
            ->withTimestamps();
    }

    /**
     * The team this person is a member of. They report to its lead.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The teams this person leads.
     */
    public function ledTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'lead_id');
    }

    /**
     * The people who are still here: everyone who has not been marked as gone.
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->whereNull('left_on');
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => $this->forenames.' '.$this->surname);
    }

    /**
     * Their name with what they do, eg "Jenny MacAdmin (Infrastructure Lead)".
     */
    protected function nameWithJobTitle(): Attribute
    {
        return Attribute::get(fn () => $this->job_title ? "{$this->full_name} ({$this->job_title})" : $this->full_name);
    }

    /**
     * Who this person turns to: the lead of the team they are a member of.
     */
    public function lineManager(): ?User
    {
        return $this->team?->lead;
    }

    /**
     * The people who report to this person: the members of every team they lead.
     *
     * @return Collection<int, User>
     */
    public function reports(): Collection
    {
        return User::whereIn('team_id', $this->ledTeams()->pluck('id'))->orderBy('surname')->get();
    }

    public function isLineManagerOf(User $person): bool
    {
        return $person->lineManager()?->is($this) ?? false;
    }

    /**
     * "My team" is me and the people who report directly to me. If nobody
     * reports to me, it is my lead and the others who report to them.
     *
     * @return Collection<int, User>
     */
    public function peopleInMyTeam(): Collection
    {
        $reports = $this->reports();

        if ($reports->isNotEmpty()) {
            return $reports->push($this);
        }

        $lead = $this->lineManager();

        if (! $lead) {
            return new Collection([$this]);
        }

        return $lead->reports()->push($lead);
    }

    /**
     * The people whose "my team" includes this person. They can only be this person, their lead,
     * the others who report to that lead, or the people who report to them, so ask each of those.
     *
     * @return Collection<int, User>
     */
    public function peopleWhoCountMeInTheirTeam(): Collection
    {
        $lead = $this->lineManager();

        return $this->reports()
            ->merge($lead?->reports() ?? [])
            ->push($this)
            ->when($lead, fn (Collection $people) => $people->push($lead))
            ->unique()
            ->filter(fn (User $person) => $person->peopleInMyTeam()->contains($this))
            ->values();
    }

    /**
     * Give every work item this person owns, apart from the finished ones, to someone else.
     * They get one email listing what is new to them, not one email for each.
     */
    public function handWorkTo(User $newPerson, User $by): void
    {
        $owned = $this->unfinishedWorkItems()->filter(fn (WorkItem $workItem) => $workItem->isOwnedBy($this));
        $newToThem = $owned->reject(fn (WorkItem $workItem) => $workItem->people->contains($newPerson))->values();

        $owned->each(fn (WorkItem $workItem) => $workItem->giveTo($newPerson, by: $by));

        if ($newToThem->isEmpty() || $newPerson->is($by)) {
            return;
        }

        event(new WorkItemsMoved($by, tell: $newPerson, whatHappened: 'gave you work that '.$this->full_name.' owned', workItems: $newToThem));
    }

    /**
     * Give this person's place on every open work item to someone else, as their lead does
     * when the whole load moves. Parked and finished work stays where it is. Everyone involved
     * gets one email listing the work, not one email for each.
     */
    public function moveOpenWorkTo(User $newPerson, User $by): void
    {
        $workItems = $this->workItems()->where('work_items.status', WorkItemStatus::Open)->with('people', 'owner')->get();

        if ($workItems->isEmpty()) {
            return;
        }

        $ownersToTell = $workItems->pluck('owner')->unique('id')->reject(fn (User $owner) => $owner->is($this) || $owner->is($newPerson) || $owner->is($by));
        $ownedBy = fn (User $owner) => $workItems->where('owner_id', $owner->id)->values();

        $workItems->each(fn (WorkItem $workItem) => $workItem->movePlaceWithoutTellingAnyone($this, $newPerson, by: $by));

        event(new WorkItemsMoved($by, tell: $this, whatHappened: 'moved all of your open work to '.$newPerson->full_name, workItems: $workItems));

        $ownersToTell->each(fn (User $owner) => event(new WorkItemsMoved($by, tell: $owner, whatHappened: 'swapped '.$this->full_name.' for '.$newPerson->full_name.' on work you own', workItems: $ownedBy($owner))));

        if ($newPerson->is($by)) {
            return;
        }

        event(new WorkItemsMoved($by, tell: $newPerson, whatHappened: 'moved everything '.$this->full_name.' was working on to you', workItems: $workItems));
    }

    /**
     * This person has left. Their work goes to someone else and they come off everything still going on.
     */
    public function markAsGone(User $by, ?User $giveWorkTo = null, ?User $newLeadOfTheirTeams = null): void
    {
        if ($giveWorkTo) {
            $this->handWorkTo($giveWorkTo, by: $by);
        }

        $this->unfinishedWorkItems()
            ->reject(fn (WorkItem $workItem) => $workItem->isOwnedBy($this))
            ->each(fn (WorkItem $workItem) => $workItem->takeOffSomeoneWhoHasLeft($this, by: $by));

        $this->ledTeams()->update(['lead_id' => $newLeadOfTheirTeams?->id]);

        $this->update(['left_on' => today(), 'team_id' => null]);
    }

    /**
     * The people an admin may give this person's work to when they go. Never the admin themselves,
     * unless this person reports to them: being an admin gives no right to see other people's work.
     *
     * @return Collection<int, User>
     */
    public function peopleWhoCouldTakeOnTheirWork(User $asChosenBy): Collection
    {
        return User::current()->whereKeyNot($this->id)->orderBy('surname')->orderBy('forenames')->get()
            ->reject(fn (User $person) => $person->is($asChosenBy) && ! $asChosenBy->isLineManagerOf($this));
    }

    /**
     * Only a developer can step into someone else's view of the app, to see why it behaves as it does for them.
     */
    public function canImpersonate(): bool
    {
        return $this->is_admin;
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->hasLeft();
    }

    /**
     * Whether this person may add people, set teams and leads, and mark people as gone.
     * Developers can too, so that they can fix things.
     */
    public function canManagePeople(): bool
    {
        return $this->is_admin || $this->is_people_admin;
    }

    public function hasLeft(): bool
    {
        return $this->left_on !== null;
    }

    public function ownsUnfinishedWork(): bool
    {
        return $this->unfinishedWorkItems()->contains(fn (WorkItem $workItem) => $workItem->isOwnedBy($this));
    }

    /**
     * The work items this person is on that are still going on: everything apart from the finished ones.
     *
     * @return Collection<int, WorkItem>
     */
    public function unfinishedWorkItems(): Collection
    {
        return $this->workItems()->where('work_items.status', '!=', WorkItemStatus::Done)->with('people', 'owner')->get();
    }

    /**
     * Create a work item for other people, as a lead does from a draft. It goes on everyone's
     * horizon, and everyone apart from the person setting it up is told.
     *
     * @param  array{title: string, starts_on: string, due_on: string}  $details
     * @param  Collection<int, User>  $for
     */
    public function setUpWork(array $details, Collection $for, User $ownedBy): WorkItem
    {
        $workItem = WorkItem::create([
            ...$details,
            'owner_id' => $ownedBy->id,
            'status' => WorkItemStatus::Open,
            'visibility' => $ownedBy->visibilityOfNewWorkItems(),
        ]);

        event(new WorkItemUpdated($workItem, $this, 'created it'));

        $for->each(fn (User $person) => $workItem->addPerson($person, addedBy: $this, reason: $person->is($ownedBy) ? 'As its owner' : null));

        return $workItem;
    }

    /**
     * Who gets to see a work item this person has just added, until they say otherwise.
     */
    public function visibilityOfNewWorkItems(): WorkItemVisibility
    {
        return $this->shows_work_to_team ? WorkItemVisibility::Team : WorkItemVisibility::PeopleOnIt;
    }

    /**
     * How this person is named to whoever is reading: "me" for themselves, otherwise their full name.
     */
    public function nameAsSeenBy(User $reader): string
    {
        return $this->is($reader) ? 'me' : $this->full_name;
    }

    /**
     * This person's work items, newest first, loaded with everyone a page about them
     * mentions: the people on each one, its owner, and who added this person to it.
     *
     * @return Collection<int, WorkItem>
     */
    public function workItemsWithEveryoneInvolved(): Collection
    {
        $workItems = $this->workItems()->with('people', 'owner')->get()->sortByDesc('id');

        Collection::make($workItems->pluck('pivot'))->load('addedBy');

        return $workItems;
    }
}
