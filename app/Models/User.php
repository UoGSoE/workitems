<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'surname',
        'forenames',
        'is_staff',
        'is_admin',
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
        ];
    }

    public function workItems(): BelongsToMany
    {
        return $this->belongsToMany(WorkItem::class)
            ->using(UserWorkItem::class)
            ->withPivot('status', 'added_by_id', 'reason')
            ->withTimestamps();
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => $this->forenames.' '.$this->surname);
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
