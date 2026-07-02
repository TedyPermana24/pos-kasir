<?php

use App\Models\Transaksi;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Riwayat Transaksi')] #[Layout('layouts.pos')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $tanggalMulai = '';
    public string $tanggalSelesai = '';

    public function mount(): void
    {
        $this->tanggalMulai = today()->format('Y-m-d');
        $this->tanggalSelesai = today()->format('Y-m-d');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTanggalMulai(): void
    {
        $this->resetPage();
    }

    public function updatedTanggalSelesai(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->tanggalMulai = '';
        $this->tanggalSelesai = '';
        $this->resetPage();
    }

    public function getTransaksisProperty()
    {
        return Transaksi::query()
            ->with(['user', 'details.produkVarian.produk'])
            ->when($this->search, function (Builder $query) {
                $query->where('no_referensi', 'like', "%{$this->search}%")
                      ->orWhere('nama_pelanggan', 'like', "%{$this->search}%");
            })
            ->when($this->tanggalMulai, function (Builder $query) {
                $query->whereDate('created_at', '>=', $this->tanggalMulai);
            })
            ->when($this->tanggalSelesai, function (Builder $query) {
                $query->whereDate('created_at', '<=', $this->tanggalSelesai);
            })
            ->latest()
            ->paginate(15);
    }
}; ?>

<div class="space-y-6 p-6 h-screen overflow-y-auto">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Riwayat Transaksi') }}</flux:heading>
            <flux:subheading>{{ __('Daftar semua transaksi yang telah selesai (Close Bill).') }}</flux:subheading>
        </div>
        <flux:button :href="route('kasir.index')" wire:navigate icon="arrow-left">{{ __('Kembali ke POS') }}</flux:button>
    </div>

    <flux:card>
        {{-- Filters --}}
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="w-full sm:w-64">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Cari no ref / pelanggan...') }}" clearable />
            </div>
            <div class="w-full sm:w-48">
                <flux:input wire:model.live="tanggalMulai" type="date" label="{{ __('Dari Tanggal') }}" />
            </div>
            <div class="w-full sm:w-48">
                <flux:input wire:model.live="tanggalSelesai" type="date" label="{{ __('Sampai Tanggal') }}" />
            </div>
            @if ($search || $tanggalMulai || $tanggalSelesai)
                <flux:button wire:click="clearFilters" variant="ghost" class="text-zinc-500">{{ __('Reset Filter') }}</flux:button>
            @endif
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Waktu') }}</flux:table.column>
                    <flux:table.column>{{ __('No. Ref / Pelanggan') }}</flux:table.column>
                    <flux:table.column>{{ __('Kasir') }}</flux:table.column>
                    <flux:table.column>{{ __('Item') }}</flux:table.column>
                    <flux:table.column align="right">{{ __('Total') }}</flux:table.column>
                    <flux:table.column align="center">{{ __('Aksi') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->transaksis as $transaksi)
                        <flux:table.row wire:key="tx-{{ $transaksi->id }}">
                            <flux:table.cell class="whitespace-nowrap">
                                <div>
                                    <p class="font-medium">{{ $transaksi->created_at->format('d/m/Y') }}</p>
                                    <p class="text-xs text-zinc-500">{{ $transaksi->created_at->format('H:i') }}</p>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div>
                                    <p class="font-semibold">{{ $transaksi->no_referensi }}</p>
                                    <p class="text-sm text-zinc-500">{{ $transaksi->nama_pelanggan }}</p>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $transaksi->user->name ?? '-' }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $transaksi->details->sum('kuantitas') }} item
                            </flux:table.cell>
                            <flux:table.cell align="right">
                                <p class="font-semibold text-emerald-600">Rp {{ number_format($transaksi->grand_total, 0, ',', '.') }}</p>
                            </flux:table.cell>
                            <flux:table.cell align="center">
                                <flux:modal.trigger :name="'detail-tx-'.$transaksi->id">
                                    <flux:button size="sm" variant="ghost" icon="eye" />
                                </flux:modal.trigger>
                            </flux:table.cell>
                        </flux:table.row>

                        {{-- Modal Detail Transaksi --}}
                        <flux:modal :name="'detail-tx-'.$transaksi->id" class="w-full max-w-lg">
                            <div class="space-y-4">
                                <div class="flex items-start justify-between border-b border-zinc-200 pb-4 pr-8 dark:border-zinc-700">
                                    <div>
                                        <flux:heading size="lg">{{ __('Detail Transaksi') }}</flux:heading>
                                        <p class="text-sm font-medium text-emerald-600">{{ $transaksi->no_referensi }}</p>
                                    </div>
                                    <div class="text-right text-sm text-zinc-500">
                                        <p>{{ $transaksi->created_at->format('d M Y, H:i') }}</p>
                                        <p>{{ __('Kasir') }}: {{ $transaksi->user->name ?? '-' }}</p>
                                    </div>
                                </div>

                                <div class="max-h-60 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-700">
                                    @foreach ($transaksi->details as $detail)
                                        <div class="py-2">
                                            @php
                                                $originalPrice = $detail->produkVarian->harga_jual ?? $detail->harga_satuan;
                                                $originalTotal = $originalPrice * $detail->kuantitas;
                                                $discountAmount = $originalTotal - $detail->subtotal;
                                            @endphp
                                            <div class="flex justify-between items-start">
                                                <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                                    {{ $detail->produkVarian->produk->nama_produk ?? 'Produk Dihapus' }}
                                                    @if (($detail->produkVarian->nama_varian ?? 'Default') !== 'Default')
                                                        <span class="text-zinc-500">({{ $detail->produkVarian->nama_varian ?? '' }})</span>
                                                    @endif
                                                </p>
                                                <div class="text-right">
                                                    @if ($discountAmount > 0)
                                                        <div class="flex items-center justify-end gap-1 text-xs">
                                                            <span class="text-zinc-400 line-through">Rp {{ number_format($originalTotal, 0, ',', '.') }}</span>
                                                            <span class="text-emerald-600 font-medium">(-Rp {{ number_format($discountAmount, 0, ',', '.') }})</span>
                                                        </div>
                                                    @endif
                                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                                        Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                                    </p>
                                                </div>
                                            </div>
                                            <p class="text-xs text-zinc-500">
                                                {{ $detail->kuantitas }}x Rp {{ number_format($originalPrice, 0, ',', '.') }}
                                            </p>
                                            @if ($detail->catatan)
                                                <p class="mt-1 text-xs italic text-zinc-400">"{{ $detail->catatan }}"</p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="space-y-1 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
                                    <div class="flex justify-between text-zinc-500">
                                        <span>{{ __('Subtotal') }}</span>
                                        <span>Rp {{ number_format($transaksi->subtotal + $transaksi->diskon_produk, 0, ',', '.') }}</span>
                                    </div>
                                    @if ($transaksi->diskon_produk > 0)
                                        <div class="flex justify-between text-emerald-600">
                                            <span>{{ __('Diskon Produk') }}</span>
                                            <span>-Rp {{ number_format($transaksi->diskon_produk, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if ($transaksi->diskon_keranjang > 0)
                                        <div class="flex justify-between text-emerald-600">
                                            <span>{{ __('Diskon Keranjang') }}</span>
                                            <span>-Rp {{ number_format($transaksi->diskon_keranjang, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if ($transaksi->total_diskon > 0 && $transaksi->diskon_produk == 0 && $transaksi->diskon_keranjang == 0)
                                        <div class="flex justify-between text-emerald-600">
                                            <span>{{ __('Total Diskon') }}</span>
                                            <span>-Rp {{ number_format($transaksi->total_diskon, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    @if ($transaksi->total_pajak > 0)
                                        <div class="flex justify-between text-zinc-500">
                                            <span>{{ __('Pajak') }}</span>
                                            <span>Rp {{ number_format($transaksi->total_pajak, 0, ',', '.') }}</span>
                                        </div>
                                    @endif
                                    <div class="mt-2 flex justify-between text-base font-bold text-zinc-900 dark:text-white">
                                        <span>{{ __('Grand Total') }}</span>
                                        <span>Rp {{ number_format($transaksi->grand_total, 0, ',', '.') }}</span>
                                    </div>
                                    <div class="mt-4 flex justify-between rounded-lg bg-zinc-50 p-3 text-zinc-500 dark:bg-zinc-800">
                                        <div class="text-center">
                                            <p class="text-xs">{{ __('Bayar (Tunai)') }}</p>
                                            <p class="font-medium text-zinc-900 dark:text-white">Rp {{ number_format($transaksi->bayar, 0, ',', '.') }}</p>
                                        </div>
                                        <div class="text-center">
                                            <p class="text-xs">{{ __('Kembalian') }}</p>
                                            <p class="font-medium text-zinc-900 dark:text-white">Rp {{ number_format($transaksi->kembalian, 0, ',', '.') }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </flux:modal>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-8 text-center text-zinc-500">
                                {{ __('Tidak ada transaksi ditemukan.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-4">
            {{ $this->transaksis->links() }}
        </div>
    </flux:card>
</div>
