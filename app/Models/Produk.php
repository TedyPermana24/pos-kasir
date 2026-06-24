<?php

namespace App\Models;

use Database\Factories\ProdukFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $kategori_id
 * @property string $nama_produk
 * @property string|null $foto_produk
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Kategori $kategori
 * @property-read Collection<int, ProdukVarian> $varians
 */
#[Fillable(['kategori_id', 'nama_produk', 'foto_produk'])]
class Produk extends Model
{
    /** @use HasFactory<ProdukFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Kategori, $this>
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class);
    }

    /**
     * @return HasMany<ProdukVarian, $this>
     */
    public function varians(): HasMany
    {
        return $this->hasMany(ProdukVarian::class);
    }
}
