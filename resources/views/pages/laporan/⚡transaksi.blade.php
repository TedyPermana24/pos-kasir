<?php

use App\Models\Transaksi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Laporan Transaksi')] class extends Component {
    use WithPagination;

    #[Url(as: 'start')]
    public string $startDate = '';

    #[Url(as: 'end')]
    public string $endDate = '';

    #[Url(as: 'status')]
    public string $statusTab = 'semua'; // semua, selesai, dibatalkan

    public ?int $selectedTransaksiId = null;

    public function mount(): void
    {
        if (empty($this->startDate)) {
            $this->startDate = Carbon::today()->format('Y-m-d');
        }
        if (empty($this->endDate)) {
            $this->endDate = Carbon::today()->format('Y-m-d');
        }
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
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
        $this->startDate = Carbon::today()->format('Y-m-d');
        $this->endDate = Carbon::today()->format('Y-m-d');
        $this->resetPage();
    }

    public function filterMingguIni(): void
    {
        $this->startDate = Carbon::now()->startOfWeek()->format('Y-m-d');
        $this->endDate = Carbon::now()->endOfWeek()->format('Y-m-d');
        $this->resetPage();
    }

    public function filterBulanIni(): void
    {
        $this->startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->startDate = Carbon::today()->format('Y-m-d');
        $this->endDate = Carbon::today()->format('Y-m-d');
        $this->statusTab = 'semua';
        $this->resetPage();
    }

    #[Computed]
    public function totalOmzet(): float
    {
        return (float) Transaksi::query()
            ->where('status', 'selesai')
            ->when($this->startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->endDate))
            ->sum('grand_total');
    }

    #[Computed]
    public function totalSelesaiCount(): int
    {
        return Transaksi::query()
            ->where('status', 'selesai')
            ->when($this->startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->endDate))
            ->count();
    }

    #[Computed]
    public function totalDibatalkanCount(): int
    {
        return Transaksi::query()
            ->where('status', 'dibatalkan')
            ->when($this->startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->endDate))
            ->count();
    }

    #[Computed]
    public function totalDibatalkanNominal(): float
    {
        return (float) Transaksi::query()
            ->where('status', 'dibatalkan')
            ->when($this->startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->endDate))
            ->sum('grand_total');
    }

    #[Computed]
    public function transaksis()
    {
        return Transaksi::query()
            ->with(['user', 'cancelledBy', 'details.produkVarian.produk', 'details.produkVarian.satuan'])
            ->when($this->statusTab !== 'semua', fn (Builder $query) => $query->where('status', $this->statusTab))
            ->when($this->startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->endDate))
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
        Flux::modal('laporan-detail-transaksi-modal')->show();
    }

    public function exportCsv()
    {
        $data = Transaksi::query()
            ->with(['user', 'cancelledBy'])
            ->when($this->statusTab !== 'semua', fn (Builder $query) => $query->where('status', $this->statusTab))
            ->when($this->startDate, fn (Builder $query) => $query->whereDate('created_at', '>=', $this->startDate))
            ->when($this->endDate, fn (Builder $query) => $query->whereDate('created_at', '<=', $this->endDate))
            ->latest()
            ->get();

        $filename = 'laporan_transaksi_' . date('Y-m-d_H-i-s') . '.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = [
            'Tanggal',
            'No. Referensi',
            'Kasir',
            'Pelanggan',
            'Status',
            'Subtotal',
            'Diskon',
            'Pajak',
            'Grand Total',
            'Dibayar',
            'Kembalian',
            'Tanggal Dibatalkan',
            'Dibatalkan Oleh',
            'Alasan Pembatalan',
        ];

        $callback = function () use ($data, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($data as $t) {
                fputcsv($file, [
                    $t->created_at->format('Y-m-d H:i'),
                    $t->no_referensi,
                    $t->user?->name ?? 'Unknown',
                    $t->nama_pelanggan,
                    strtoupper($t->status),
                    $t->subtotal,
                    $t->total_diskon,
                    $t->total_pajak,
                    $t->grand_total,
                    $t->bayar,
                    $t->kembalian,
                    $t->cancelled_at ? $t->cancelled_at->format('Y-m-d H:i') : '-',
                    $t->cancelledBy?->name ?? '-',
                    $t->alasan_pembatalan ?? '-',
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Laporan Transaksi') }}</flux:heading>
            <flux:subheading>{{ __('Riwayat dan ringkasan transaksi berdasarkan rentang tanggal.') }}</flux:subheading>
        </div>
        <flux:button wire:click="exportCsv" icon="arrow-down-tray">
            Export CSV
        </flux:button>
    </div>

    {{-- Top Summary Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @if (auth()->user()->hasPermission('laporan.omzet'))
            <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Omzet Selesai') }}</p>
                        <h3 class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            Rp {{ number_format($this->totalOmzet, 0, ',', '.') }}
                        </h3>
                    </div>
                    <div class="rounded-lg bg-emerald-500/10 p-3 text-emerald-600 dark:text-emerald-400">
                        <flux:icon name="banknotes" class="size-6" />
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Transaksi Selesai') }}</p>
                    <h3 class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">
                        {{ number_format($this->totalSelesaiCount, 0, ',', '.') }}
                    </h3>
                </div>
                <div class="rounded-lg bg-blue-500/10 p-3 text-blue-600 dark:text-blue-400">
                    <flux:icon name="check-circle" class="size-6" />
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-800 sm:col-span-2 lg:col-span-1">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Laporan Pembatalan') }}</p>
                    <h3 class="mt-1 text-2xl font-bold text-rose-600 dark:text-rose-400">
                        {{ number_format($this->totalDibatalkanCount, 0, ',', '.') }}
                    </h3>
                    <p class="text-xs text-rose-500">
                        Rp {{ number_format($this->totalDibatalkanNominal, 0, ',', '.') }} {{ __('Dibatalkan') }}
                    </p>
                </div>
                <div class="rounded-lg bg-rose-500/10 p-3 text-rose-600 dark:text-rose-400">
                    <flux:icon name="x-circle" class="size-6" />
                </div>
            </div>
        </div>
    </div>

    {{-- Main Filter & Table Card --}}
    <flux:card class="p-6">
        <div class="mb-6 space-y-4">
            {{-- Status Filter Tabs --}}
            <div class="flex border-b border-zinc-200 dark:border-zinc-700">
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
                    {{ __('Dibatalkan') }}
                </button>
            </div>

            {{-- Date Shortcut Buttons --}}
            <div class="flex flex-wrap items-center gap-2">
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

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 sm:items-end">
                <flux:field>
                    <flux:label>{{ __('Tanggal Mulai') }}</flux:label>
                    <flux:input type="date" wire:model.live="startDate" />
                </flux:field>
                
                <flux:field>
                    <flux:label>{{ __('Tanggal Akhir') }}</flux:label>
                    <flux:input type="date" wire:model.live="endDate" />
                </flux:field>

                <div class="flex items-center">
                    <flux:button wire:click="resetFilters" variant="subtle" class="w-full">
                        {{ __('Reset Filter') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Tanggal') }}</flux:table.column>
                    <flux:table.column>{{ __('No. Referensi') }}</flux:table.column>
                    <flux:table.column>{{ __('Kasir & Pelanggan') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Aksi') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->transaksis as $transaksi)
                        <flux:table.row :key="$transaksi->id">
                            <flux:table.cell>
                                {{ $transaksi->created_at->format('d/m/Y H:i') }}
                            </flux:table.cell>
                            <flux:table.cell class="font-bold">
                                {{ $transaksi->no_referensi }}
                            </flux:table.cell>
                            <flux:table.cell>
                                {{ $transaksi->user?->name ?? '-' }}
                                <div class="text-xs text-zinc-500">{{ $transaksi->nama_pelanggan }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($transaksi->status === 'dibatalkan')
                                    <span class="font-semibold text-rose-600 dark:text-rose-400">
                                        {{ __('Dibatalkan') }}
                                    </span>
                                @else
                                    <span class="font-medium text-zinc-900 dark:text-white">
                                        {{ __('Selesai') }}
                                    </span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end" class="font-medium">
                                Rp {{ number_format($transaksi->grand_total, 0, ',', '.') }}
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="subtle" icon="eye" wire:click="showDetail({{ $transaksi->id }})">
                                    {{ __('Detail') }}
                                </flux:button>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center text-zinc-500">
                                {{ __('Tidak ada data transaksi.') }}
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

    {{-- Detail Transaksi Modal --}}
    <flux:modal name="laporan-detail-transaksi-modal" class="max-w-2xl">
        @if ($this->selectedTransaksi)
            <div class="space-y-6">
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
                </div>

                <div class="flex justify-end border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:button variant="subtle" wire:click="$set('selectedTransaksiId', null)" @click="Flux.modal('laporan-detail-transaksi-modal').close()">
                        {{ __('Tutup') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
