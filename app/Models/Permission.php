<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property string|null $keterangan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Jabatan> $jabatans
 */
#[Fillable(['nama', 'keterangan'])]
class Permission extends Model
{
    /**
     * @return BelongsToMany<Jabatan, $this>
     */
    public function jabatans(): BelongsToMany
    {
        return $this->belongsToMany(Jabatan::class, 'jabatan_permission');
    }
}
