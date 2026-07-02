<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property float $persentase
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['nama', 'persentase', 'is_active'])]
class Pajak extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'persentase' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Helper to get the single active tax record (if any).
     */
    public static function getAktif(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
