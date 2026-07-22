<?php

use App\Models\Kategori;
use App\Models\Pajak;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Promo;
use App\Models\Transaksi;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kasir')] #[Layout('layouts.pos')] class extends Component {
    public string $search = '';
    public string $kategoriFilter = '';

    /**
     * Cart items.
     *
     * @var array<int, array{varian_id: int, nama_produk: string, nama_varian: string, foto: string|null, harga: float, qty: int, catatan: string, diskon_aktif: bool, diskon_nama: string, diskon_per_item: float, subtotal: float}>
     */
    public array $cart = [];

    public string $namaPelanggan = 'GUEST';

    /** Product detail slider state */
    public bool $showDetail = false;
    public ?int $detailVarianId = null;
    public string $detailNamaProduk = '';
    public string $detailNamaVarian = '';
    public ?string $detailFoto = null;
    public float $detailHarga = 0;
    public int $detailQty = 1;
    public string $detailCatatan = '';
    public bool $detailDiskon = false;
    public ?int $detailPromoId = null;
    public float $detailDiskonPerItem = 0;
    public string $detailDiskonTipe = 'nominal';
    public string $detailDiskonInput = '';
    public ?int $detailStok = null;

    /** @var array<int, array{id: int, nama: string, minimal_harga_jual: float}> */
    public array $detailAvailablePromos = [];

    /** Cart-wide discount */
    public bool $showDiskonKeranjangSidebar = false;
    public bool $diskonKeranjangAktif = false;
    public string $diskonKeranjangTipe = 'nominal';
    public string $diskonKeranjangNominal = '0';

    /** Payment view state */
    public bool $applyTax = true;
    public bool $showPayment = false;
    public string $bayar = '';
    public string $metodePembayaran = 'cash';

    /** Success view state */
    public bool $showSuccess = false;
    public ?Transaksi $lastTransaksi = null;

    public function openDetail(int $varianId): void
    {
        $varian = ProdukVarian::with('produk')->findOrFail($varianId);

        $this->detailVarianId = $varian->id;
        $this->detailNamaProduk = $varian->produk->nama_produk;
        $this->detailNamaVarian = $varian->nama_varian;
        $this->detailFoto = $varian->produk->foto_produk
            ? \Illuminate\Support\Facades\Storage::url($varian->produk->foto_produk)
            : null;
        $this->detailHarga = (float) $varian->harga_jual;
        $this->detailStok = $varian->stok;
        $this->detailQty = 1;
        $this->detailCatatan = '';
        $this->detailDiskon = false;
        $this->detailPromoId = null;
        $this->detailDiskonPerItem = 0;
        $this->detailDiskonTipe = 'nominal';
        $this->detailDiskonInput = '';

        // Find available promos for this varian
        $this->detailAvailablePromos = Promo::aktif()
            ->whereHas('produkVarians', fn (Builder $q) => $q->where('produk_varian_id', $varianId))
            ->get()
            ->map(function (Promo $promo) use ($varianId) {
                $pivot = $promo->produkVarians()->where('produk_varian_id', $varianId)->first()?->pivot;

                return [
                    'id' => $promo->id,
                    'nama' => $promo->nama,
                    'minimal_harga_jual' => (float) ($pivot?->minimal_harga_jual ?? 0),
                ];
            })
            ->toArray();

        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
    }

    public function updatedDetailDiskon(): void
    {
        if (! $this->detailDiskon) {
            $this->detailPromoId = null;
            $this->detailDiskonPerItem = 0;
            $this->detailDiskonInput = '';
            $this->resetValidation('detailDiskonInput');
        }
    }

    public function selectPromo(int $promoId): void
    {
        $this->detailPromoId = $promoId;
        $this->detailDiskonInput = '';
        $this->detailDiskonPerItem = 0;
        $this->resetValidation('detailDiskonInput');
        $this->calculateDetailDiskon();
    }

    public function updatedDetailDiskonInput(): void
    {
        $this->calculateDetailDiskon();
    }

    public function updatedDetailDiskonTipe(): void
    {
        $this->calculateDetailDiskon();
    }

    public function calculateDetailDiskon(): void
    {
        $this->resetValidation('detailDiskonInput');
        $this->detailDiskonPerItem = 0;

        if (!$this->detailPromoId || $this->detailDiskonInput === '') {
            return;
        }

        $input = (float) $this->detailDiskonInput;
        $diskon = $this->detailDiskonTipe === 'persen' ? ($input / 100) * $this->detailHarga : $input;

        $minHargaJual = 0;
        foreach ($this->detailAvailablePromos as $promo) {
            if ($promo['id'] === $this->detailPromoId) {
                $minHargaJual = $promo['minimal_harga_jual'];
                break;
            }
        }

        if (($this->detailHarga - $diskon) < $minHargaJual) {
            $this->addError('detailDiskonInput', __('Minimal harga jual Rp :min', ['min' => number_format($minHargaJual, 0, ',', '.')]));
        } else {
            $this->detailDiskonPerItem = $diskon;
        }
    }

    public function detailIncrement(): void
    {
        $this->detailQty++;
    }

    public function detailDecrement(): void
    {
        if ($this->detailQty > 1) {
            $this->detailQty--;
        }
    }

    public function addToCartFromDetail(): void
    {
        if (! $this->detailVarianId || $this->getErrorBag()->has('detailDiskonInput')) {
            return;
        }

        $varian = ProdukVarian::with('produk')->find($this->detailVarianId);
        if ($varian && $varian->stok !== null) {
            $existingQty = 0;
            foreach ($this->cart as $item) {
                if ($item['varian_id'] === $this->detailVarianId) {
                    $existingQty += $item['qty'];
                }
            }

            if ($varian->stok <= 0) {
                Flux::toast(variant: 'danger', text: __('Stok :produk (:varian) habis.', ['produk' => $varian->produk->nama_produk, 'varian' => $varian->nama_varian]));

                return;
            }

            if (($existingQty + $this->detailQty) > $varian->stok) {
                Flux::toast(variant: 'danger', text: __('Stok :produk (:varian) tidak mencukupi (sisa stok: :stok).', ['produk' => $varian->produk->nama_produk, 'varian' => $varian->nama_varian, 'stok' => $varian->stok]));

                return;
            }
        }

        $hargaSetelahDiskon = $this->detailHarga - $this->detailDiskonPerItem;
        $diskonNama = '';
        if ($this->detailDiskon && $this->detailPromoId) {
            foreach ($this->detailAvailablePromos as $promo) {
                if ($promo['id'] === $this->detailPromoId) {
                    $diskonNama = $promo['nama'];
                    break;
                }
            }
        }

        // Check if same varian already in cart (merge)
        foreach ($this->cart as $index => $item) {
            if ($item['varian_id'] === $this->detailVarianId
                && $item['diskon_aktif'] === $this->detailDiskon
                && $item['diskon_per_item'] == $this->detailDiskonPerItem) {
                $this->cart[$index]['qty'] += $this->detailQty;
                $this->cart[$index]['subtotal'] = $hargaSetelahDiskon * $this->cart[$index]['qty'];

                $this->showDetail = false;

                return;
            }
        }

        $this->cart[] = [
            'varian_id' => $this->detailVarianId,
            'nama_produk' => $this->detailNamaProduk,
            'nama_varian' => $this->detailNamaVarian,
            'foto' => $this->detailFoto,
            'harga' => $this->detailHarga,
            'qty' => $this->detailQty,
            'catatan' => $this->detailCatatan,
            'diskon_aktif' => $this->detailDiskon && $this->detailDiskonPerItem > 0,
            'diskon_nama' => $diskonNama,
            'diskon_per_item' => $this->detailDiskonPerItem,
            'subtotal' => $hargaSetelahDiskon * $this->detailQty,
        ];

        $this->showDetail = false;
    }

    public function incrementQty(int $index): void
    {
        if (isset($this->cart[$index])) {
            $item = $this->cart[$index];
            $varian = ProdukVarian::with('produk')->find($item['varian_id']);
            if ($varian && $varian->stok !== null) {
                $totalQtyInCart = 0;
                foreach ($this->cart as $cItem) {
                    if ($cItem['varian_id'] === $item['varian_id']) {
                        $totalQtyInCart += $cItem['qty'];
                    }
                }

                if (($totalQtyInCart + 1) > $varian->stok) {
                    Flux::toast(variant: 'warning', text: __('Stok :produk (:varian) tidak mencukupi (sisa stok: :stok).', ['produk' => $item['nama_produk'], 'varian' => $item['nama_varian'], 'stok' => $varian->stok]));

                    return;
                }
            }

            $this->cart[$index]['qty']++;
            $harga = $this->cart[$index]['harga'] - $this->cart[$index]['diskon_per_item'];
            $this->cart[$index]['subtotal'] = $harga * $this->cart[$index]['qty'];
        }
    }

    public function decrementQty(int $index): void
    {
        if (isset($this->cart[$index])) {
            if ($this->cart[$index]['qty'] > 1) {
                $this->cart[$index]['qty']--;
                $harga = $this->cart[$index]['harga'] - $this->cart[$index]['diskon_per_item'];
                $this->cart[$index]['subtotal'] = $harga * $this->cart[$index]['qty'];
            } else {
                $this->removeItem($index);
            }
        }
    }

    public function removeItem(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->diskonKeranjangAktif = false;
        $this->diskonKeranjangTipe = 'nominal';
        $this->diskonKeranjangNominal = '0';
        $this->applyTax = true;
        Flux::toast(variant: 'success', text: __('Keranjang berhasil dikosongkan.'));
    }

    /** Payment */
    public function openPayment(): void
    {
        if (empty($this->cart)) {
            Flux::toast(variant: 'warning', text: __('Keranjang masih kosong.'));

            return;
        }

        foreach ($this->cart as $item) {
            $varian = ProdukVarian::with('produk')->find($item['varian_id']);
            if ($varian && $varian->stok !== null) {
                if ($item['qty'] > $varian->stok) {
                    Flux::toast(variant: 'danger', text: __('Stok :produk (:varian) tidak mencukupi (sisa stok: :stok).', ['produk' => $item['nama_produk'], 'varian' => $item['nama_varian'], 'stok' => $varian->stok]));

                    return;
                }
            }
        }

        $this->bayar = '';
        $this->showPayment = true;
    }

    public function closePayment(): void
    {
        $this->showPayment = false;
    }

    public function appendBayar(string $digit): void
    {
        if ($this->bayar === '0') {
            $this->bayar = $digit;
        } else {
            $this->bayar .= $digit;
        }
    }

    public function backspaceBayar(): void
    {
        $this->bayar = substr($this->bayar, 0, -1);
        if ($this->bayar === '') {
            $this->bayar = '';
        }
    }

    public function clearBayar(): void
    {
        $this->bayar = '';
    }

    public function setBayar(string $amount): void
    {
        $this->bayar = $amount;
    }

    public function processPayment(): void
    {
        $bayarAmount = (float) $this->bayar;
        $grandTotal = $this->grandTotal;

        if ($bayarAmount < $grandTotal) {
            $this->addError('bayar', __('Jumlah bayar kurang dari total.'));

            return;
        }

        foreach ($this->cart as $item) {
            $varian = ProdukVarian::with('produk')->find($item['varian_id']);
            if ($varian && $varian->stok !== null) {
                if ($item['qty'] > $varian->stok) {
                    Flux::toast(variant: 'danger', text: __('Stok :produk (:varian) tidak mencukupi (sisa stok: :stok).', ['produk' => $item['nama_produk'], 'varian' => $item['nama_varian'], 'stok' => $varian->stok]));

                    return;
                }
            }
        }

        $kembalian = $bayarAmount - $grandTotal;

        $transaksi = Transaksi::create([
            'no_referensi' => Transaksi::generateNoReferensi(),
            'user_id' => auth()->id(),
            'nama_pelanggan' => $this->namaPelanggan ?: 'GUEST',
            'subtotal' => $this->subtotal,
            'total_pajak' => $this->totalPajak,
            'total_diskon' => $this->totalDiskonProduk + $this->diskonKeranjangValue,
            'diskon_produk' => $this->totalDiskonProduk,
            'diskon_keranjang' => $this->diskonKeranjangValue,
            'grand_total' => $grandTotal,
            'bayar' => $bayarAmount,
            'kembalian' => $kembalian,
        ]);

        foreach ($this->cart as $item) {
            $transaksi->details()->create([
                'produk_varian_id' => $item['varian_id'],
                'kuantitas' => $item['qty'],
                'harga_satuan' => $item['harga'] - $item['diskon_per_item'],
                'harga_modal' => ProdukVarian::find($item['varian_id'])?->harga_modal ?? 0,
                'subtotal' => $item['subtotal'],
                'catatan' => $item['catatan'] ?? null,
            ]);

            $varian = ProdukVarian::find($item['varian_id']);
            if ($varian && $varian->stok !== null) {
                $varian->decrement('stok', $item['qty']);
            }
        }

        $this->cart = [];
        $this->namaPelanggan = 'GUEST';
        $this->bayar = '';
        $this->diskonKeranjangAktif = false;
        $this->diskonKeranjangTipe = 'nominal';
        $this->diskonKeranjangNominal = '0';
        $this->applyTax = true;
        $this->showPayment = false;
        
        $this->lastTransaksi = $transaksi;
        $this->showSuccess = true;
    }

    public function newTransaction(): void
    {
        $this->showSuccess = false;
        $this->lastTransaksi = null;
    }

    #[Computed]
    public function subtotal(): float
    {
        return collect($this->cart)->sum('subtotal');
    }

    #[Computed]
    public function totalDiskonProduk(): float
    {
        return collect($this->cart)->sum(fn (array $item) => $item['diskon_per_item'] * $item['qty']);
    }

    #[Computed]
    public function diskonKeranjangValue(): float
    {
        if (! $this->diskonKeranjangAktif) {
            return 0;
        }

        $input = (float) $this->diskonKeranjangNominal;
        return $this->diskonKeranjangTipe === 'persen' ? ($input / 100) * $this->subtotal : $input;
    }

    #[Computed]
    public function subtotalSetelahDiskon(): float
    {
        return max(0, $this->subtotal - $this->diskonKeranjangValue);
    }

    #[Computed]
    public function pajakAktif(): ?Pajak
    {
        return Pajak::getAktif();
    }

    #[Computed]
    public function totalPajak(): float
    {
        if (! $this->applyTax) {
            return 0;
        }

        $pajak = $this->pajakAktif;
        if (! $pajak) {
            return 0;
        }

        return round($this->subtotalSetelahDiskon * ($pajak->persentase / 100), 2);
    }

    #[Computed]
    public function grandTotal(): float
    {
        return $this->subtotalSetelahDiskon + $this->totalPajak;
    }

    #[Computed]
    public function kembalian(): float
    {
        $bayar = (float) $this->bayar;

        return max(0, $bayar - $this->grandTotal);
    }

    #[Computed]
    public function kategoris()
    {
        return Kategori::orderBy('nama')->get();
    }

    #[Computed]
    public function produks()
    {
        return Produk::query()
            ->with(['kategori', 'varians.satuan'])
            ->when($this->search, function (Builder $query) {
                $query->where(function (Builder $q) {
                    $q->where('nama_produk', 'like', "%{$this->search}%")
                        ->orWhereHas('varians', fn (Builder $v) => $v->where('sku', 'like', "%{$this->search}%"));
                });
            })
            ->when($this->kategoriFilter, fn (Builder $query) => $query->where('kategori_id', $this->kategoriFilter))
            ->orderBy('nama_produk')
            ->get();
    }
}; ?>

{{-- Main POS Layout --}}
@if ($showSuccess && $lastTransaksi)
    {{-- ==================== SUCCESS VIEW ==================== --}}
    <div class="flex h-screen items-center justify-center bg-zinc-50 dark:bg-zinc-900 p-6">
        <div class="w-full max-w-md rounded-3xl bg-white p-8 text-center shadow-xl dark:bg-zinc-800">
            <div class="mx-auto mb-6 flex size-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
                <flux:icon name="check-circle" class="size-12 text-emerald-600 dark:text-emerald-400" />
            </div>
            
            <h2 class="mb-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Transaksi Berhasil!') }}</h2>
            <p class="mb-8 text-zinc-500">{{ $lastTransaksi->no_referensi }}</p>

            <div class="mb-8 rounded-2xl bg-zinc-50 p-6 dark:bg-zinc-700/50">
                <p class="mb-1 text-sm font-medium text-zinc-500">{{ __('Kembalian') }}</p>
                <p class="text-4xl font-bold text-emerald-600 dark:text-emerald-400">
                    Rp {{ number_format($lastTransaksi->kembalian, 0, ',', '.') }}
                </p>
            </div>

            <div class="mb-8 space-y-2 text-sm">
                <div class="flex justify-between text-zinc-500">
                    <span>{{ __('Total Tagihan') }}</span>
                    <span class="font-medium text-zinc-900 dark:text-white">Rp {{ number_format($lastTransaksi->grand_total, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between text-zinc-500">
                    <span>{{ __('Tunai') }}</span>
                    <span class="font-medium text-zinc-900 dark:text-white">Rp {{ number_format($lastTransaksi->bayar, 0, ',', '.') }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <button
                    wire:click="newTransaction"
                    class="rounded-xl border border-zinc-200 py-3.5 font-semibold text-zinc-700 transition hover:bg-zinc-50 active:scale-95 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-700"
                >
                    {{ __('Transaksi Baru') }}
                </button>
                <button
                    class="flex items-center justify-center gap-2 rounded-xl bg-emerald-600 py-3.5 font-semibold text-white transition hover:bg-emerald-700 active:scale-95"
                    onclick="window.print()"
                >
                    <flux:icon name="printer" class="size-5" />
                    {{ __('Cetak Struk') }}
                </button>
            </div>
        </div>
    </div>
@elseif ($showPayment)
    {{-- ==================== PAYMENT VIEW ==================== --}}
    <div class="flex h-screen flex-col bg-zinc-50 dark:bg-zinc-900 lg:flex-row">
        {{-- Left: Order Summary --}}
        <div class="hidden lg:flex w-full flex-col border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800 lg:w-96 lg:border-b-0 lg:border-r">
            <div class="flex items-center gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <button wire:click="closePayment" class="text-zinc-400 transition hover:text-zinc-700 dark:hover:text-white">
                    <flux:icon name="arrow-left" class="size-5" />
                </button>
                <flux:heading size="lg">{{ __('Ringkasan Pesanan') }}</flux:heading>
            </div>
            <div class="flex-1 overflow-y-auto">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                    @foreach ($cart as $item)
                        <div class="px-5 py-3">
                            <div class="flex justify-between">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $item['nama_produk'] }}
                                    @if ($item['nama_varian'] !== 'Default') <span class="text-zinc-400">({{ $item['nama_varian'] }})</span> @endif
                                </p>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</p>
                            </div>
                            <p class="text-xs text-zinc-400">{{ $item['qty'] }}x Rp {{ number_format($item['harga'] - $item['diskon_per_item'], 0, ',', '.') }}</p>
                            @if ($item['diskon_aktif'])
                                <p class="text-xs text-emerald-500">{{ $item['diskon_nama'] }}: -Rp {{ number_format($item['diskon_per_item'], 0, ',', '.') }}/item</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="space-y-1 border-t border-zinc-200 px-5 py-4 text-sm dark:border-zinc-700">
                <div class="flex justify-between text-zinc-500">
                    <span>{{ __('Subtotal') }}</span>
                    <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                </div>
                @if ($this->totalDiskonProduk > 0)
                    <div class="flex justify-between text-emerald-600">
                        <span>{{ __('Diskon Produk') }}</span>
                        <span>-Rp {{ number_format($this->totalDiskonProduk, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if ($this->diskonKeranjangValue > 0)
                    <div class="flex justify-between text-emerald-600">
                        <span>{{ __('Diskon Keranjang') }}</span>
                        <span>-Rp {{ number_format($this->diskonKeranjangValue, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if ($this->pajakAktif)
                    <div class="flex justify-between items-center text-zinc-500">
                        <div class="flex items-center gap-2">
                            <span>{{ $this->pajakAktif->nama }} ({{ $this->pajakAktif->persentase }}%)</span>
                            <flux:switch wire:model.live="applyTax" size="sm" />
                        </div>
                        <span>Rp {{ number_format($this->totalPajak, 0, ',', '.') }}</span>
                    </div>
                @endif
                <flux:separator />
                <div class="flex justify-between text-lg font-bold text-zinc-900 dark:text-white">
                    <span>{{ __('Total') }}</span>
                    <span>Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Right: Calculator --}}
        <div class="min-h-0 flex flex-1 flex-col items-center overflow-y-auto p-4 lg:justify-center lg:p-6">
            {{-- Mobile Back Button --}}
            <div class="mb-4 flex w-full max-w-md items-center gap-3 lg:hidden">
                <button wire:click="closePayment" class="text-zinc-400 transition hover:text-zinc-700 dark:hover:text-white">
                    <flux:icon name="arrow-left" class="size-5" />
                </button>
                <flux:heading size="lg">{{ __('Pembayaran') }}</flux:heading>
            </div>

            <div class="w-full max-w-md space-y-6">
                {{-- Total Tagihan --}}
                <div class="rounded-2xl bg-white p-6 text-center shadow-sm dark:bg-zinc-800">
                    <p class="mb-1 text-sm font-medium text-zinc-500">{{ __('Total Tagihan') }}</p>
                    <p class="text-4xl font-bold text-zinc-900 dark:text-white">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</p>
                </div>

                {{-- Metode Pembayaran --}}
                <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-zinc-800">
                    <p class="mb-2 text-sm font-medium text-zinc-500">{{ __('Metode Pembayaran') }}</p>
                    <div class="flex gap-2">
                        <button
                            wire:click="$set('metodePembayaran', 'cash')"
                            @class([
                                'flex-1 rounded-xl border-2 px-4 py-3 text-center text-sm font-semibold transition',
                                'border-emerald-500 bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-300' => $metodePembayaran === 'cash',
                                'border-zinc-200 text-zinc-500 hover:border-zinc-300 dark:border-zinc-600' => $metodePembayaran !== 'cash',
                            ])
                        >
                            <flux:icon name="banknotes" class="mx-auto mb-1 size-6" />
                            {{ __('Cash') }}
                        </button>
                    </div>
                </div>

                {{-- Input Display --}}
                <div class="rounded-2xl bg-white p-4 shadow-sm dark:bg-zinc-800">
                    <p class="mb-1 text-sm font-medium text-zinc-500">{{ __('Jumlah Bayar') }}</p>
                    <div class="flex items-baseline gap-1">
                        <span class="text-lg text-zinc-400">Rp</span>
                        <span class="text-3xl font-bold text-zinc-900 dark:text-white">
                            {{ $bayar ? number_format((float) $bayar, 0, ',', '.') : '0' }}
                        </span>
                    </div>
                    @if ((float) $bayar >= $this->grandTotal && (float) $bayar > 0)
                        <p class="mt-2 text-sm font-semibold text-emerald-600">{{ __('Kembalian') }}: Rp {{ number_format($this->kembalian, 0, ',', '.') }}</p>
                    @endif
                    <flux:error name="bayar" />
                </div>

                {{-- Quick Nominal Shortcuts --}}
                <div class="grid grid-cols-4 gap-2">
                    @php
                        $gt = $this->grandTotal;
                        $shortcuts = collect([
                            ceil($gt / 1000) * 1000,
                            ceil($gt / 5000) * 5000,
                            ceil($gt / 10000) * 10000,
                            ceil($gt / 50000) * 50000,
                            50000, 100000, 200000, 500000,
                        ])->unique()->filter(fn ($a) => $a >= $gt)->sort()->take(8)->values();
                    @endphp
                    @foreach ($shortcuts as $amount)
                        <button
                            wire:click="setBayar('{{ (int) $amount }}')"
                            class="rounded-xl border border-zinc-200 px-2 py-2.5 text-xs font-semibold text-zinc-700 transition hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700 active:scale-95 dark:border-zinc-600 dark:text-zinc-300 dark:hover:bg-emerald-900/20"
                        >
                            {{ number_format($amount, 0, ',', '.') }}
                        </button>
                    @endforeach
                </div>

                {{-- Numpad --}}
                <div class="grid grid-cols-3 gap-2">
                    @foreach (['1','2','3','4','5','6','7','8','9','00','0'] as $key)
                        <button
                            wire:click="appendBayar('{{ $key }}')"
                            class="rounded-xl bg-white py-4 text-xl font-semibold text-zinc-900 shadow-sm transition hover:bg-zinc-50 active:scale-95 dark:bg-zinc-800 dark:text-white dark:hover:bg-zinc-700"
                        >
                            {{ $key }}
                        </button>
                    @endforeach
                    <button
                        wire:click="backspaceBayar"
                        class="flex items-center justify-center rounded-xl bg-white py-4 shadow-sm transition hover:bg-zinc-50 active:scale-95 dark:bg-zinc-800 dark:hover:bg-zinc-700"
                    >
                        <flux:icon name="backspace" class="size-6 text-zinc-500" />
                    </button>
                </div>

                {{-- Process Button --}}
                <button
                    wire:click="processPayment"
                    @class([
                        'w-full rounded-2xl py-4 text-lg font-bold transition active:scale-[0.98]',
                        'bg-emerald-600 text-white hover:bg-emerald-700' => (float) $bayar >= $this->grandTotal && (float) $bayar > 0,
                        'bg-zinc-200 text-zinc-400 cursor-not-allowed dark:bg-zinc-700 dark:text-zinc-500' => (float) $bayar < $this->grandTotal || (float) $bayar <= 0,
                    ])
                    @if ((float) $bayar < $this->grandTotal || (float) $bayar <= 0) disabled @endif
                >
                    {{ __('Proses Pembayaran') }}
                </button>
            </div>
        </div>
    </div>
@else
    {{-- ==================== MAIN POS VIEW ==================== --}}
    <div class="flex h-screen flex-col lg:flex-row" x-data="{ showVarianPicker: false, varianList: [], produkNama: '', showMobileCart: false }">
        {{-- LEFT: Product Area --}}
        <div class="flex flex-1 flex-col overflow-hidden">
            {{-- Top Bar --}}
            <div class="flex flex-col border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                {{-- Top Row: Menu, Outlet, Orders --}}
                <div class="flex items-center justify-between px-4 py-3">
                    <div class="flex items-center gap-3">
                        <flux:button variant="ghost" icon="bars-3" :href="route('dashboard')" wire:navigate size="sm" />
                        <span class="text-lg font-bold text-zinc-700 dark:text-zinc-200 uppercase tracking-wide">
                            {{ \App\Models\Outlet::first()->nama ?? 'POS KASIR' }}
                        </span>
                    </div>

                    <flux:button :href="route('kasir.riwayat')" wire:navigate variant="ghost" size="sm">
                        {{ __('Daftar Pesanan') }}
                    </flux:button>
                </div>

                {{-- Bottom Row: Categories, Search --}}
                {{-- Search (mobile: full width) --}}
                <div class="border-t border-zinc-100 px-4 py-2 dark:border-zinc-700 lg:hidden">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Cari...') }}" clearable size="sm" />
                </div>

                <div class="flex items-center gap-4 border-t border-zinc-100 px-4 py-3 dark:border-zinc-700">
                    <div class="flex flex-1 items-center gap-2 overflow-x-auto">
                        <flux:button
                            size="sm"
                            :variant="$kategoriFilter === '' ? 'primary' : 'ghost'"
                            wire:click="$set('kategoriFilter', '')"
                        >
                            {{ __('Semua') }}
                        </flux:button>

                        @foreach ($this->kategoris as $kategori)
                            <flux:button
                                size="sm"
                                :variant="$kategoriFilter === (string) $kategori->id ? 'primary' : 'ghost'"
                                wire:click="$set('kategoriFilter', '{{ $kategori->id }}')"
                                wire:key="kat-{{ $kategori->id }}"
                            >
                                {{ $kategori->nama }}
                            </flux:button>
                        @endforeach
                    </div>

                    <div class="hidden w-64 shrink-0 lg:block">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Cari...') }}" clearable size="sm" />
                    </div>
                </div>
            </div>

            {{-- Product Grid --}}
            <div class="flex-1 overflow-y-auto p-4 pb-24 lg:pb-4">
                @php
                    $cartQtyByVarian = collect($cart)->groupBy('varian_id')->map(fn ($items) => $items->sum('qty'));
                @endphp
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @forelse ($this->produks as $produk)
                        @if ($produk->varians->count() === 1)
                            @php
                                $singleVarianQty = $cartQtyByVarian->get($produk->varians->first()->id, 0);
                            @endphp
                            <button
                                wire:click="openDetail({{ $produk->varians->first()->id }})"
                                wire:key="produk-{{ $produk->id }}"
                                class="group relative flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-md active:scale-[0.97] dark:border-zinc-700 dark:bg-zinc-800"
                            >
                                <div class="relative aspect-square w-full overflow-hidden bg-zinc-100 dark:bg-zinc-700">
                                    @if ($produk->foto_produk)
                                        <img src="{{ Storage::url($produk->foto_produk) }}" alt="{{ $produk->nama_produk }}" class="size-full object-cover transition group-hover:scale-105">
                                    @else
                                        <div class="flex size-full items-center justify-center">
                                            <flux:icon name="photo" class="size-10 text-zinc-300 dark:text-zinc-600" />
                                        </div>
                                    @endif
                                    @if ($produk->varians->first()->stok !== null && $produk->varians->first()->stok <= 0)
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/50">
                                            <span class="rounded-full bg-red-600 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white shadow">
                                                {{ __('Stok Habis') }}
                                            </span>
                                        </div>
                                    @elseif ($singleVarianQty > 0)
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                            <div class="flex size-10 items-center justify-center rounded-full bg-emerald-600 text-lg font-bold text-white shadow-lg ring-2 ring-white dark:ring-zinc-800">
                                                {{ $singleVarianQty }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="space-y-1 p-3 text-left">
                                    <p class="text-sm font-medium leading-tight text-zinc-900 dark:text-white line-clamp-2">{{ $produk->nama_produk }}</p>
                                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                        Rp {{ number_format($produk->varians->first()->harga_jual, 0, ',', '.') }}
                                    </p>
                                </div>
                            </button>
                        @else
                            <button
                                x-on:click="
                                    produkNama = @js($produk->nama_produk);
                                    varianList = @js($produk->varians->map(fn ($v) => ['id' => $v->id, 'nama' => $v->nama_varian, 'harga' => $v->harga_jual, 'satuan' => $v->satuan->nama, 'stok' => $v->stok])->toArray());
                                    showVarianPicker = true;
                                "
                                wire:key="produk-{{ $produk->id }}"
                                class="group relative flex flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white transition hover:shadow-md active:scale-[0.97] dark:border-zinc-700 dark:bg-zinc-800"
                            >
                                @php
                                    $multiVarianQty = $produk->varians->sum(fn ($v) => $cartQtyByVarian->get($v->id, 0));
                                @endphp
                                <div class="relative aspect-square w-full overflow-hidden bg-zinc-100 dark:bg-zinc-700">
                                    @if ($produk->foto_produk)
                                        <img src="{{ Storage::url($produk->foto_produk) }}" alt="{{ $produk->nama_produk }}" class="size-full object-cover transition group-hover:scale-105">
                                    @else
                                        <div class="flex size-full items-center justify-center">
                                            <flux:icon name="photo" class="size-10 text-zinc-300 dark:text-zinc-600" />
                                        </div>
                                    @endif
                                    @if ($multiVarianQty > 0)
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20">
                                            <div class="flex size-10 items-center justify-center rounded-full bg-emerald-600 text-lg font-bold text-white shadow-lg ring-2 ring-white dark:ring-zinc-800">
                                                {{ $multiVarianQty }}
                                            </div>
                                        </div>
                                    @endif
                                </div>
                                <div class="absolute right-2 top-2">
                                    <flux:badge size="sm" color="zinc">{{ $produk->varians->count() }} varian</flux:badge>
                                </div>
                                <div class="space-y-1 p-3 text-left">
                                    <p class="text-sm font-medium leading-tight text-zinc-900 dark:text-white line-clamp-2">{{ $produk->nama_produk }}</p>
                                    @php
                                        $min = $produk->varians->min('harga_jual');
                                        $max = $produk->varians->max('harga_jual');
                                    @endphp
                                    <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                        @if ($min == $max)
                                            Rp {{ number_format($min, 0, ',', '.') }}
                                        @else
                                            Rp {{ number_format($min, 0, ',', '.') }} - {{ number_format($max, 0, ',', '.') }}
                                        @endif
                                    </p>
                                </div>
                            </button>
                        @endif
                    @empty
                        <div class="col-span-full flex flex-col items-center justify-center py-20">
                            <flux:icon name="cube" class="mb-2 size-12 text-zinc-300 dark:text-zinc-600" />
                            <flux:text class="text-zinc-400">{{ __('Tidak ada produk ditemukan.') }}</flux:text>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- RIGHT: Cart Sidebar (Desktop) --}}
        <div class="hidden lg:flex w-80 flex-col border-l border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800 lg:w-96">
            {{-- Cart Header --}}
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <flux:icon name="user-circle" class="size-5 shrink-0 text-zinc-400" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-zinc-500">{{ $namaPelanggan }}</p>
                        </div>
                    </div>
                </div>
                <flux:modal.trigger name="edit-pelanggan-modal">
                    <flux:button variant="ghost" size="sm" icon="pencil-square" />
                </flux:modal.trigger>
            </div>

            {{-- Cart Body --}}
            <div class="flex-1 overflow-y-auto">
                @if (empty($cart))
                    <div class="flex h-full flex-col items-center justify-center px-6 text-center">
                        <div class="mb-4 flex size-24 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                            <flux:icon name="shopping-cart" class="size-12 text-zinc-300 dark:text-zinc-500" />
                        </div>
                        <flux:heading size="lg" class="mb-1">{{ __('Keranjang Kosong') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-400">{{ __('Silakan masukkan pesanan dari pelanggan') }}</flux:text>
                    </div>
                @else
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                        @foreach ($cart as $index => $item)
                            <div wire:key="cart-{{ $index }}" class="px-4 py-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white line-clamp-1">{{ $item['nama_produk'] }}</p>
                                        @if ($item['nama_varian'] !== 'Default')
                                            <p class="text-xs text-zinc-500">{{ $item['nama_varian'] }}</p>
                                        @endif
                                        <p class="mt-0.5 text-xs text-zinc-400">
                                            @if ($item['diskon_aktif'])
                                                <span class="line-through">Rp {{ number_format($item['harga'], 0, ',', '.') }}</span>
                                                <span class="text-emerald-500 font-medium">Rp {{ number_format($item['harga'] - $item['diskon_per_item'], 0, ',', '.') }}</span>
                                            @else
                                                Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                            @endif
                                        </p>
                                        @if ($item['diskon_aktif'])
                                            <p class="text-xs text-emerald-500">🏷️ {{ $item['diskon_nama'] }}</p>
                                        @endif
                                    </div>
                                    <button wire:click="removeItem({{ $index }})" class="shrink-0 text-zinc-300 transition hover:text-red-500 dark:text-zinc-600">
                                        <flux:icon name="x-mark" class="size-4" />
                                    </button>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <div class="flex items-center gap-1 rounded-lg border border-zinc-200 dark:border-zinc-600">
                                        <button wire:click="decrementQty({{ $index }})" class="px-2.5 py-1 text-zinc-500 transition hover:text-zinc-900 dark:hover:text-white">
                                            <flux:icon name="minus" class="size-3.5" />
                                        </button>
                                        <span class="min-w-[2rem] text-center text-sm font-medium text-zinc-900 dark:text-white">{{ $item['qty'] }}</span>
                                        <button wire:click="incrementQty({{ $index }})" class="px-2.5 py-1 text-zinc-500 transition hover:text-zinc-900 dark:hover:text-white">
                                            <flux:icon name="plus" class="size-3.5" />
                                        </button>
                                    </div>
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Cart Footer --}}
            <div class="border-t border-zinc-200 dark:border-zinc-700">
                @if (!empty($cart))
                    {{-- Action Buttons --}}
                    <div class="flex items-center justify-around border-b border-zinc-100 px-2 py-2 dark:border-zinc-700">
                        <button wire:click="clearCart" wire:confirm="{{ __('Yakin ingin mengosongkan keranjang?') }}" class="flex flex-col items-center gap-1 rounded-lg px-3 py-1.5 text-zinc-500 transition hover:bg-zinc-100 hover:text-red-500 dark:hover:bg-zinc-700">
                            <flux:icon name="trash" class="size-5" />
                            <span class="text-xs">{{ __('Hapus') }}</span>
                        </button>
                        <button
                            wire:click="$set('showDiskonKeranjangSidebar', true)"
                            @class([
                                'flex flex-col items-center gap-1 rounded-lg px-3 py-1.5 transition hover:bg-zinc-100 dark:hover:bg-zinc-700',
                                'text-emerald-600' => $diskonKeranjangAktif,
                                'text-zinc-500' => !$diskonKeranjangAktif,
                            ])
                        >
                            <flux:icon name="receipt-percent" class="size-5" />
                            <span class="text-xs">{{ __('Diskon') }}</span>
                        </button>
                    </div>

                    {{-- Summary --}}
                    <div class="space-y-1 px-4 py-3 text-sm">
                        <div class="flex justify-between text-zinc-500">
                            <span>{{ __('Subtotal') }}</span>
                            <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if ($this->totalDiskonProduk > 0)
                            <div class="flex justify-between text-emerald-600">
                                <span>{{ __('Diskon Produk') }}</span>
                                <span>-Rp {{ number_format($this->totalDiskonProduk, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if ($this->diskonKeranjangValue > 0)
                            <div class="flex justify-between text-emerald-600">
                                <span>{{ __('Diskon Keranjang') }}</span>
                                <span>-Rp {{ number_format($this->diskonKeranjangValue, 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if ($this->pajakAktif)
                            <div class="flex justify-between items-center text-zinc-500">
                                <div class="flex items-center gap-2">
                                    <span>{{ $this->pajakAktif->nama }} ({{ $this->pajakAktif->persentase }}%)</span>
                                    <flux:switch wire:model.live="applyTax" size="sm" />
                                </div>
                                <span>Rp {{ number_format($this->totalPajak, 0, ',', '.') }}</span>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Pay Button --}}
                <div class="p-3">
                    <button
                        wire:click="openPayment"
                        @class([
                            'flex w-full items-center justify-between rounded-xl px-5 py-4 text-white font-semibold text-lg transition active:scale-[0.98]',
                            'bg-emerald-600 hover:bg-emerald-700' => !empty($cart),
                            'bg-zinc-300 dark:bg-zinc-600 cursor-not-allowed' => empty($cart),
                        ])
                        @if(empty($cart)) disabled @endif
                    >
                        <span>{{ __('Bayar') }}</span>
                        <span>Rp {{ number_format($this->grandTotal, 0, ',', '.') }} ›</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ==================== MOBILE FLOATING CART BUTTON ==================== --}}
        @if (!empty($cart))
            <div class="fixed bottom-4 left-4 right-4 z-40 lg:hidden">
                <button
                    x-on:click="showMobileCart = true"
                    class="flex w-full items-center justify-between rounded-2xl bg-emerald-600 px-5 py-4 text-white shadow-lg shadow-emerald-900/30 transition active:scale-[0.98]"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex size-8 items-center justify-center rounded-full bg-white/20">
                            <flux:icon name="shopping-cart" class="size-5" />
                        </div>
                        <span class="font-semibold">{{ count($cart) }} {{ __('item') }}</span>
                    </div>
                    <span class="text-lg font-bold">Rp {{ number_format($this->grandTotal, 0, ',', '.') }} ›</span>
                </button>
            </div>
        @endif

        {{-- ==================== MOBILE CART SLIDE-UP ==================== --}}
        <div
            x-show="showMobileCart"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 lg:hidden"
            x-cloak
        >
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="showMobileCart = false"></div>

            {{-- Cart Panel --}}
            <div
                x-show="showMobileCart"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="translate-y-full"
                x-transition:enter-end="translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-y-0"
                x-transition:leave-end="translate-y-full"
                class="absolute inset-0 flex flex-col bg-white shadow-2xl dark:bg-zinc-800"
            >
                {{-- Cart Header --}}
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <div class="flex items-center gap-2">
                        <flux:icon name="shopping-cart" class="size-5 text-zinc-400" />
                        <flux:heading size="lg">{{ __('Keranjang') }} ({{ count($cart) }})</flux:heading>
                    </div>
                    <button x-on:click="showMobileCart = false" class="text-zinc-400 transition hover:text-zinc-700 dark:hover:text-white">
                        <flux:icon name="x-mark" class="size-5" />
                    </button>
                </div>

                {{-- Cart Body (scrollable area includes items + summary) --}}
                <div class="min-h-0 flex-1 overflow-y-auto">
                    @if (empty($cart))
                        <div class="flex h-full flex-col items-center justify-center px-6 py-12 text-center">
                            <div class="mb-4 flex size-20 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                                <flux:icon name="shopping-cart" class="size-10 text-zinc-300 dark:text-zinc-500" />
                            </div>
                            <flux:heading size="lg" class="mb-1">{{ __('Keranjang Kosong') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-400">{{ __('Silakan masukkan pesanan dari pelanggan') }}</flux:text>
                        </div>
                    @else
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-700">
                            @foreach ($cart as $index => $item)
                                <div wire:key="mobile-cart-{{ $index }}" class="px-4 py-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-zinc-900 dark:text-white line-clamp-1">{{ $item['nama_produk'] }}</p>
                                            @if ($item['nama_varian'] !== 'Default')
                                                <p class="text-xs text-zinc-500">{{ $item['nama_varian'] }}</p>
                                            @endif
                                            <p class="mt-0.5 text-xs text-zinc-400">
                                                @if ($item['diskon_aktif'])
                                                    <span class="line-through">Rp {{ number_format($item['harga'], 0, ',', '.') }}</span>
                                                    <span class="text-emerald-500 font-medium">Rp {{ number_format($item['harga'] - $item['diskon_per_item'], 0, ',', '.') }}</span>
                                                @else
                                                    Rp {{ number_format($item['harga'], 0, ',', '.') }}
                                                @endif
                                            </p>
                                            @if ($item['diskon_aktif'])
                                                <p class="text-xs text-emerald-500">🏷️ {{ $item['diskon_nama'] }}</p>
                                            @endif
                                        </div>
                                        <button wire:click="removeItem({{ $index }})" class="shrink-0 text-zinc-300 transition hover:text-red-500 dark:text-zinc-600">
                                            <flux:icon name="x-mark" class="size-4" />
                                        </button>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between">
                                        <div class="flex items-center gap-1 rounded-lg border border-zinc-200 dark:border-zinc-600">
                                            <button wire:click="decrementQty({{ $index }})" class="px-2.5 py-1 text-zinc-500 transition hover:text-zinc-900 dark:hover:text-white">
                                                <flux:icon name="minus" class="size-3.5" />
                                            </button>
                                            <span class="min-w-[2rem] text-center text-sm font-medium text-zinc-900 dark:text-white">{{ $item['qty'] }}</span>
                                            <button wire:click="incrementQty({{ $index }})" class="px-2.5 py-1 text-zinc-500 transition hover:text-zinc-900 dark:hover:text-white">
                                                <flux:icon name="plus" class="size-3.5" />
                                            </button>
                                        </div>
                                        <p class="text-sm font-semibold text-zinc-900 dark:text-white">Rp {{ number_format($item['subtotal'], 0, ',', '.') }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Cart Footer: Action buttons + Pay (always visible) --}}
                @if (!empty($cart))
                    <div class="shrink-0 border-t border-zinc-200 dark:border-zinc-700">
                        {{-- Action Buttons --}}
                        <div class="flex items-center justify-around border-b border-zinc-100 px-2 py-1.5 dark:border-zinc-700">
                            <button wire:click="clearCart" wire:confirm="{{ __('Yakin ingin mengosongkan keranjang?') }}" class="flex flex-col items-center gap-0.5 rounded-lg px-3 py-1 text-zinc-500 transition hover:bg-zinc-100 hover:text-red-500 dark:hover:bg-zinc-700">
                                <flux:icon name="trash" class="size-4" />
                                <span class="text-[10px]">{{ __('Hapus') }}</span>
                            </button>
                            <button
                                wire:click="$set('showDiskonKeranjangSidebar', true)"
                                x-on:click="showMobileCart = false"
                                @class([
                                    'flex flex-col items-center gap-0.5 rounded-lg px-3 py-1 transition hover:bg-zinc-100 dark:hover:bg-zinc-700',
                                    'text-emerald-600' => $diskonKeranjangAktif,
                                    'text-zinc-500' => !$diskonKeranjangAktif,
                                ])
                            >
                                <flux:icon name="receipt-percent" class="size-4" />
                                <span class="text-[10px]">{{ __('Diskon') }}</span>
                            </button>
                        </div>

                        {{-- Pay Button --}}
                        <div class="p-2">
                            <button
                                wire:click="openPayment"
                                x-on:click="showMobileCart = false"
                                @class([
                                    'flex w-full items-center justify-between rounded-xl px-5 py-3 text-white font-semibold text-base transition active:scale-[0.98]',
                                    'bg-emerald-600 hover:bg-emerald-700' => !empty($cart),
                                    'bg-zinc-300 dark:bg-zinc-600 cursor-not-allowed' => empty($cart),
                                ])
                                @if(empty($cart)) disabled @endif
                            >
                                <span>{{ __('Bayar') }}</span>
                                <span>Rp {{ number_format($this->grandTotal, 0, ',', '.') }} ›</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ==================== PRODUCT DETAIL SLIDE-OVER ==================== --}}
        @if ($showDetail)
            <div
                class="fixed inset-0 z-50 flex justify-end"
                x-data="{ open: true }"
                x-show="open"
                x-on:keydown.escape.window="$wire.closeDetail()"
            >
                {{-- Backdrop --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                    wire:click="closeDetail"
                ></div>

                {{-- Slider Panel --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="relative z-10 flex w-full max-w-sm flex-col bg-white shadow-2xl dark:bg-zinc-800"
                >
                    {{-- Header --}}
                    <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        <flux:heading size="lg">{{ __('Detail Produk') }}</flux:heading>
                        <button wire:click="closeDetail" class="text-zinc-400 transition hover:text-zinc-700 dark:hover:text-white">
                            <flux:icon name="x-mark" class="size-5" />
                        </button>
                    </div>

                    {{-- Body --}}
                    <div class="flex-1 overflow-y-auto px-5 py-4">
                        {{-- Product Info --}}
                        <div class="flex items-center gap-4 mb-6">
                            @if ($detailFoto)
                                <img src="{{ $detailFoto }}" alt="{{ $detailNamaProduk }}" class="size-16 rounded-xl object-cover shadow-sm">
                            @else
                                <div class="flex size-16 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-700">
                                    <flux:icon name="photo" class="size-8 text-zinc-300" />
                                </div>
                            @endif
                            <div>
                                <p class="font-semibold text-zinc-900 dark:text-white">{{ $detailNamaProduk }}</p>
                                @if ($detailNamaVarian !== 'Default')
                                    <p class="text-xs text-zinc-400">{{ $detailNamaVarian }}</p>
                                @endif
                                <p class="text-lg font-bold text-emerald-600 dark:text-emerald-400">Rp {{ number_format($detailHarga, 0, ',', '.') }}</p>
                            </div>
                        </div>

                        {{-- Catatan --}}
                        <flux:field class="mb-5">
                            <flux:label>{{ __('Catatan Tambahan') }}</flux:label>
                            <flux:input wire:model="detailCatatan" placeholder="{{ __('Catatan untuk item ini...') }}" />
                        </flux:field>

                        {{-- Diskon Toggle --}}
                        <div class="mb-4">
                            <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-700/50">
                                <div>
                                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Diskon') }}</span>
                                    @if (count($detailAvailablePromos) > 0)
                                        <p class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                            {{ __('Ada :count promo yang aktif', ['count' => count($detailAvailablePromos)]) }}
                                        </p>
                                    @else
                                        <p class="text-xs text-zinc-400">
                                            {{ __('Tidak ada promo yang aktif') }}
                                        </p>
                                    @endif
                                </div>
                                <flux:switch wire:model.live="detailDiskon" />
                            </div>
                        </div>

                        {{-- Available Promos --}}
                        @if ($detailDiskon)
                            <div class="space-y-2 mb-5">
                                @forelse ($detailAvailablePromos as $promo)
                                    <button
                                        wire:click="selectPromo({{ $promo['id'] }})"
                                        @class([
                                            'flex w-full items-center justify-between rounded-xl border-2 px-4 py-3 text-left transition',
                                            'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20' => $detailPromoId === $promo['id'],
                                            'border-zinc-200 hover:border-zinc-300 dark:border-zinc-600' => $detailPromoId !== $promo['id'],
                                        ])
                                    >
                                        <div>
                                            <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $promo['nama'] }}</p>
                                            <p class="text-xs text-zinc-400">{{ __('Minimal Harga Diskon') }}: Rp {{ number_format($promo['minimal_harga_jual'], 0, ',', '.') }}</p>
                                        </div>
                                        @if ($detailPromoId === $promo['id'])
                                            <flux:icon name="check-circle" class="size-5 text-emerald-500" />
                                        @endif
                                    </button>
                                @empty
                                    <div class="rounded-xl bg-amber-50 px-4 py-3 text-center dark:bg-amber-900/20">
                                        <p class="text-sm text-amber-600 dark:text-amber-300">{{ __('Tidak ada promo tersedia untuk produk ini.') }}</p>
                                    </div>
                                @endforelse

                                @if ($detailPromoId)
                                    <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                                        <p class="mb-3 text-sm font-medium text-zinc-900 dark:text-white">{{ __('Input Diskon Manual') }}</p>
                                        <div class="flex gap-2">
                                            <div class="w-1/3">
                                                <flux:select wire:model.live="detailDiskonTipe">
                                                    <option value="nominal">Rp</option>
                                                    <option value="persen">%</option>
                                                </flux:select>
                                            </div>
                                            <div class="w-2/3">
                                                <flux:input wire:model.live.debounce.300ms="detailDiskonInput" type="number" placeholder="0" min="0" />
                                            </div>
                                        </div>
                                        <flux:error name="detailDiskonInput" class="mt-2" />
                                        
                                        @if ($detailDiskonPerItem > 0 && !$errors->has('detailDiskonInput'))
                                            <div class="mt-3 rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/20">
                                                <p class="text-sm text-emerald-700 dark:text-emerald-300">
                                                    {{ __('Diskon diterapkan') }}: <strong>-Rp {{ number_format($detailDiskonPerItem, 0, ',', '.') }}</strong>
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>

                    {{-- Footer --}}
                    <div class="border-t border-zinc-200 px-5 py-4 dark:border-zinc-700">
                        {{-- Qty Selector --}}
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Jumlah') }}</span>
                            <div class="flex items-center gap-3">
                                <button wire:click="detailDecrement"
                                    class="flex size-9 items-center justify-center rounded-full border-2 border-emerald-500 text-emerald-600 transition hover:bg-emerald-50 active:scale-90 dark:hover:bg-emerald-900/30">
                                    <flux:icon name="minus" class="size-4" />
                                </button>
                                <span class="min-w-[2.5rem] text-center text-xl font-bold text-zinc-900 dark:text-white">{{ $detailQty }}</span>
                                <button wire:click="detailIncrement"
                                    class="flex size-9 items-center justify-center rounded-full border-2 border-emerald-500 text-emerald-600 transition hover:bg-emerald-50 active:scale-90 dark:hover:bg-emerald-900/30">
                                    <flux:icon name="plus" class="size-4" />
                                </button>
                            </div>
                        </div>

                        {{-- Subtotal --}}
                        @php
                            $detailSubtotal = ($detailHarga - $detailDiskonPerItem) * $detailQty;
                        @endphp
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-sm text-zinc-500">{{ __('Subtotal') }}</span>
                            <span class="text-lg font-bold text-zinc-900 dark:text-white">Rp {{ number_format($detailSubtotal, 0, ',', '.') }}</span>
                        </div>

                        {{-- Add Button --}}
                        @if ($this->detailStok !== null && $this->detailStok <= 0)
                            <div class="mb-3 rounded-xl bg-red-50 p-3 text-center text-xs font-semibold text-red-600 dark:bg-red-950/40 dark:text-red-400">
                                {{ __('Stok produk ini habis dan tidak dapat ditambahkan ke keranjang.') }}
                            </div>
                            <button
                                disabled
                                class="w-full rounded-xl bg-zinc-300 py-3.5 text-center text-base font-semibold text-zinc-500 cursor-not-allowed dark:bg-zinc-700 dark:text-zinc-400"
                            >
                                {{ __('Stok Habis') }}
                            </button>
                        @else
                            <button
                                wire:click="addToCartFromDetail"
                                class="w-full rounded-xl bg-emerald-600 py-3.5 text-center text-base font-semibold text-white transition hover:bg-emerald-700 active:scale-[0.98]"
                            >
                                {{ __('Tambah') }} - Rp {{ number_format($detailSubtotal, 0, ',', '.') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        {{-- Varian Picker Modal (Alpine.js) --}}
        <template x-teleport="body">
            <div x-show="showVarianPicker" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" x-cloak>
                <div x-show="showVarianPicker" x-transition x-on:click.away="showVarianPicker = false" class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-800">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white" x-text="produkNama"></h3>
                        <button x-on:click="showVarianPicker = false" class="text-zinc-400 hover:text-zinc-600">
                            <flux:icon name="x-mark" class="size-5" />
                        </button>
                    </div>
                    <div class="max-h-64 space-y-2 overflow-y-auto">
                        <template x-for="varian in varianList" :key="varian.id">
                            <button
                                x-on:click="if (varian.stok !== null && varian.stok <= 0) return; $wire.openDetail(varian.id); showVarianPicker = false;"
                                :class="varian.stok !== null && varian.stok <= 0 ? 'opacity-50 cursor-not-allowed bg-zinc-100 dark:bg-zinc-800' : 'hover:border-emerald-400 hover:bg-emerald-50 active:scale-[0.98] dark:hover:border-emerald-500 dark:hover:bg-emerald-900/20'"
                                class="flex w-full items-center justify-between rounded-xl border border-zinc-200 px-4 py-3 text-left transition dark:border-zinc-600"
                            >
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white" x-text="varian.nama"></p>
                                    <p class="text-xs text-zinc-400">
                                        <span x-text="varian.satuan"></span>
                                        <template x-if="varian.stok !== null && varian.stok <= 0">
                                            <span class="ml-2 font-semibold text-red-500">(Stok Habis)</span>
                                        </template>
                                        <template x-if="varian.stok !== null && varian.stok > 0">
                                            <span class="ml-2 text-zinc-500" x-text="'Stok: ' + varian.stok"></span>
                                        </template>
                                    </p>
                                </div>
                                <p class="text-sm font-semibold text-emerald-600 dark:text-emerald-400" x-text="'Rp ' + Number(varian.harga).toLocaleString('id-ID')"></p>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        {{-- Diskon Keranjang Sidebar --}}
        @if ($showDiskonKeranjangSidebar)
            <template x-teleport="body">
                <div
                    x-data="{ open: true }"
                    x-show="open"
                    x-on:keydown.escape.window="$wire.set('showDiskonKeranjangSidebar', false)"
                    class="fixed inset-0 z-50 flex justify-end"
                >
                    {{-- Backdrop --}}
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="absolute inset-0 bg-black/40 backdrop-blur-sm"
                        wire:click="$set('showDiskonKeranjangSidebar', false)"
                    ></div>

                    {{-- Slider Panel --}}
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-200"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="relative z-10 flex w-full max-w-sm flex-col bg-white shadow-2xl dark:bg-zinc-800"
                    >
                        {{-- Header --}}
                        <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                            <flux:heading size="lg">{{ __('Diskon Keranjang') }}</flux:heading>
                            <button wire:click="$set('showDiskonKeranjangSidebar', false)" class="text-zinc-400 transition hover:text-zinc-700 dark:hover:text-white">
                                <flux:icon name="x-mark" class="size-5" />
                            </button>
                        </div>

                        {{-- Body --}}
                        <div class="flex-1 overflow-y-auto px-5 py-4">
                            <flux:subheading class="mb-4">{{ __('Terapkan diskon untuk seluruh keranjang') }}</flux:subheading>

                            <div class="flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-700/50">
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Aktifkan Diskon') }}</span>
                                <flux:switch wire:model.live="diskonKeranjangAktif" />
                            </div>

                            @if ($diskonKeranjangAktif)
                                <div class="mt-4 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800">
                                    <p class="mb-3 text-sm font-medium text-zinc-900 dark:text-white">{{ __('Input Diskon Keranjang') }}</p>
                                    <div class="flex gap-2">
                                        <div class="w-1/3">
                                            <flux:select wire:model.live="diskonKeranjangTipe">
                                                <option value="nominal">Rp</option>
                                                <option value="persen">%</option>
                                            </flux:select>
                                        </div>
                                        <div class="w-2/3">
                                            <flux:input wire:model.live.debounce.300ms="diskonKeranjangNominal" type="number" placeholder="0" min="0" />
                                        </div>
                                    </div>
                                    
                                    @if ($this->diskonKeranjangValue > 0)
                                        <div class="mt-3 rounded-lg bg-emerald-50 p-3 dark:bg-emerald-900/20">
                                            <p class="text-sm text-emerald-700 dark:text-emerald-300">
                                                {{ __('Diskon diterapkan') }}: <strong>-Rp {{ number_format($this->diskonKeranjangValue, 0, ',', '.') }}</strong>
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Footer --}}
                        <div class="border-t border-zinc-200 p-5 dark:border-zinc-700">
                            <flux:button wire:click="$set('showDiskonKeranjangSidebar', false)" class="w-full" variant="primary">{{ __('Terapkan') }}</flux:button>
                        </div>
                    </div>
                </div>
            </template>
        @endif

        {{-- Edit Pelanggan Modal --}}
        <flux:modal name="edit-pelanggan-modal" class="max-w-xs">
            <div class="space-y-4">
                <flux:heading size="lg">{{ __('Ubah Nama Pelanggan') }}</flux:heading>
                <flux:subheading>{{ __('Masukkan nama pelanggan untuk transaksi ini.') }}</flux:subheading>

                <flux:field>
                    <flux:label>{{ __('Nama Pelanggan') }}</flux:label>
                    <flux:input wire:model.live="namaPelanggan" placeholder="GUEST" />
                </flux:field>

                <flux:modal.close>
                    <flux:button class="w-full" variant="primary">{{ __('Simpan') }}</flux:button>
                </flux:modal.close>
            </div>
        </flux:modal>
    </div>
@endif
