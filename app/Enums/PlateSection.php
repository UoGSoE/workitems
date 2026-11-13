<?php

namespace App\Enums;

/**
 * The sections of a person's page. An open work item sits on their own plate or
 * horizon; a parked or done one sits there for everyone on it.
 */
enum PlateSection: string
{
    case MyPlate = 'my_plate';
    case Horizon = 'horizon';
    case Parked = 'parked';
    case Done = 'done';

    public static function of(WorkItemStatus $workItemStatus, PlateStatus $plateStatus): self
    {
        return match ($workItemStatus) {
            WorkItemStatus::Parked => self::Parked,
            WorkItemStatus::Done => self::Done,
            WorkItemStatus::Open => $plateStatus === PlateStatus::InProgress ? self::MyPlate : self::Horizon,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MyPlate => 'My plate',
            self::Horizon => 'On the horizon',
            self::Parked => 'Parked',
            self::Done => 'Done',
        };
    }

    /**
     * The wording for the action that moves a work item to this section.
     */
    public function moveLabel(): string
    {
        return match ($this) {
            self::MyPlate => 'Put on my plate',
            self::Horizon => 'Put on the horizon',
            self::Parked => 'Park',
            self::Done => 'Done',
        };
    }

    public function workItemStatus(): WorkItemStatus
    {
        return match ($this) {
            self::MyPlate, self::Horizon => WorkItemStatus::Open,
            self::Parked => WorkItemStatus::Parked,
            self::Done => WorkItemStatus::Done,
        };
    }

    /**
     * The person's own status this section needs, if it needs one.
     */
    public function plateStatus(): ?PlateStatus
    {
        return match ($this) {
            self::MyPlate => PlateStatus::InProgress,
            self::Horizon => PlateStatus::Upcoming,
            self::Parked, self::Done => null,
        };
    }
}
