<?php

namespace App\Models;

use Database\Factories\PromoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nama
 * @property Carbon|null $tanggal_mulai
 * @property Carbon|null $tanggal_selesai
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ProdukVarian> $produkVarians
 */
#[Fillable(['nama', 'tanggal_mulai', 'tanggal_selesai', 'is_active'])]
class Promo extends Model
{
    /** @use HasFactory<PromoFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<ProdukVarian, $this>
     */
    public function produkVarians(): BelongsToMany
    {
        return $this->belongsToMany(ProdukVarian::class, 'promo_produk_varian')
            ->withPivot('minimal_harga_jual')
            ->withTimestamps();
    }

    /**
     * Scope to only active promos within their date range.
     *
     * @param  Builder<Promo>  $query
     * @return Builder<Promo>
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('tanggal_mulai')
                    ->orWhere('tanggal_mulai', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', now());
            });
    }
}
