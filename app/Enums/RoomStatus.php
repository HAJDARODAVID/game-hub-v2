<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Waiting = 'waiting';     // gathering players
    case Playing = 'playing';     // a match is running
    case Finished = 'finished';   // match over, room kept as history
    case Closed = 'closed';       // closed by the host

    /** Rooms that are still live: listed first and can still change. */
    public static function open(): array
    {
        return [self::Waiting, self::Playing];
    }

    public function isOpen(): bool
    {
        return in_array($this, self::open(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Waiting => __('Waiting'),
            self::Playing => __('Playing'),
            self::Finished => __('Finished'),
            self::Closed => __('Closed'),
        };
    }

    /** Bootstrap badge classes. */
    public function badge(): string
    {
        return match ($this) {
            self::Waiting => 'text-bg-warning',
            self::Playing => 'text-bg-success',
            self::Finished => 'text-bg-secondary',
            self::Closed => 'text-bg-dark border border-secondary',
        };
    }
}
