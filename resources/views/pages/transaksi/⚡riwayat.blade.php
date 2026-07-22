<?php

use App\Models\ProdukVarian;
use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Riwayat Transaksi')] #[Layout('layouts.pos')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $tanggalMulai = '';

    #[Url]
    public string $tanggalSelesai = '';

    #[Url(as: 'status')]
    public string $statusTab = 'semua'; // semua, selesai, dibatalkan

    public ?int $selectedTransaksiId = null;

    public ?int $cancellingTransaksiId = null;

    public string $alasanPembatalan = '';

    public function mount(): void
    {
        if (empty($this->tanggalMulai)) {
            $this->tanggalMulai = Carbon::today()->format('Y-m-d');
        }
        if (empty($this->tanggalSelesai)) {
            $this->tanggalSelesai = Carbon::today()->format('Y-m-d');
        }
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

    public function setStatusTab(string $tab): void
    {
        $this->statusTab = $tab;
        $this->resetPage();
    }

    public function filterHariIni(): void
    {
        $this->tanggalMulai = Carbon::today()->format('Y-m-d');
        $this->tanggalSelesai = Carbon::today()->format('Y-m-d');
        $this->resetPage();
    }

    public function filterMingguIni(): void
    {
        $this->tanggalMulai = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->tanggalSelesai = Carbon::now()->endOfWeek()->format('Y-m-d');
        $this->resetPage();
    }

    public function filterBulanIni(): void
    {
        $this->tanggalMulai = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->tanggalSelesai = Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->tanggalMulai = Carbon::today()->format('Y-m-d');
        $this->tanggalSelesai = Carbon::today()->format('Y-m-d');
        $this->statusTab = 'semua';
        $this->resetPage();
    }

    #[Computed]
    public function totalDibatalkanCount(): int
    {
        return Transaksi::query()
            ->where('status', 'dibatalkan')
            ->when($this->tanggalMulai, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->tanggalMulai))
            ->when($this->tanggalSelesai, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->tanggalSelesai))
            ->count();
    }

    #[Computed]
    public function transaksis()
    {
        return Transaksi::query()
            ->with(['user', 'cancelledBy', 'details.produkVarian.produk', 'details.produkVarian.satuan'])
            ->when($this->statusTab !== 'semua', fn (Builder $query) => $query->where('status', $this->statusTab))
            ->when($this->search, function (Builder $query) {
                $query->where(function ($q) {
                    $q->where('no_referensi', 'like', "%{$this->search}%")
                      ->orWhere('nama_pelanggan', 'like', "%{$this->search}%");
                });
            })
            ->when($this->tanggalMulai, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->tanggalMulai))
            ->when($this->tanggalSelesai, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->tanggalSelesai))
            ->latest()
            ->paginate(15);
    }

    #[Computed]
    public function selectedTransaksi()
    {
        if (! $this->selectedTransaksiId) {
            return null;
        }

        return Transaksi::with(['user', 'cancelledBy', 'details.produkVarian.produk', 'details.produkVarian.satuan'])
            ->find($this->selectedTransaksiId);
    }

    public function showDetail(int $id): void
    {
        $this->selectedTransaksiId = $id;
        Flux::modal('detail-tx-modal')->show();
    }

    public function confirmCancel(int $id): void
    {
        if (! auth()->user()->hasPermission('transaksi.cancel')) {
            Flux::toast(variant: 'danger', text: __('Anda tidak memiliki hak akses untuk membatalkan transaksi.'));
            return;
        }

        $this->cancellingTransaksiId = $id;
        $this->alasanPembatalan = '';
        Flux::modal('cancel-tx-modal')->show();
    }

    public function processCancel(): void
    {
        if (! auth()->user()->hasPermission('transaksi.cancel')) {
            Flux::toast(variant: 'danger', text: __('Anda tidak memiliki hak akses untuk membatalkan transaksi.'));
            return;
        }

        $this->validate([
            'alasanPembatalan' => ['required', 'string', 'max:500'],
        ], [
            'alasanPembatalan.required' => __('Alasan pembatalan wajib diisi.'),
        ]);

        $transaksi = Transaksi::with('details')->find($this->cancellingTransaksiId);

        if (! $transaksi || $transaksi->status === 'dibatalkan') {
            Flux::toast(variant: 'danger', text: __('Transaksi tidak ditemukan atau sudah dibatalkan.'));
            return;
        }

        // Restore stock
        foreach ($transaksi->details as $detail) {
            $varian = ProdukVarian::find($detail->produk_varian_id);
            if ($varian && $varian->stok !== null) {
                $varian->increment('stok', $detail->kuantitas);
            }
        }

        // Update transaction status
        $transaksi->update([
            'status' => 'dibatalkan',
            'alasan_pembatalan' => $this->alasanPembatalan,
            'cancelled_at' => now(),
            'cancelled_by_user_id' => auth()->id(),
        ]);

        Flux::modal('cancel-tx-modal')->close();
        if ($this->selectedTransaksiId === $this->cancellingTransaksiId) {
            Flux::modal('detail-tx-modal')->close();
        }

        $this->cancellingTransaksiId = null;
        $this->alasanPembatalan = '';

        Flux::toast(variant: 'success', text: __('Transaksi :no berhasil dibatalkan dan sisa stok produk telah dikembalikan.', ['no' => $transaksi->no_referensi]));
    }
}; ?>

<div class="flex h-screen flex-col bg-zinc-50 dark:bg-zinc-900">
    {{-- Header Navbar POS (Identik dengan Menu Kasir Utama) --}}
    <header class="flex flex-shrink-0 items-center justify-between border-b border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex items-center gap-3">
            <flux:button variant="ghost" icon="bars-3" :href="route('dashboard')" wire:navigate size="sm" />
            <span class="text-lg font-bold text-zinc-700 dark:text-zinc-200 uppercase tracking-wide">
                {{ \App\Models\Outlet::first()->nama ?? 'POS KASIR' }}
            </span>
        </div>

        <div class="flex items-center gap-2">
            <flux:button :href="route('kasir.index')" wire:navigate variant="subtle" size="sm" icon="calculator">
                {{ __('Mulai Kasir') }}
            </flux:button>
        </div>
    </header>

    {{-- Content Body Area --}}
    <main class="flex-1 overflow-y-auto p-4 sm:p-6">
        <div class="mx-auto max-w-7xl space-y-4">
            {{-- Filter & Action Card --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800 sm:p-6">
                {{-- Status Tabs --}}
                <div class="mb-5 flex border-b border-zinc-200 dark:border-zinc-700">
                    <button
                        type="button"
                        wire:click="setStatusTab('semua')"
                        class="px-4 py-2.5 text-sm font-semibold transition border-b-2 -mb-px {{ $statusTab === 'semua' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
                    >
                        {{ __('Semua Transaksi') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setStatusTab('selesai')"
                        class="px-4 py-2.5 text-sm font-semibold transition border-b-2 -mb-px {{ $statusTab === 'selesai' ? 'border-emerald-600 text-emerald-600 dark:border-emerald-400 dark:text-emerald-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
                    >
                        {{ __('Transaksi Selesai') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setStatusTab('dibatalkan')"
                        class="px-4 py-2.5 text-sm font-semibold transition border-b-2 -mb-px {{ $statusTab === 'dibatalkan' ? 'border-rose-600 text-rose-600 dark:border-rose-400 dark:text-rose-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
                    >
                        {{ __('Riwayat Pembatalan') }}
                    </button>
                </div>

                {{-- Date Shortcut Buttons --}}
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        wire:click="filterHariIni"
                        class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3.5 py-1.5 text-xs font-medium text-zinc-700 shadow-xs transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 active:scale-95 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:bg-zinc-700 dark:hover:text-white"
                    >
                        {{ __('Hari Ini') }}
                    </button>
                    <button
                        type="button"
                        wire:click="filterMingguIni"
                        class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3.5 py-1.5 text-xs font-medium text-zinc-700 shadow-xs transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 active:scale-95 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:bg-zinc-700 dark:hover:text-white"
                    >
                        {{ __('Minggu Ini') }}
                    </button>
                    <button
                        type="button"
                        wire:click="filterBulanIni"
                        class="inline-flex items-center rounded-lg border border-zinc-300 bg-white px-3.5 py-1.5 text-xs font-medium text-zinc-700 shadow-xs transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 active:scale-95 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:border-zinc-500 dark:hover:bg-zinc-700 dark:hover:text-white"
                    >
                        {{ __('Bulan Ini') }}
                    </button>
                </div>

                {{-- Filters Bar --}}
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-4 sm:items-end">
                    <div class="sm:col-span-2">
                        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Cari no ref / pelanggan...') }}" clearable />
                    </div>
                    <div>
                        <flux:input wire:model.live="tanggalMulai" type="date" label="{{ __('Dari Tanggal') }}" />
                    </div>
                    <div>
                        <flux:input wire:model.live="tanggalSelesai" type="date" label="{{ __('Sampai Tanggal') }}" />
                    </div>
                </div>
            </div>

            {{-- Table Card --}}
            <div class="rounded-2xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:bg-zinc-800/80 dark:text-zinc-400">
                            <tr>
                                <th class="px-4 py-3.5">{{ __('Waktu') }}</th>
                                <th class="px-4 py-3.5">{{ __('No. Ref / Pelanggan') }}</th>
                                <th class="px-4 py-3.5">{{ __('Kasir') }}</th>
                                <th class="px-4 py-3.5">{{ __('Status') }}</th>
                                <th class="px-4 py-3.5 text-right">{{ __('Total') }}</th>
                                <th class="px-4 py-3.5 text-right">{{ __('Aksi') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @forelse ($this->transaksis as $transaksi)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/40">
                                    <td class="px-4 py-3.5 text-zinc-600 dark:text-zinc-300 whitespace-nowrap">
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $transaksi->created_at->format('d/m/Y') }}</div>
                                        <div class="text-xs text-zinc-500">{{ $transaksi->created_at->format('H:i') }} WIB</div>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        <div class="font-bold text-zinc-900 dark:text-white">{{ $transaksi->no_referensi }}</div>
                                        <div class="text-xs text-zinc-500">{{ __('Pelanggan: :nama', ['nama' => $transaksi->nama_pelanggan]) }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-zinc-700 dark:text-zinc-300 whitespace-nowrap">
                                        {{ $transaksi->user?->name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap">
                                        @if ($transaksi->status === 'dibatalkan')
                                            <span class="font-semibold text-rose-600 dark:text-rose-400">
                                                {{ __('Dibatalkan') }}
                                            </span>
                                        @else
                                            <span class="font-medium text-zinc-900 dark:text-white">
                                                {{ __('Selesai') }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right font-bold text-zinc-900 dark:text-white whitespace-nowrap">
                                        Rp {{ number_format($transaksi->grand_total, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                wire:click="showDetail({{ $transaksi->id }})"
                                                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-xs font-medium text-zinc-700 shadow-2xs transition hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                                            >
                                                <flux:icon name="eye" class="size-4 text-zinc-500" />
                                                <span>{{ __('Detail') }}</span>
                                            </button>

                                            @if ($transaksi->status === 'selesai' && auth()->user()->hasPermission('transaksi.cancel'))
                                                <button
                                                    type="button"
                                                    wire:click="confirmCancel({{ $transaksi->id }})"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-700 shadow-2xs transition hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 dark:hover:bg-rose-900/60"
                                                >
                                                    <flux:icon name="x-mark" class="size-4 text-rose-600 dark:text-rose-400" />
                                                    <span>{{ __('Batalkan') }}</span>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <flux:icon name="document-magnifying-glass" class="size-8 text-zinc-400" />
                                            <p class="font-medium">{{ __('Tidak ada transaksi ditemukan.') }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                    {{ $this->transaksis->links() }}
                </div>
            </div>
        </div>
    </main>

    {{-- Modal Detail Transaksi --}}
    <flux:modal name="detail-tx-modal" class="max-w-2xl">
        @if ($this->selectedTransaksi)
            <div class="space-y-6">
                {{-- Header --}}
                <div class="flex items-start justify-between border-b border-zinc-200 pb-4 dark:border-zinc-700">
                    <div>
                        <div class="flex items-center gap-3">
                            <h3 class="text-xl font-bold text-zinc-900 dark:text-white">
                                {{ $this->selectedTransaksi->no_referensi }}
                            </h3>
                            @if ($this->selectedTransaksi->status === 'dibatalkan')
                                <span class="font-semibold text-rose-600 dark:text-rose-400">
                                    ({{ __('Dibatalkan') }})
                                </span>
                            @else
                                <span class="font-medium text-zinc-900 dark:text-white">
                                    ({{ __('Selesai') }})
                                </span>
                            @endif
                        </div>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $this->selectedTransaksi->created_at->format('d F Y, H:i') }} WIB
                        </p>
                    </div>
                </div>

                {{-- General Info --}}
                <div class="grid grid-cols-2 gap-4 rounded-xl bg-zinc-50 p-4 text-xs dark:bg-zinc-800/60">
                    <div>
                        <span class="text-zinc-400">{{ __('Kasir') }}</span>
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $this->selectedTransaksi->user?->name ?? '-' }}</p>
                    </div>
                    <div>
                        <span class="text-zinc-400">{{ __('Pelanggan') }}</span>
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $this->selectedTransaksi->nama_pelanggan }}</p>
                    </div>
                </div>

                {{-- Cancellation Information Banner --}}
                @if ($this->selectedTransaksi->status === 'dibatalkan')
                    <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4 text-xs dark:border-rose-900/40 dark:bg-rose-950/30">
                        <div class="flex items-center gap-2 font-bold text-rose-900 dark:text-rose-200">
                            <flux:icon name="exclamation-triangle" class="size-4 text-rose-600 dark:text-rose-400" />
                            <span>{{ __('Informasi Pembatalan Transaksi') }}</span>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-rose-800 dark:text-rose-300">
                            <div>
                                <span class="text-rose-600 dark:text-rose-400">{{ __('Tanggal Dibatalkan:') }}</span>
                                <p class="font-medium">{{ $this->selectedTransaksi->cancelled_at ? $this->selectedTransaksi->cancelled_at->format('d/m/Y H:i') : '-' }}</p>
                            </div>
                            <div>
                                <span class="text-rose-600 dark:text-rose-400">{{ __('Dibatalkan Oleh:') }}</span>
                                <p class="font-medium">{{ $this->selectedTransaksi->cancelledBy?->name ?? '-' }}</p>
                            </div>
                            <div class="col-span-2 mt-1">
                                <span class="text-rose-600 dark:text-rose-400">{{ __('Alasan Pembatalan:') }}</span>
                                <p class="font-medium italic">"{{ $this->selectedTransaksi->alasan_pembatalan ?? '-' }}"</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Product Details --}}
                <div>
                    <h4 class="mb-2 text-xs font-bold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">{{ __('Rincian Produk') }}</h4>
                    <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-zinc-50 font-semibold uppercase text-zinc-500 dark:bg-zinc-800/80 dark:text-zinc-400">
                                <tr>
                                    <th class="px-3 py-2.5">{{ __('Produk / Varian') }}</th>
                                    <th class="px-3 py-2.5 text-center">{{ __('Qty') }}</th>
                                    <th class="px-3 py-2.5 text-right">{{ __('Harga Satuan') }}</th>
                                    <th class="px-3 py-2.5 text-right">{{ __('Subtotal') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                @foreach ($this->selectedTransaksi->details as $detail)
                                    <tr>
                                        <td class="px-3 py-2.5 font-medium text-zinc-900 dark:text-white">
                                            {{ $detail->produkVarian?->produk?->nama_produk ?? 'Produk' }}
                                            <span class="text-zinc-500">({{ $detail->produkVarian?->nama_varian ?? 'Varian' }})</span>
                                        </td>
                                        <td class="px-3 py-2.5 text-center font-bold text-zinc-700 dark:text-zinc-300">
                                            {{ $detail->kuantitas }} {{ $detail->produkVarian?->satuan?->nama }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right text-zinc-600 dark:text-zinc-400">
                                            Rp {{ number_format($detail->harga_satuan, 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-bold text-zinc-900 dark:text-white">
                                            Rp {{ number_format($detail->subtotal, 0, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Financial Breakdown --}}
                <div class="space-y-1.5 border-t border-zinc-200 pt-3 text-xs dark:border-zinc-700">
                    <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                        <span>{{ __('Subtotal') }}</span>
                        <span class="font-medium text-zinc-900 dark:text-white">Rp {{ number_format($this->selectedTransaksi->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if ($this->selectedTransaksi->total_diskon > 0)
                        <div class="flex justify-between text-rose-600 dark:text-rose-400">
                            <span>{{ __('Total Diskon') }}</span>
                            <span class="font-medium">- Rp {{ number_format($this->selectedTransaksi->total_diskon, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    @if ($this->selectedTransaksi->total_pajak > 0)
                        <div class="flex justify-between text-zinc-600 dark:text-zinc-400">
                            <span>{{ __('Total Pajak') }}</span>
                            <span class="font-medium text-zinc-900 dark:text-white">+ Rp {{ number_format($this->selectedTransaksi->total_pajak, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between border-t border-zinc-100 pt-2 text-sm font-bold text-zinc-900 dark:border-zinc-800 dark:text-white">
                        <span>{{ __('Grand Total') }}</span>
                        <span class="text-base text-emerald-600 dark:text-emerald-400">Rp {{ number_format($this->selectedTransaksi->grand_total, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between pt-1 text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Bayar') }}</span>
                        <span>Rp {{ number_format($this->selectedTransaksi->bayar, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Kembalian') }}</span>
                        <span>Rp {{ number_format($this->selectedTransaksi->kembalian, 0, ',', '.') }}</span>
                    </div>
                </div>

                {{-- Modal Actions --}}
                <div class="flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:button variant="subtle" wire:click="$set('selectedTransaksiId', null)" @click="Flux.modal('detail-tx-modal').close()">
                        {{ __('Tutup') }}
                    </flux:button>

                    @if ($this->selectedTransaksi->status === 'selesai' && auth()->user()->hasPermission('transaksi.cancel'))
                        <flux:button variant="danger" icon="x-mark" wire:click="confirmCancel({{ $this->selectedTransaksi->id }})">
                            {{ __('Batalkan Transaksi Ini') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>

    {{-- Cancel Confirmation Modal --}}
    <flux:modal name="cancel-tx-modal" class="max-w-md">
        <div class="space-y-4">
            <div class="flex items-center gap-3 text-rose-600 dark:text-rose-400">
                <div class="rounded-xl bg-rose-500/10 p-3">
                    <flux:icon name="exclamation-triangle" class="size-6" />
                </div>
                <div>
                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Konfirmasi Pembatalan Transaksi') }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Tindakan ini akan membatalkan transaksi & mengembalikan sisa stok.') }}</p>
                </div>
            </div>

            <form wire:submit="processCancel" class="space-y-4">
                <flux:field>
                    <flux:label>{{ __('Alasan Pembatalan') }} <span class="text-rose-500">*</span></flux:label>
                    <flux:textarea
                        wire:model="alasanPembatalan"
                        placeholder="Contoh: Salah input barang / Pelanggan batalkan transaksi"
                        rows="3"
                    />
                    <flux:error name="alasanPembatalan" />
                </flux:field>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <flux:button variant="subtle" type="button" @click="Flux.modal('cancel-tx-modal').close()">
                        {{ __('Batal') }}
                    </flux:button>
                    <flux:button variant="danger" type="submit">
                        {{ __('Ya, Batalkan Transaksi') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </flux:modal>
</div>
