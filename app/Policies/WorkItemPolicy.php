<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkItem;

class WorkItemPolicy
{
    public function view(User $user, WorkItem $workItem): bool
    {
        return $workItem->canBeSeenBy($user);
    }

    /**
     * Only the owner decides who sees a work item, and a secret one stays secret.
     */
    public function changeVisibility(User $user, WorkItem $workItem): bool
    {
        return $workItem->isOwnedBy($user) && ! $workItem->isSecret();
    }

    /**
     * The blurb is the owner's pitch for help, so only the owner writes it.
     */
    public function writeBlurb(User $user, WorkItem $workItem): bool
    {
        return $workItem->isOwnedBy($user);
    }

    public function takeOff(User $user, WorkItem $workItem, User $person): bool
    {
        return $workItem->allowsTakingOff($person, by: $user);
    }

    /**
     * A lead can give the place of someone who reports to them to somebody else.
     */
    public function movePlace(User $user, WorkItem $workItem, User $person): bool
    {
        return $user->isLineManagerOf($person) && $workItem->people->contains($person);
    }

    /**
     * Anyone on a work item can take it over, apart from the person who already owns it.
     */
    public function takeOver(User $user, WorkItem $workItem): bool
    {
        return ! $workItem->isOwnedBy($user);
    }
}
