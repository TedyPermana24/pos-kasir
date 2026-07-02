<?php

use App\Models\Produk;
use App\Models\Satuan;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manajemen Produk')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'kategori')]
    public string $kategori_filter = '';

    public ?int $deletingProdukId = null;

    /** Add Varian Modal State */
    public ?int $addingVarianProdukId = null;
    public string $nama_varian = '';
    public string $satuan_id = '';
    public string $harga_jual = '';
    public bool $aturStokModal = false;
    public string $sku = '';
    public string $harga_modal = '';
    public string $stok = '';
    public string $minimum_stok = '';

    /** Satuan modal state */
    public string $searchSatuan = '';
    public string $namaSatuanBaru = '';
    public ?int $editingSatuanId = null;
    public string $editingSatuanNama = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedKategoriFilter(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingProdukId = $id;
        Flux::modal('confirm-delete')->show();
    }

    public function deleteProduk(): void
    {
        $produk = Produk::findOrFail($this->deletingProdukId);
        $produk->varians()->delete();
        $produk->delete();

        $this->deletingProdukId = null;
        Flux::modal('confirm-delete')->close();
        Flux::toast(variant: 'success', text: __('Produk berhasil dihapus.'));
    }

    /** Add Varian Methods */
    public function openAddVarianModal(int $produkId): void
    {
        $this->addingVarianProdukId = $produkId;
        $this->nama_varian = '';
        $this->satuan_id = '';
        $this->harga_jual = '';
        $this->aturStokModal = false;
        $this->sku = '';
        $this->harga_modal = '';
        $this->stok = '';
        $this->minimum_stok = '';

        Flux::modal('add-varian')->show();
    }

    public function generateVarianSku(): void
    {
        if (! $this->addingVarianProdukId) {
            return;
        }

        $produk = Produk::find($this->addingVarianProdukId);
        if (! $produk) {
            return;
        }

        $prefix = Str::of($produk->nama_produk)->upper()->limit(3, '')->toString();
        $random = strtoupper(Str::random(5));
        $this->sku = $prefix ? "{$prefix}-{$random}" : "SKU-{$random}";
    }

    public function saveNewVarian(): void
    {
        if (! $this->addingVarianProdukId) {
            return;
        }

        $rules = [
            'nama_varian' => ['required', 'string', 'max:255'],
            'satuan_id' => ['required', 'exists:satuans,id'],
            'harga_jual' => ['required', 'numeric', 'min:0'],
        ];

        if ($this->aturStokModal) {
            $rules['sku'] = ['nullable', 'string', 'max:50', 'unique:produk_varians,sku'];
            $rules['harga_modal'] = ['nullable', 'numeric', 'min:0'];
            $rules['stok'] = ['nullable', 'integer', 'min:0'];
            $rules['minimum_stok'] = ['nullable', 'integer', 'min:0'];
        }

        $validated = $this->validate($rules);

        $produk = Produk::findOrFail($this->addingVarianProdukId);
        $produk->varians()->create([
            'satuan_id' => $validated['satuan_id'],
            'nama_varian' => $validated['nama_varian'],
            'harga_jual' => $validated['harga_jual'],
            'sku' => $this->aturStokModal ? ($validated['sku'] ?: null) : null,
            'harga_modal' => $this->aturStokModal ? ($validated['harga_modal'] ?: 0) : 0,
            'stok' => $this->aturStokModal ? ($validated['stok'] ?: 0) : 0,
            'minimum_stok' => $this->aturStokModal ? ($validated['minimum_stok'] ?: 0) : 0,
        ]);

        Flux::modal('add-varian')->close();
        Flux::toast(variant: 'success', text: __('Varian baru berhasil ditambahkan ke produk :nama', ['nama' => $produk->nama_produk]));
    }

    /** Satuan management */
    public function showSatuanModal(): void
    {
        Flux::modal('kelola-satuan')->show();
    }

    public function selectSatuan(int $id): void
    {
        $this->satuan_id = (string) $id;
        Flux::modal('kelola-satuan')->close();
    }

    public function createSatuan(): void
    {
        $this->validate([
            'namaSatuanBaru' => ['required', 'string', 'max:255', 'unique:satuans,nama'],
        ]);

        $satuan = Satuan::create(['nama' => $this->namaSatuanBaru]);
        $this->namaSatuanBaru = '';
        $this->searchSatuan = '';

        Flux::toast(variant: 'success', text: __('Satuan ":nama" berhasil ditambahkan.', ['nama' => $satuan->nama]));
    }

    public function startEditSatuan(int $id, string $nama): void
    {
        $this->editingSatuanId = $id;
        $this->editingSatuanNama = $nama;
    }

    public function cancelEditSatuan(): void
    {
        $this->editingSatuanId = null;
        $this->editingSatuanNama = '';
    }

    public function updateSatuan(): void
    {
        $this->validate([
            'editingSatuanNama' => ['required', 'string', 'max:255',
                \Illuminate\Validation\Rule::unique('satuans', 'nama')->ignore($this->editingSatuanId)],
        ]);

        Satuan::findOrFail($this->editingSatuanId)->update(['nama' => $this->editingSatuanNama]);
        Flux::toast(variant: 'success', text: __('Satuan berhasil diperbarui.'));
        $this->cancelEditSatuan();
    }

    public function deleteSatuan(int $id): void
    {
        Satuan::findOrFail($id)->delete();
        if ($this->satuan_id === (string) $id) {
            $this->satuan_id = '';
        }
        Flux::toast(variant: 'success', text: __('Satuan berhasil dihapus.'));
    }

    #[Computed]
    public function selectedSatuan()
    {
        return $this->satuan_id ? Satuan::find($this->satuan_id) : null;
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
            ->when($this->kategori_filter, fn (Builder $query) => $query->where('kategori_id', $this->kategori_filter))
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function satuans()
    {
        return Satuan::orderBy('nama')
            ->when($this->searchSatuan, fn ($q) => $q->where('nama', 'like', "%{$this->searchSatuan}%"))
            ->get();
    }

    #[Computed]
    public function filterKategoris()
    {
        return \App\Models\Kategori::orderBy('nama')->get();
    }
}; ?>

<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manajemen Produk') }}</flux:heading>
            <flux:subheading>{{ __('Kelola semua produk toko Anda') }}</flux:subheading>
        </div>

        @if (auth()->user()->hasPermission('produk.create'))
            <flux:button variant="primary" icon="plus" :href="route('produk.create')" wire:navigate data-test="add-product-button">
                {{ __('Tambah Produk') }}
            </flux:button>
        @endif
    </div>

    {{-- Search & Filter --}}
    <div class="flex flex-col sm:flex-row items-center gap-3 w-full max-w-2xl">
        <div class="w-full sm:flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari produk atau SKU...')" clearable data-test="search-input" />
        </div>
        <div class="w-full sm:w-48 shrink-0">
            <flux:select wire:model.live="kategori_filter" data-test="kategori-filter">
                <flux:select.option value="">{{ __('Semua Kategori') }}</flux:select.option>
                @foreach ($this->filterKategoris as $kat)
                    <flux:select.option value="{{ $kat->id }}">{{ $kat->nama }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Desktop & Tablet Table --}}
    <div class="hidden md:block">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Foto') }}</flux:table.column>
                <flux:table.column>{{ __('Produk') }}</flux:table.column>
                <flux:table.column class="hidden lg:table-cell">{{ __('Kategori') }}</flux:table.column>
                <flux:table.column>{{ __('Varian') }}</flux:table.column>
                <flux:table.column>{{ __('Harga Jual') }}</flux:table.column>
                @if (auth()->user()->hasPermission('produk.edit') || auth()->user()->hasPermission('produk.delete') || auth()->user()->hasPermission('produk.create'))
                    <flux:table.column class="w-24"></flux:table.column>
                @endif
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->produks as $produk)
                    <flux:table.row wire:key="produk-{{ $produk->id }}">
                        <flux:table.cell>
                            @if ($produk->foto_produk)
                                <img src="{{ Storage::url($produk->foto_produk) }}" alt="{{ $produk->nama_produk }}" class="size-12 rounded-lg object-cover shadow-sm">
                            @else
                                <div class="flex size-12 items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                                    <flux:icon name="photo" class="size-5 text-zinc-400" />
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="font-medium text-zinc-900 dark:text-white">{{ $produk->nama_produk }}</div>
                            @if ($produk->varians->count() > 0)
                                <div class="mt-0.5 text-xs text-zinc-500">
                                    {{ $produk->varians->pluck('nama_varian')->join(', ') }}
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="hidden lg:table-cell">{{ $produk->kategori->nama }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="zinc">{{ $produk->varians->count() }} {{ __('varian') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="font-medium">
                            @php
                                $minHarga = $produk->varians->min('harga_jual');
                                $maxHarga = $produk->varians->max('harga_jual');
                            @endphp
                            @if ($produk->varians->isEmpty())
                                <flux:text class="text-zinc-400">—</flux:text>
                            @elseif ($minHarga == $maxHarga)
                                Rp {{ number_format($minHarga, 0, ',', '.') }}
                            @else
                                Rp {{ number_format($minHarga, 0, ',', '.') }} - {{ number_format($maxHarga, 0, ',', '.') }}
                            @endif
                        </flux:table.cell>
                        @if (auth()->user()->hasPermission('produk.edit') || auth()->user()->hasPermission('produk.delete') || auth()->user()->hasPermission('produk.create'))
                            <flux:table.cell>
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                    <flux:menu>
                                        @if (auth()->user()->hasPermission('produk.edit'))
                                            <flux:menu.item icon="pencil-square" :href="route('produk.edit', $produk)" wire:navigate>
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                        @endif
                                        @if (auth()->user()->hasPermission('produk.create') || auth()->user()->hasPermission('produk.edit'))
                                            <flux:menu.item icon="plus" wire:click="openAddVarianModal({{ $produk->id }})">
                                                {{ __('Tambah Varian') }}
                                            </flux:menu.item>
                                        @endif
                                        @if (auth()->user()->hasPermission('produk.delete'))
                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $produk->id }})">
                                                {{ __('Hapus') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            </flux:table.cell>
                        @endif
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center">
                            <div class="py-8">
                                <flux:icon name="cube" class="mx-auto mb-2 size-8 text-zinc-400" />
                                <flux:text>{{ __('Belum ada produk.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Mobile Cards --}}
    <div class="space-y-3 md:hidden">
        @forelse ($this->produks as $produk)
            <flux:card wire:key="produk-mobile-{{ $produk->id }}" class="p-4">
                <div class="flex items-start gap-4">
                    <div class="shrink-0">
                        @if ($produk->foto_produk)
                            <img src="{{ Storage::url($produk->foto_produk) }}" alt="{{ $produk->nama_produk }}" class="size-16 rounded-xl object-cover shadow-sm">
                        @else
                            <div class="flex size-16 items-center justify-center rounded-xl bg-zinc-100 dark:bg-zinc-800">
                                <flux:icon name="photo" class="size-6 text-zinc-400" />
                            </div>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="flex items-start justify-between gap-2">
                            <flux:heading size="sm" class="line-clamp-2">{{ $produk->nama_produk }}</flux:heading>
                            @if (auth()->user()->hasPermission('produk.edit') || auth()->user()->hasPermission('produk.delete') || auth()->user()->hasPermission('produk.create'))
                                <flux:dropdown position="bottom" align="end">
                                    <flux:button variant="ghost" size="sm" class="-mt-2 -mr-2" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        @if (auth()->user()->hasPermission('produk.edit'))
                                            <flux:menu.item icon="pencil-square" :href="route('produk.edit', $produk)" wire:navigate>
                                                {{ __('Edit') }}
                                            </flux:menu.item>
                                        @endif
                                        @if (auth()->user()->hasPermission('produk.create') || auth()->user()->hasPermission('produk.edit'))
                                            <flux:menu.item icon="plus" wire:click="openAddVarianModal({{ $produk->id }})">
                                                {{ __('Tambah Varian') }}
                                            </flux:menu.item>
                                        @endif
                                        @if (auth()->user()->hasPermission('produk.delete'))
                                            <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $produk->id }})">
                                                {{ __('Hapus') }}
                                            </flux:menu.item>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-xs text-zinc-500">{{ $produk->kategori->nama }}</span>
                            <flux:badge size="sm" variant="pill" color="zinc">{{ $produk->varians->count() }} {{ __('varian') }}</flux:badge>
                        </div>
                    </div>
                </div>

                <flux:separator class="my-3" />

                <div class="flex items-center justify-between text-sm">
                    @php
                        $minHarga = $produk->varians->min('harga_jual');
                        $maxHarga = $produk->varians->max('harga_jual');
                    @endphp
                    <flux:text class="font-semibold text-zinc-900 dark:text-white">
                        @if ($produk->varians->isEmpty())
                            —
                        @elseif ($minHarga == $maxHarga)
                            Rp {{ number_format($minHarga, 0, ',', '.') }}
                        @else
                            Rp {{ number_format($minHarga, 0, ',', '.') }} - {{ number_format($maxHarga, 0, ',', '.') }}
                        @endif
                    </flux:text>
                </div>
            </flux:card>
        @empty
            <div class="py-12 text-center">
                <flux:icon name="cube" class="mx-auto mb-2 size-8 text-zinc-400" />
                <flux:text>{{ __('Belum ada produk.') }}</flux:text>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $this->produks->links() }}
    </div>

    {{-- Add Varian Modal --}}
    <flux:modal name="add-varian" class="max-w-lg">
        <form wire:submit="saveNewVarian" class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Tambah Varian Baru') }}</flux:heading>
                <flux:subheading>
                    {{ $addingVarianProdukId ? __('Menambahkan varian untuk produk: :nama', ['nama' => App\Models\Produk::find($addingVarianProdukId)?->nama_produk]) : '' }}
                </flux:subheading>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Nama Varian') }}</flux:label>
                    <flux:input wire:model="nama_varian" placeholder="Contoh: Jumbo, Merah, dll" required data-test="nama-varian-input" />
                    <flux:error name="nama_varian" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Satuan') }}</flux:label>
                    <button type="button" wire:click="showSatuanModal" data-test="satuan-select"
                        class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                        @if ($this->selectedSatuan)
                            <span class="text-zinc-900 dark:text-zinc-100">{{ $this->selectedSatuan->nama }}</span>
                        @else
                            <span class="text-zinc-400">{{ __('Pilih satuan...') }}</span>
                        @endif
                    </button>
                    <flux:error name="satuan_id" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Harga Jual') }}</flux:label>
                <flux:input wire:model="harga_jual" type="number" prefix="Rp" placeholder="0" min="0" step="100" required data-test="harga-jual-input" />
                <flux:error name="harga_jual" />
            </flux:field>

            <flux:card class="space-y-4 p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <flux:heading size="sm">{{ __('Atur Stok & Modal?') }}</flux:heading>
                    </div>
                    <flux:switch wire:model.live="aturStokModal" data-test="toggle-stok-modal" />
                </div>

                @if ($aturStokModal)
                    <div class="space-y-4">
                        <flux:field>
                            <div class="flex items-center justify-between">
                                <flux:label>{{ __('SKU') }}</flux:label>
                                <flux:button variant="ghost" size="sm" icon="sparkles" wire:click="generateVarianSku" data-test="generate-sku-button">
                                    {{ __('Auto') }}
                                </flux:button>
                            </div>
                            <flux:input wire:model="sku" placeholder="Contoh: SKU-0001-AB" data-test="sku-input" />
                            <flux:error name="sku" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Harga Modal') }}</flux:label>
                            <flux:input wire:model="harga_modal" type="number" prefix="Rp" placeholder="0" min="0" step="100" data-test="harga-modal-input" />
                            <flux:error name="harga_modal" />
                        </flux:field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('Stok Awal') }}</flux:label>
                                <flux:input wire:model="stok" type="number" placeholder="0" min="0" data-test="stok-input" />
                                <flux:error name="stok" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Minimum Stok') }}</flux:label>
                                <flux:input wire:model="minimum_stok" type="number" placeholder="0" min="0" data-test="minimum-stok-input" />
                                <flux:error name="minimum_stok" />
                            </flux:field>
                        </div>
                    </div>
                @endif
            </flux:card>

            <div class="flex gap-3">
                <flux:modal.close>
                    <flux:button class="w-full">{{ __('Batal') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit" class="w-full" data-test="save-varian-button">
                    {{ __('Simpan Varian') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Delete Confirmation Modal --}}
    <flux:modal name="confirm-delete" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Produk?') }}</flux:heading>
            <flux:text>{{ __('Produk beserta semua variannya akan dihapus. Yakin ingin melanjutkan?') }}</flux:text>
            <div class="flex gap-3">
                <flux:modal.close>
                    <flux:button class="w-full">{{ __('Batal') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" class="w-full" wire:click="deleteProduk" data-test="confirm-delete-button">
                    {{ __('Hapus') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Modal Kelola Satuan --}}
    <flux:modal name="kelola-satuan" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Pilih Satuan') }}</flux:heading>

            <flux:input wire:model.live="searchSatuan" placeholder="Cari satuan..." icon="magnifying-glass" clearable />

            <form wire:submit="createSatuan" class="flex gap-2">
                <flux:input wire:model="namaSatuanBaru" placeholder="Nama satuan baru..." class="flex-1" data-test="nama-satuan-input" />
                <flux:button variant="primary" type="submit" icon="plus" data-test="save-satuan-button">
                    {{ __('Tambah') }}
                </flux:button>
            </form>
            <flux:error name="namaSatuanBaru" />

            <div class="max-h-64 divide-y divide-zinc-100 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                @forelse ($this->satuans as $satuan)
                    <div wire:key="satuan-{{ $satuan->id }}"
                        class="flex items-center gap-2 px-3 py-2 {{ $satuan_id === (string) $satuan->id ? 'bg-blue-50 dark:bg-blue-950' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                        @if ($editingSatuanId === $satuan->id)
                            <flux:input wire:model="editingSatuanNama" class="flex-1 text-sm" wire:keydown.enter="updateSatuan" wire:keydown.escape="cancelEditSatuan" autofocus />
                            <flux:button size="xs" variant="primary" wire:click="updateSatuan" icon="check" />
                            <flux:button size="xs" wire:click="cancelEditSatuan" icon="x-mark" />
                        @else
                            <button type="button" wire:click="selectSatuan({{ $satuan->id }})" class="flex flex-1 items-center gap-2 text-left text-sm">
                                @if ($satuan_id === (string) $satuan->id)
                                    <flux:icon name="check" class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                @else
                                    <span class="size-4 shrink-0"></span>
                                @endif
                                <span class="{{ $satuan_id === (string) $satuan->id ? 'font-medium text-blue-700 dark:text-blue-300' : 'text-zinc-800 dark:text-zinc-200' }}">
                                    {{ $satuan->nama }}
                                </span>
                            </button>
                            <flux:button size="xs" variant="ghost" icon="pencil" wire:click="startEditSatuan({{ $satuan->id }}, '{{ addslashes($satuan->nama) }}')" />
                            <flux:button size="xs" variant="ghost" icon="trash" class="text-red-500 hover:text-red-600"
                                wire:click="deleteSatuan({{ $satuan->id }})"
                                wire:confirm="{{ __('Hapus satuan \':nama\'?', ['nama' => $satuan->nama]) }}" />
                        @endif
                    </div>
                @empty
                    <p class="px-3 py-6 text-center text-sm text-zinc-400">
                        {{ $searchSatuan ? __('Tidak ditemukan.') : __('Belum ada satuan.') }}
                    </p>
                @endforelse
            </div>

            <flux:modal.close>
                <flux:button class="w-full">{{ __('Tutup') }}</flux:button>
            </flux:modal.close>
        </div>
    </flux:modal>
</div>