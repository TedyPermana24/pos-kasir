<?php

namespace App\Models;

use Database\Factories\TransaksiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $no_referensi
 * @property int $user_id
 * @property string $nama_pelanggan
 * @property float $subtotal
 * @property float $total_pajak
 * @property float $total_diskon
 * @property float $diskon_produk
 * @property float $diskon_keranjang
 * @property float $grand_total
 * @property float $bayar
 * @property float $kembalian
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Collection<int, TransaksiDetail> $details
 */
#[Fillable(['no_referensi', 'user_id', 'nama_pelanggan', 'subtotal', 'total_pajak', 'total_diskon', 'diskon_produk', 'diskon_keranjang', 'grand_total', 'bayar', 'kembalian'])]
class Transaksi extends Model
{
    /** @use HasFactory<TransaksiFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total_pajak' => 'decimal:2',
            'total_diskon' => 'decimal:2',
            'diskon_produk' => 'decimal:2',
            'diskon_keranjang' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'bayar' => 'decimal:2',
            'kembalian' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TransaksiDetail, $this>
     */
    public function details(): HasMany
    {
        return $this->hasMany(TransaksiDetail::class);
    }

    /**
     * Generate a unique reference number for this transaction.
     */
    public static function generateNoReferensi(): string
    {
        $today = now()->format('Ymd');
        $lastToday = static::where('no_referensi', 'like', "INV-{$today}-%")
            ->orderByDesc('no_referensi')
            ->value('no_referensi');

        $nextNumber = 1;
        if ($lastToday) {
            $lastNumber = (int) str($lastToday)->afterLast('-')->toString();
            $nextNumber = $lastNumber + 1;
        }

        return sprintf('INV-%s-%03d', $today, $nextNumber);
    }
}
