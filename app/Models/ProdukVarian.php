<?php

namespace App\Models;

use Database\Factories\ProdukVarianFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $produk_id
 * @property int $satuan_id
 * @property string|null $sku
 * @property string $nama_varian
 * @property float|null $harga_modal
 * @property float $harga_jual
 * @property int|null $stok
 * @property int|null $minimum_stok
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Produk $produk
 * @property-read Satuan $satuan
 */
#[Fillable(['produk_id', 'satuan_id', 'sku', 'nama_varian', 'harga_modal', 'harga_jual', 'stok', 'minimum_stok'])]
class ProdukVarian extends Model
{
    /** @use HasFactory<ProdukVarianFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harga_modal' => 'decimal:2',
            'harga_jual' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Produk, $this>
     */
    public function produk(): BelongsTo
    {
        return $this->belongsTo(Produk::class);
    }

    /**
     * @return BelongsTo<Satuan, $this>
     */
    public function satuan(): BelongsTo
    {
        return $this->belongsTo(Satuan::class);
    }
}
