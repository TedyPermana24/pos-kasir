<?php

namespace App\Models;

use Database\Factories\SatuanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Produk> $produks
 */
#[Fillable(['nama'])]
class Satuan extends Model
{
    /** @use HasFactory<SatuanFactory> */
    use HasFactory;

    /**
     * @return HasMany<Produk, $this>
     */
    public function produks(): HasMany
    {
        return $this->hasMany(Produk::class);
    }
}
