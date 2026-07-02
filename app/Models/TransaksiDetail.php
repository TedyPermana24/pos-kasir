<?php

namespace App\Models;

use Database\Factories\TransaksiDetailFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $transaksi_id
 * @property int $produk_varian_id
 * @property int $kuantitas
 * @property float $harga_satuan
 * @property float $harga_modal
 * @property float $subtotal
 * @property string|null $catatan
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Transaksi $transaksi
 * @property-read ProdukVarian $produkVarian
 */
#[Fillable(['transaksi_id', 'produk_varian_id', 'kuantitas', 'harga_satuan', 'harga_modal', 'subtotal', 'catatan'])]
class TransaksiDetail extends Model
{
    /** @use HasFactory<TransaksiDetailFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'harga_satuan' => 'decimal:2',
            'harga_modal' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Transaksi, $this>
     */
    public function transaksi(): BelongsTo
    {
        return $this->belongsTo(Transaksi::class);
    }

    /**
     * @return BelongsTo<ProdukVarian, $this>
     */
    public function produkVarian(): BelongsTo
    {
        return $this->belongsTo(ProdukVarian::class);
    }
}
