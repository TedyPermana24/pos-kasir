<?php

use App\Models\Kategori;
use App\Models\Produk;
use App\Models\ProdukVarian;
use App\Models\Satuan;
use Flux\Flux;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Tambah Produk')] class extends Component {
    use WithFileUploads;

    public UploadedFile|string|null $foto_produk = null;
    public string $nama_produk = '';
    public string $kategori_id = '';
    public string $satuan_id = '';
    public string $harga_jual = '';

    public bool $aturStokModal = false;
    public string $sku = '';
    public string $harga_modal = '';
    public string $stok = '';
    public string $minimum_stok = '';

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

    #[Livewire\Attributes\On('sku-scanned')]
    public function onSkuScanned($sku): void
    {
        $this->sku = $sku;
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
                Rule::unique('satuans', 'nama')->ignore($this->editingSatuanId)],
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

    /**
     * Save the product and its default variant.
     */
    public function save(): void
    {
        $rules = [
            'nama_produk' => ['required', 'string', 'max:255'],
            'kategori_id' => ['required', 'exists:kategoris,id'],
            'foto_produk' => ['nullable', 'image', 'max:2048'],
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

        $fotoPath = ($this->foto_produk instanceof UploadedFile)
            ? $this->foto_produk->store('produk', 'public')
            : null;

        $produk = Produk::create([
            'nama_produk' => $validated['nama_produk'],
            'kategori_id' => $validated['kategori_id'],
            'foto_produk' => $fotoPath,
        ]);

        $produk->varians()->create([
            'satuan_id' => $validated['satuan_id'],
            'nama_varian' => 'Default',
            'harga_jual' => $validated['harga_jual'],
            'sku' => $this->aturStokModal ? ($validated['sku'] ?: null) : null,
            'harga_modal' => $this->aturStokModal ? ($validated['harga_modal'] ?: 0) : 0,
            'stok' => $this->aturStokModal ? ($validated['stok'] ?: 0) : 0,
            'minimum_stok' => $this->aturStokModal ? ($validated['minimum_stok'] ?: 0) : 0,
        ]);

        Flux::toast(variant: 'success', text: __('Produk berhasil ditambahkan.'));
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

    #[Computed]
    public function selectedSatuan()
    {
        return $this->satuan_id ? Satuan::find($this->satuan_id) : null;
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('produk.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Tambah Produk') }}</flux:heading>
            <flux:subheading>{{ __('Isi informasi produk baru') }}</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
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

            <flux:field>
                <flux:label>{{ __('Satuan Dasar') }}</flux:label>
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

            <flux:field>
                <flux:label>{{ __('Harga Jual') }}</flux:label>
                <flux:input wire:model="harga_jual" type="number" prefix="Rp" placeholder="0" min="0" step="100" required data-test="harga-jual-input" />
                <flux:error name="harga_jual" />
            </flux:field>
        </flux:card>

        {{-- Stock & Modal Toggle --}}
        <flux:card class="space-y-4 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm">{{ __('Atur Stok & Modal?') }}</flux:heading>
                    <flux:subheading>{{ __('Aktifkan untuk mengatur SKU, harga modal, stok, dan minimum stok') }}</flux:subheading>
                </div>
                <flux:switch wire:model.live="aturStokModal" data-test="toggle-stok-modal" />
            </div>

            @if ($aturStokModal)
                <flux:field>
                    <div class="flex items-center justify-between">
                        <flux:label>{{ __('SKU') }}</flux:label>
                        <flux:button variant="ghost" size="sm" icon="qr-code" onclick="Flux.modal('barcode-scanner').show()" data-test="scan-sku-button">
                            {{ __('Scan') }}
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

                <flux:separator />

                <div>
                    <flux:heading size="sm">{{ __('Pengaturan Stok') }}</flux:heading>
                    <flux:subheading>{{ __('Atur stok awal dan batas minimum') }}</flux:subheading>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
            @endif
        </flux:card>

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" class="flex-1 sm:flex-none" data-test="save-button">
                {{ __('Simpan Produk') }}
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

    <x-barcode-scanner eventName="sku-scanned" />
</div>
