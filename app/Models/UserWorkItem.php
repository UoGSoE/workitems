<?php

namespace App\Models;

use App\Enums\PlateStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * One person's own row on a work item.
 */
class UserWorkItem extends Pivot
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlateStatus::class,
            'user_id' => 'integer',
            'added_by_id' => 'integer',
        ];
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_id');
    }

    public function wasAddedBySomeoneElse(): bool
    {
        return $this->added_by_id !== $this->user_id;
    }

    /**
     * People can take themselves off work they were added to, and take off anyone they added.
     */
    public function canBeTakenOffBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return $this->wasAddedBySomeoneElse();
        }

        return $this->added_by_id === $user->id;
    }

    /**
     * Who added this person and why, eg "Added by Jenny MacAdmin: you know how the backups work".
     */
    public function addedByLine(): string
    {
        return collect(['Added by '.$this->addedBy->full_name, $this->reason])->filter()->join(': ');
    }
}
