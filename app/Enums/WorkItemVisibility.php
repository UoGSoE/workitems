<?php

namespace App\Enums;

/**
 * Who can see a work item, beyond the people on it and the leads those people report to.
 */
enum WorkItemVisibility: string
{
    case PeopleOnIt = 'people_on_it';
    case Team = 'team';
    case LookingForAHand = 'looking_for_a_hand';
    case Secret = 'secret';

    public function label(): string
    {
        return match ($this) {
            self::PeopleOnIt => 'Just the people on it',
            self::Team => 'My team can see it',
            self::LookingForAHand => 'In the classifieds (everyone can see it)',
            self::Secret => 'Secret',
        };
    }

    /**
     * A line under the label, for the person choosing.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::PeopleOnIt => 'And whoever each of them reports to.',
            self::Team => 'You and the people who report to you. If nobody does, your lead and the others who report to them.',
            self::LookingForAHand => 'For work you would like help with, or that someone might learn from.',
            self::Secret => 'Like "just the people on it", but it can never be changed afterwards.',
        };
    }

    /**
     * What someone did by choosing this, for the history, eg "made it secret".
     */
    public function historyLine(): string
    {
        return match ($this) {
            self::PeopleOnIt => 'made it visible to just the people on it',
            self::Team => 'made it visible to their team',
            self::LookingForAHand => 'made it visible to everyone, in the classifieds',
            self::Secret => 'made it secret',
        };
    }
}
