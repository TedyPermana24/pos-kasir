<?php

use App\Models\Promo;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manajemen Promo')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public ?int $deletingPromoId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function toggleActive(int $id): void
    {
        $promo = Promo::findOrFail($id);
        $promo->update(['is_active' => ! $promo->is_active]);

        $status = $promo->is_active ? __('diaktifkan') : __('dinonaktifkan');
        Flux::toast(variant: 'success', text: __('Promo berhasil :status.', ['status' => $status]));
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingPromoId = $id;
        Flux::modal('confirm-delete')->show();
    }

    public function deletePromo(): void
    {
        $promo = Promo::findOrFail($this->deletingPromoId);
        $promo->produkVarians()->detach();
        $promo->delete();

        $this->deletingPromoId = null;
        Flux::modal('confirm-delete')->close();
        Flux::toast(variant: 'success', text: __('Promo berhasil dihapus.'));
    }

    /**
     * Determine the display status of a promo.
     *
     * @return array{label: string, color: string}
     */
    public function getPromoStatus(Promo $promo): array
    {
        if (! $promo->is_active) {
            return ['label' => __('Nonaktif'), 'color' => 'zinc'];
        }

        if ($promo->tanggal_selesai && $promo->tanggal_selesai->isPast()) {
            return ['label' => __('Kadaluarsa'), 'color' => 'red'];
        }

        if ($promo->tanggal_mulai && $promo->tanggal_mulai->isFuture()) {
            return ['label' => __('Akan Datang'), 'color' => 'yellow'];
        }

        return ['label' => __('Aktif'), 'color' => 'lime'];
    }

    #[Computed]
    public function promos()
    {
        return Promo::query()
            ->withCount('produkVarians')
            ->when($this->search, fn (Builder $query) => $query->where('nama', 'like', "%{$this->search}%"))
            ->when($this->statusFilter === 'aktif', fn (Builder $query) => $query->aktif())
            ->when($this->statusFilter === 'nonaktif', fn (Builder $query) => $query->where('is_active', false))
            ->when($this->statusFilter === 'kadaluarsa', fn (Builder $query) => $query->where('is_active', true)
                ->whereNotNull('tanggal_selesai')
                ->where('tanggal_selesai', '<', now()))
            ->orderByDesc('id')
            ->paginate(15);
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manajemen Promo') }}</flux:heading>
            <flux:subheading>{{ __('Kelola promo dan batas minimal harga jual produk') }}</flux:subheading>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('promo.create')" wire:navigate data-test="add-promo-button">
            {{ __('Tambah Promo') }}
        </flux:button>
    </div>

    {{-- Search & Filter --}}
    <div class="flex flex-col sm:flex-row items-center gap-3 w-full max-w-2xl">
        <div class="w-full sm:flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari promo...')" clearable data-test="search-input" />
        </div>
        <div class="w-full sm:w-48 shrink-0">
            <flux:select wire:model.live="statusFilter" data-test="status-filter">
                <flux:select.option value="">{{ __('Semua Status') }}</flux:select.option>
                <flux:select.option value="aktif">{{ __('Aktif') }}</flux:select.option>
                <flux:select.option value="nonaktif">{{ __('Nonaktif') }}</flux:select.option>
                <flux:select.option value="kadaluarsa">{{ __('Kadaluarsa') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Desktop & Tablet Table --}}
    <div class="hidden md:block">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nama Promo') }}</flux:table.column>
                <flux:table.column>{{ __('Periode') }}</flux:table.column>
                <flux:table.column>{{ __('Produk') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-24"></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->promos as $promo)
                    @php $status = $this->getPromoStatus($promo); @endphp
                    <flux:table.row wire:key="promo-{{ $promo->id }}">
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $promo->nama }}</div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                @if ($promo->tanggal_mulai && $promo->tanggal_selesai)
                                    {{ $promo->tanggal_mulai->format('d M Y') }} — {{ $promo->tanggal_selesai->format('d M Y') }}
                                @elseif ($promo->tanggal_mulai)
                                    {{ __('Mulai') }} {{ $promo->tanggal_mulai->format('d M Y') }}
                                @elseif ($promo->tanggal_selesai)
                                    {{ __('s/d') }} {{ $promo->tanggal_selesai->format('d M Y') }}
                                @else
                                    <span class="text-zinc-400">{{ __('Tanpa batas') }}</span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $promo->produk_varians_count }} {{ __('varian') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$status['color']">{{ $status['label'] }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('promo.edit', $promo)" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="{{ $promo->is_active ? 'eye-slash' : 'eye' }}" wire:click="toggleActive({{ $promo->id }})">
                                        {{ $promo->is_active ? __('Nonaktifkan') : __('Aktifkan') }}
                                    </flux:menu.item>
                                    <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $promo->id }})">
                                        {{ __('Hapus') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center">
                            <div class="py-8">
                                <flux:icon name="tag" class="mx-auto mb-2 size-8 text-zinc-400" />
                                <flux:text>{{ __('Belum ada promo.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Mobile Cards --}}
    <div class="space-y-3 md:hidden">
        @forelse ($this->promos as $promo)
            @php $status = $this->getPromoStatus($promo); @endphp
            <flux:card wire:key="promo-mobile-{{ $promo->id }}" class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 flex-1 space-y-1">
                        <flux:heading size="sm" class="line-clamp-2">{{ $promo->nama }}</flux:heading>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <flux:badge size="sm" :color="$status['color']">{{ $status['label'] }}</flux:badge>
                            <flux:badge size="sm" variant="pill" color="zinc">{{ $promo->produk_varians_count }} {{ __('varian') }}</flux:badge>
                        </div>
                    </div>
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" size="sm" class="-mt-2 -mr-2" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" :href="route('promo.edit', $promo)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:menu.item>
                            <flux:menu.item icon="{{ $promo->is_active ? 'eye-slash' : 'eye' }}" wire:click="toggleActive({{ $promo->id }})">
                                {{ $promo->is_active ? __('Nonaktifkan') : __('Aktifkan') }}
                            </flux:menu.item>
                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $promo->id }})">
                                {{ __('Hapus') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>

                <flux:separator class="my-3" />

                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    @if ($promo->tanggal_mulai && $promo->tanggal_selesai)
                        {{ $promo->tanggal_mulai->format('d M Y') }} — {{ $promo->tanggal_selesai->format('d M Y') }}
                    @elseif ($promo->tanggal_mulai)
                        {{ __('Mulai') }} {{ $promo->tanggal_mulai->format('d M Y') }}
                    @elseif ($promo->tanggal_selesai)
                        {{ __('s/d') }} {{ $promo->tanggal_selesai->format('d M Y') }}
                    @else
                        <span class="text-zinc-400">{{ __('Tanpa batas waktu') }}</span>
                    @endif
                </div>
            </flux:card>
        @empty
            <div class="py-12 text-center">
                <flux:icon name="tag" class="mx-auto mb-2 size-8 text-zinc-400" />
                <flux:text>{{ __('Belum ada promo.') }}</flux:text>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $this->promos->links() }}
    </div>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-delete" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Promo?') }}</flux:heading>
            <flux:text>{{ __('Promo ini akan dihapus beserta semua pengaturan harga minimalnya. Yakin ingin melanjutkan?') }}</flux:text>
            <div class="flex gap-3">
                <flux:modal.close>
                    <flux:button class="w-full">{{ __('Batal') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" class="w-full" wire:click="deletePromo" data-test="confirm-delete-button">
                    {{ __('Hapus') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
