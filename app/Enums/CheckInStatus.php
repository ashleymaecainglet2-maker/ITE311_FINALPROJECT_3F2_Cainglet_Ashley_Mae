<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CheckInStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Due = 'due';
    case Expired = 'expired';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Due => 'Due',
            self::Expired => 'Expired',
            self::Inactive => 'Inactive',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Due => 'warning',
            self::Expired, self::Inactive => 'danger',
        };
    }

    /**
     * Color name used by the front-desk scanner UI.
     */
    public function colorName(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Due => 'yellow',
            self::Expired, self::Inactive => 'red',
        };
    }

    public function colorHex(): string
    {
        return match ($this) {
            self::Active => '#16a34a',
            self::Due => '#eab308',
            self::Expired, self::Inactive => '#dc2626',
        };
    }

    public function allowsEntry(): bool
    {
        return in_array($this, [self::Active, self::Due], true);
    }
}
