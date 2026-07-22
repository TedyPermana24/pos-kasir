<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Edit Produk')] class extends Component {
    use WithFileUploads;

    public Produk $produk;

    public UploadedFile|string|null $foto_produk = null;

    public string $nama_produk = '';

    public string $kategori_id = '';

    /** @var array<int, array{id: int|null, nama_varian: string, satuan_id: string, harga_jual: string, atur_stok: bool, sku: string, harga_modal: string, stok: string, minimum_stok: string}> */
    public array $varians = [];

    /** IDs of varians that have been removed by user */
    public array $removedVarianIds = [];

    /** Kategori modal state */
    public string $searchKategori = '';

    public string $namaKategoriBaru = '';

    public ?int $editingKategoriId = null;

    public string $editingKategoriNama = '';

    /** Satuan modal state */
    public string $searchSatuan = '';

    public string $namaSatuanBaru = '';

    public ?int $editingSatuanId = null;

    public string $editingSatuanNama = '';

    public ?int $selectingSatuanForVarianIndex = null;

    public function mount(Produk $produk): void
    {
        $this->produk = $produk;
        $this->foto_produk = $produk->foto_produk;
        $this->nama_produk = $produk->nama_produk;
        $this->kategori_id = (string) $produk->kategori_id;

        foreach ($produk->varians as $varian) {
            $hasStockData = $varian->stok !== null;

            $this->varians[] = [
                'id' => $varian->id,
                'nama_varian' => $varian->nama_varian,
                'satuan_id' => (string) $varian->satuan_id,
                'harga_jual' => (string) $varian->harga_jual,
                'atur_stok' => $hasStockData,
                'sku' => $varian->sku ?? '',
                'harga_modal' => $varian->harga_modal ? (string) $varian->harga_modal : '',
                'stok' => $varian->stok !== null ? (string) $varian->stok : '',
                'minimum_stok' => $varian->minimum_stok !== null ? (string) $varian->minimum_stok : '',
            ];
        }

        if (empty($this->varians)) {
            $this->addVarian();
        }
    }

    public function addVarian(): void
    {
        $this->varians[] = [
            'id' => null,
            'nama_varian' => '',
            'satuan_id' => '',
            'harga_jual' => '',
            'atur_stok' => false,
            'sku' => '',
            'harga_modal' => '',
            'stok' => '',
            'minimum_stok' => '',
        ];
    }

    public function removeVarian(int $index): void
    {
        if (count($this->varians) <= 1) {
            Flux::toast(variant: 'warning', text: __('Minimal harus ada 1 varian.'));

            return;
        }

        if (isset($this->varians[$index]['id']) && $this->varians[$index]['id']) {
            $this->removedVarianIds[] = $this->varians[$index]['id'];
        }

        unset($this->varians[$index]);
        $this->varians = array_values($this->varians);
    }

    public ?int $scanningSkuIndex = null;

    #[Livewire\Attributes\On('sku-scanned')]
    public function onSkuScanned($sku): void
    {
        $skuVal = is_array($sku) ? ($sku['sku'] ?? '') : (string) $sku;
        if ($this->scanningSkuIndex !== null && isset($this->varians[$this->scanningSkuIndex])) {
            $this->varians[$this->scanningSkuIndex]['sku'] = $skuVal;
            $this->scanningSkuIndex = null;
            Flux::toast(variant: 'success', text: __('SKU berhasil dipindai: :sku', ['sku' => $skuVal]));
        }
    }

    /** Kategori management */
    public function showKategoriModal(): void
    {
        Flux::modal('kelola-kategori')->show();
    }

    public function selectKategori(int $id): void
    {
        $this->kategori_id = (string) $id;
        Flux::modal('kelola-kategori')->close();
    }

    public function createKategori(): void
    {
        $this->validate([
            'namaKategoriBaru' => ['required', 'string', 'max:255', 'unique:kategoris,nama'],
        ]);

        $kategori = Kategori::create(['nama' => $this->namaKategoriBaru]);
        $this->namaKategoriBaru = '';
        $this->searchKategori = '';

        Flux::toast(variant: 'success', text: __('Kategori ":nama" berhasil ditambahkan.', ['nama' => $kategori->nama]));
    }

    public function startEditKategori(int $id, string $nama): void
    {
        $this->editingKategoriId = $id;
        $this->editingKategoriNama = $nama;
    }

    public function cancelEditKategori(): void
    {
        $this->editingKategoriId = null;
        $this->editingKategoriNama = '';
    }

    public function updateKategori(): void
    {
        $this->validate([
            'editingKategoriNama' => ['required', 'string', 'max:255',
                Rule::unique('kategoris', 'nama')->ignore($this->editingKategoriId)],
        ]);

        Kategori::findOrFail($this->editingKategoriId)->update(['nama' => $this->editingKategoriNama]);
        Flux::toast(variant: 'success', text: __('Kategori berhasil diperbarui.'));
        $this->cancelEditKategori();
    }

    public function deleteKategori(int $id): void
    {
        Kategori::findOrFail($id)->delete();

        if ($this->kategori_id === (string) $id) {
            $this->kategori_id = '';
        }

        Flux::toast(variant: 'success', text: __('Kategori berhasil dihapus.'));
    }

    /** Satuan management */
    public function showSatuanModal(): void
    {
        $this->selectingSatuanForVarianIndex = null;
        Flux::modal('kelola-satuan')->show();
    }

    public function showSatuanModalForVarian(int $index): void
    {
        $this->selectingSatuanForVarianIndex = $index;
        Flux::modal('kelola-satuan')->show();
    }

    public function selectSatuan(int $id): void
    {
        if ($this->selectingSatuanForVarianIndex !== null) {
            $this->varians[$this->selectingSatuanForVarianIndex]['satuan_id'] = (string) $id;
            $this->selectingSatuanForVarianIndex = null;
        }
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
                Rule::unique('satuans', 'nama')->ignore($this->editingSatuanId)],
        ]);

        Satuan::findOrFail($this->editingSatuanId)->update(['nama' => $this->editingSatuanNama]);
        Flux::toast(variant: 'success', text: __('Satuan berhasil diperbarui.'));
        $this->cancelEditSatuan();
    }

    public function deleteSatuan(int $id): void
    {
        Satuan::findOrFail($id)->delete();
        Flux::toast(variant: 'success', text: __('Satuan berhasil dihapus.'));
    }

    /**
     * Update the product and its variants.
     */
    public function save(): void
    {
        $rules = [
            'nama_produk' => ['required', 'string', 'max:255'],
            'kategori_id' => ['required', 'exists:kategoris,id'],
            'foto_produk' => $this->foto_produk instanceof UploadedFile ? ['image', 'max:2048'] : ['nullable'],
            'varians' => ['required', 'array', 'min:1'],
            'varians.*.nama_varian' => ['required', 'string', 'max:255'],
            'varians.*.satuan_id' => ['required', 'exists:satuans,id'],
            'varians.*.harga_jual' => ['required', 'numeric', 'min:0'],
        ];

        foreach ($this->varians as $i => $varian) {
            if ($varian['atur_stok']) {
                $ignoreId = $varian['id'] ?? 0;
                $rules["varians.{$i}.sku"] = ['nullable', 'string', 'max:50', "unique:produk_varians,sku,{$ignoreId}"];
                $rules["varians.{$i}.harga_modal"] = ['nullable', 'numeric', 'min:0'];
                $rules["varians.{$i}.stok"] = ['nullable', 'integer', 'min:0'];
                $rules["varians.{$i}.minimum_stok"] = ['nullable', 'integer', 'min:0'];
            }
        }

        $validated = $this->validate($rules);

        // Handle photo
        if ($this->foto_produk instanceof UploadedFile) {
            if ($this->produk->foto_produk) {
                Storage::disk('public')->delete($this->produk->foto_produk);
            }

            $fotoPath = $this->foto_produk->store('produk', 'public');
        } else {
            $fotoPath = $this->produk->foto_produk;
        }

        $this->produk->update([
            'nama_produk' => $validated['nama_produk'],
            'kategori_id' => $validated['kategori_id'],
            'foto_produk' => $fotoPath,
        ]);

        // Delete removed varians
        if (! empty($this->removedVarianIds)) {
            ProdukVarian::whereIn('id', $this->removedVarianIds)->delete();
        }

        // Upsert varians
        foreach ($this->varians as $varian) {
            $data = [
                'produk_id' => $this->produk->id,
                'satuan_id' => $varian['satuan_id'],
                'nama_varian' => $varian['nama_varian'],
                'harga_jual' => $varian['harga_jual'],
                'sku' => $varian['atur_stok'] ? ($varian['sku'] ?: null) : null,
                'harga_modal' => $varian['atur_stok'] ? (($varian['harga_modal'] ?? '') !== '' ? (float) $varian['harga_modal'] : null) : null,
                'stok' => $varian['atur_stok'] ? (($varian['stok'] ?? '') !== '' ? (int) $varian['stok'] : 0) : null,
                'minimum_stok' => $varian['atur_stok'] ? (($varian['minimum_stok'] ?? '') !== '' ? (int) $varian['minimum_stok'] : 0) : null,
            ];

            if ($varian['id']) {
                ProdukVarian::findOrFail($varian['id'])->update($data);
            } else {
                $this->produk->varians()->create($data);
            }
        }

        Flux::toast(variant: 'success', text: __('Produk berhasil diperbarui.'));
        $this->redirectRoute('produk.index', navigate: true);
    }

    #[Computed]
    public function kategoris()
    {
        return Kategori::orderBy('nama')
            ->when($this->searchKategori, fn ($q) => $q->where('nama', 'like', "%{$this->searchKategori}%"))
            ->get();
    }

    #[Computed]
    public function satuans()
    {
        return Satuan::orderBy('nama')
            ->when($this->searchSatuan, fn ($q) => $q->where('nama', 'like', "%{$this->searchSatuan}%"))
            ->get();
    }

    #[Computed]
    public function selectedKategori()
    {
        return $this->kategori_id ? Kategori::find($this->kategori_id) : null;
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('produk.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Edit Produk') }}</flux:heading>
            <flux:subheading>{{ $produk->nama_produk }}</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Basic Info --}}
        <flux:card class="space-y-4 p-6">
            <flux:heading size="sm">{{ __('Informasi Produk') }}</flux:heading>

            {{-- Foto Upload --}}
            <div class="flex flex-col items-center">
                <div class="relative size-40">
                    <input type="file" id="foto-produk-input" wire:model="foto_produk" accept="image/*" class="hidden" />
                    <label for="foto-produk-input"
                        class="group flex size-full cursor-pointer flex-col items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-zinc-300 bg-zinc-50 transition hover:border-zinc-400 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-500 dark:hover:bg-zinc-800">
                        @if ($foto_produk instanceof \Illuminate\Http\UploadedFile)
                            <img src="{{ $foto_produk->temporaryUrl() }}" alt="Preview" class="size-full object-cover">
                            <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                <span class="text-sm font-medium text-white">{{ __('Ganti Foto') }}</span>
                            </div>
                        @elseif ($produk->foto_produk)
                            <img src="{{ Storage::url($produk->foto_produk) }}" alt="{{ $produk->nama_produk }}" class="size-full object-cover">
                            <div class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                <span class="text-sm font-medium text-white">{{ __('Ganti Foto') }}</span>
                            </div>
                        @else
                            <div class="flex flex-col items-center text-zinc-400 dark:text-zinc-500">
                                <flux:icon name="camera" class="mb-2 size-8" />
                                <span class="text-center text-xs font-medium">{{ __('Klik untuk') }}<br>{{ __('Galeri / Kamera') }}</span>
                            </div>
                        @endif
                    </label>
                </div>
                <div wire:loading wire:target="foto_produk" class="mt-2 text-sm text-zinc-500">
                    {{ __('Mengunggah foto...') }}
                </div>
                <flux:error name="foto_produk" class="mt-2" />
            </div>

            <flux:field>
                <flux:label>{{ __('Nama Produk') }}</flux:label>
                <flux:input wire:model="nama_produk" placeholder="Masukkan nama produk" required data-test="nama-produk-input" />
                <flux:error name="nama_produk" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Kategori') }}</flux:label>
                <button type="button" wire:click="showKategoriModal" data-test="kategori-select"
                    class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                    @if ($this->selectedKategori)
                        <span class="text-zinc-900 dark:text-zinc-100">{{ $this->selectedKategori->nama }}</span>
                    @else
                        <span class="text-zinc-400">{{ __('Pilih kategori...') }}</span>
                    @endif
                </button>
                <flux:error name="kategori_id" />
            </flux:field>
        </flux:card>

        {{-- Varian Section Header --}}
        <div class="flex items-center justify-between pt-2">
            <div>
                <flux:heading size="lg">{{ __('Daftar Varian Produk') }}</flux:heading>
                <flux:subheading>{{ __('Setiap varian memiliki harga, satuan, dan pengaturan stok mandiri') }}</flux:subheading>
            </div>
            <flux:button variant="primary" size="sm" icon="plus" wire:click="addVarian" data-test="add-varian-button">
                {{ __('Tambah Varian') }}
            </flux:button>
        </div>

        {{-- Individual Variant Cards --}}
        <div class="space-y-4">
            @foreach ($varians as $index => $varian)
                <flux:card wire:key="varian-{{ $index }}" class="space-y-4 p-6 border border-zinc-200 dark:border-zinc-700">
                    {{-- Card Header Bar --}}
                    <div class="flex items-center justify-between border-b border-zinc-100 pb-3 dark:border-zinc-800">
                        <div class="flex items-center gap-2">
                            <span class="rounded-lg bg-emerald-500/10 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                                {{ __('Varian #:num', ['num' => $index + 1]) }}
                            </span>
                            @if (!empty($varian['nama_varian']))
                                <span class="text-sm font-semibold text-zinc-900 dark:text-white">
                                    {{ $varian['nama_varian'] }}
                                </span>
                            @endif
                        </div>

                        @if (count($varians) > 1)
                            <flux:button variant="ghost" size="xs" icon="trash" class="text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40" wire:click="removeVarian({{ $index }})">
                                {{ __('Hapus Varian') }}
                            </flux:button>
                        @endif
                    </div>

                    {{-- Form Fields Grid --}}
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Nama Varian') }} <span class="text-rose-500">*</span></flux:label>
                            <flux:input wire:model="varians.{{ $index }}.nama_varian" placeholder="Contoh: Kecil, Besar, 500ml" required />
                            <flux:error name="varians.{{ $index }}.nama_varian" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Satuan') }} <span class="text-rose-500">*</span></flux:label>
                            @php
                                $satuanId = $varians[$index]['satuan_id'] ?? null;
                                $satuanName = $satuanId ? $this->satuans->firstWhere('id', $satuanId)?->nama : null;
                            @endphp
                            <button type="button" wire:click="showSatuanModalForVarian({{ $index }})" data-test="satuan-select"
                                class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-sm transition hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600">
                                @if ($satuanName)
                                    <span class="text-zinc-900 dark:text-zinc-100">{{ $satuanName }}</span>
                                @else
                                    <span class="text-zinc-400">{{ __('Pilih satuan...') }}</span>
                                @endif
                            </button>
                            <flux:error name="varians.{{ $index }}.satuan_id" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Harga Jual') }} <span class="text-rose-500">*</span></flux:label>
                        <div x-data="{
                            format(val) {
                                if (val === null || val === undefined || val === '') return '';
                                let num = Math.floor(parseFloat(val.toString()));
                                return isNaN(num) || num <= 0 ? '' : Number(num).toLocaleString('id-ID');
                            },
                            update(e) {
                                let digits = e.target.value.replace(/[^0-9]/g, '');
                                let num = parseInt(digits, 10) || 0;
                                $wire.set('varians.{{ $index }}.harga_jual', num > 0 ? num : '', false);
                                e.target.value = num > 0 ? Number(num).toLocaleString('id-ID') : '';
                            }
                        }">
                            <flux:input.group>
                                <flux:input.group.prefix>Rp</flux:input.group.prefix>
                                <flux:input
                                    type="text"
                                    x-init="$el.value = format($wire.get('varians.{{ $index }}.harga_jual'))"
                                    x-on:input="update($event)"
                                    placeholder="0"
                                    required
                                />
                            </flux:input.group>
                        </div>
                        <flux:error name="varians.{{ $index }}.harga_jual" />
                    </flux:field>

                    {{-- Stock & Modal Management Sub-card --}}
                    <div class="rounded-xl border border-zinc-200/80 bg-zinc-50 p-4 dark:border-zinc-700/80 dark:bg-zinc-800/60 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Atur Stok & Modal?') }}</span>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Aktifkan jika varian ini menggunakan batas stok dan harga modal') }}</p>
                            </div>
                            <flux:switch wire:model.live="varians.{{ $index }}.atur_stok" />
                        </div>

                        @if ($varian['atur_stok'])
                            <div class="space-y-3 pt-2 border-t border-zinc-200/60 dark:border-zinc-700/60">
                                <flux:field>
                                    <flux:label>{{ __('SKU') }}</flux:label>
                                    <flux:input wire:model="varians.{{ $index }}.sku" placeholder="Contoh: SKU-0001-AB" />
                                    <flux:error name="varians.{{ $index }}.sku" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Harga Modal') }}</flux:label>
                                    <div x-data="{
                                        format(val) {
                                            if (val === null || val === undefined || val === '') return '';
                                            let num = Math.floor(parseFloat(val.toString()));
                                            return isNaN(num) || num <= 0 ? '' : Number(num).toLocaleString('id-ID');
                                        },
                                        update(e) {
                                            let digits = e.target.value.replace(/[^0-9]/g, '');
                                            let num = parseInt(digits, 10) || 0;
                                            $wire.set('varians.{{ $index }}.harga_modal', num > 0 ? num : '', false);
                                            e.target.value = num > 0 ? Number(num).toLocaleString('id-ID') : '';
                                        }
                                    }">
                                        <flux:input.group>
                                            <flux:input.group.prefix>Rp</flux:input.group.prefix>
                                            <flux:input
                                                type="text"
                                                x-init="$el.value = format($wire.get('varians.{{ $index }}.harga_modal'))"
                                                x-on:input="update($event)"
                                                placeholder="0"
                                            />
                                        </flux:input.group>
                                    </div>
                                    <flux:error name="varians.{{ $index }}.harga_modal" />
                                </flux:field>

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <flux:field>
                                        <flux:label>{{ __('Stok Saat Ini') }}</flux:label>
                                        <flux:input wire:model="varians.{{ $index }}.stok" type="number" placeholder="0" min="0" />
                                        <flux:error name="varians.{{ $index }}.stok" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Minimum Stok') }}</flux:label>
                                        <flux:input wire:model="varians.{{ $index }}.minimum_stok" type="number" placeholder="0" min="0" />
                                        <flux:error name="varians.{{ $index }}.minimum_stok" />
                                    </flux:field>
                                </div>
                            </div>
                        @endif
                    </div>
                </flux:card>
            @endforeach

            {{-- Bottom Add Variant Button --}}
            <button
                type="button"
                wire:click="addVarian"
                class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-zinc-300 py-3.5 text-sm font-semibold text-zinc-600 transition hover:border-emerald-500 hover:bg-emerald-50/50 hover:text-emerald-700 active:scale-[0.99] dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-emerald-500 dark:hover:bg-emerald-950/30"
            >
                <flux:icon name="plus" class="size-4" />
                <span>{{ __('Tambah Varian Baru') }}</span>
            </button>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-2">
            <flux:button variant="primary" type="submit" class="flex-1 sm:flex-none" data-test="save-button">
                {{ __('Simpan Perubahan') }}
            </flux:button>
            <flux:button :href="route('produk.index')" wire:navigate>
                {{ __('Batal') }}
            </flux:button>
        </div>
    </form>

    {{-- Modal Kelola Kategori --}}
    <flux:modal name="kelola-kategori" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Pilih Kategori') }}</flux:heading>

            <flux:input wire:model.live="searchKategori" placeholder="Cari kategori..." icon="magnifying-glass" clearable />

            <form wire:submit="createKategori" class="flex gap-2">
                <flux:input wire:model="namaKategoriBaru" placeholder="Nama kategori baru..." class="flex-1" data-test="nama-kategori-input" />
                <flux:button variant="primary" type="submit" icon="plus" data-test="save-kategori-button">
                    {{ __('Tambah') }}
                </flux:button>
            </form>
            <flux:error name="namaKategoriBaru" />

            <div class="max-h-64 divide-y divide-zinc-100 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-700">
                @forelse ($this->kategoris as $kategori)
                    <div wire:key="kategori-{{ $kategori->id }}"
                        class="flex items-center gap-2 px-3 py-2 {{ $kategori_id === (string) $kategori->id ? 'bg-blue-50 dark:bg-blue-950' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800' }}">
                        @if ($editingKategoriId === $kategori->id)
                            <flux:input wire:model="editingKategoriNama" class="flex-1 text-sm" wire:keydown.enter="updateKategori" wire:keydown.escape="cancelEditKategori" autofocus />
                            <flux:button size="xs" variant="primary" wire:click="updateKategori" icon="check" />
                            <flux:button size="xs" wire:click="cancelEditKategori" icon="x-mark" />
                        @else
                            <button type="button" wire:click="selectKategori({{ $kategori->id }})" class="flex flex-1 items-center gap-2 text-left text-sm">
                                @if ($kategori_id === (string) $kategori->id)
                                    <flux:icon name="check" class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                @else
                                    <span class="size-4 shrink-0"></span>
                                @endif
                                <span class="{{ $kategori_id === (string) $kategori->id ? 'font-medium text-blue-700 dark:text-blue-300' : 'text-zinc-800 dark:text-zinc-200' }}">
                                    {{ $kategori->nama }}
                                </span>
                            </button>
                            <flux:button size="xs" variant="ghost" icon="pencil" wire:click="startEditKategori({{ $kategori->id }}, '{{ addslashes($kategori->nama) }}')" />
                            <flux:button size="xs" variant="ghost" icon="trash" class="text-red-500 hover:text-red-600"
                                wire:click="deleteKategori({{ $kategori->id }})"
                                wire:confirm="{{ __('Hapus kategori \':nama\'?', ['nama' => $kategori->nama]) }}" />
                        @endif
                    </div>
                @empty
                    <p class="px-3 py-6 text-center text-sm text-zinc-400">
                        {{ $searchKategori ? __('Tidak ditemukan.') : __('Belum ada kategori.') }}
                    </p>
                @endforelse
            </div>

            <flux:modal.close>
                <flux:button class="w-full">{{ __('Tutup') }}</flux:button>
            </flux:modal.close>
        </div>
    </flux:modal>

    {{-- Modal Kelola Satuan --}}
    <flux:modal name="kelola-satuan" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Kelola Satuan') }}</flux:heading>

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
                        class="flex items-center gap-2 px-3 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        @if ($editingSatuanId === $satuan->id)
                            <flux:input wire:model="editingSatuanNama" class="flex-1 text-sm" wire:keydown.enter="updateSatuan" wire:keydown.escape="cancelEditSatuan" autofocus />
                            <flux:button size="xs" variant="primary" wire:click="updateSatuan" icon="check" />
                            <flux:button size="xs" wire:click="cancelEditSatuan" icon="x-mark" />
                        @else
                            <button type="button" wire:click="selectSatuan({{ $satuan->id }})" class="flex flex-1 items-center gap-2 text-left text-sm">
                                @php
                                    $isSelected = $this->selectingSatuanForVarianIndex !== null && ($varians[$this->selectingSatuanForVarianIndex]['satuan_id'] ?? '') === (string) $satuan->id;
                                @endphp
                                @if ($isSelected)
                                    <flux:icon name="check" class="size-4 shrink-0 text-blue-600 dark:text-blue-400" />
                                @else
                                    <span class="size-4 shrink-0"></span>
                                @endif
                                <span class="{{ $isSelected ? 'font-medium text-blue-700 dark:text-blue-300' : 'text-zinc-800 dark:text-zinc-200' }}">
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
