<?php

use App\Models\Produk;
use App\Models\Promo;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Tambah Promo')] class extends Component {
    public string $nama = '';
    public ?string $tanggal_mulai = null;
    public ?string $tanggal_selesai = null;
    public bool $is_active = true;

    /** @var array<int, array{produk_varian_id: int, produk_nama: string, varian_nama: string, harga_jual: string, minimal_harga_jual: string}> */
    public array $selectedVarians = [];

    /** Product search for adding varians */
    public string $searchProduk = '';

    public function addVarian(int $produkVarianId, string $produkNama, string $varianNama, string $hargaJual, string $hargaModal): void
    {
        // Check if already added
        foreach ($this->selectedVarians as $v) {
            if ($v['produk_varian_id'] === $produkVarianId) {
                Flux::toast(variant: 'warning', text: __('Varian ini sudah ditambahkan.'));

                return;
            }
        }

        $this->selectedVarians[] = [
            'produk_varian_id' => $produkVarianId,
            'produk_nama' => $produkNama,
            'varian_nama' => $varianNama,
            'harga_jual' => $hargaJual,
            'harga_modal' => $hargaModal,
            'minimal_harga_jual' => $hargaJual,
        ];
    }

    public function removeVarian(int $index): void
    {
        unset($this->selectedVarians[$index]);
        $this->selectedVarians = array_values($this->selectedVarians);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'is_active' => ['boolean'],
            'selectedVarians' => ['required', 'array', 'min:1'],
            'selectedVarians.*.produk_varian_id' => ['required', 'exists:produk_varians,id'],
            'selectedVarians.*.minimal_harga_jual' => ['required', 'numeric', 'min:0'],
        ], [
            'selectedVarians.required' => __('Pilih minimal 1 varian produk.'),
            'selectedVarians.min' => __('Pilih minimal 1 varian produk.'),
            'tanggal_selesai.after_or_equal' => __('Tanggal selesai harus setelah tanggal mulai.'),
        ]);

        $promo = Promo::create([
            'nama' => $validated['nama'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'is_active' => $validated['is_active'],
        ]);

        foreach ($validated['selectedVarians'] as $varian) {
            $promo->produkVarians()->attach($varian['produk_varian_id'], [
                'minimal_harga_jual' => $varian['minimal_harga_jual'],
            ]);
        }

        Flux::toast(variant: 'success', text: __('Promo berhasil dibuat.'));
        $this->redirectRoute('promo.index', navigate: true);
    }

    #[Computed]
    public function produkResults()
    {
        if (strlen($this->searchProduk) < 2) {
            return collect();
        }

        return Produk::query()
            ->with(['varians.satuan'])
            ->where('nama_produk', 'like', "%{$this->searchProduk}%")
            ->orWhereHas('varians', fn (Builder $q) => $q->where('sku', 'like', "%{$this->searchProduk}%"))
            ->limit(10)
            ->get();
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    {{-- Header --}}
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('promo.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Tambah Promo') }}</flux:heading>
            <flux:subheading>{{ __('Buat promo baru dengan batas minimal harga jual per varian') }}</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{-- Promo Info --}}
        <flux:card class="space-y-4 p-6">
            <flux:heading size="sm">{{ __('Informasi Promo') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Nama Promo') }}</flux:label>
                <flux:input wire:model="nama" placeholder="Contoh: Promo Akhir Tahun" required data-test="nama-promo-input" />
                <flux:error name="nama" />
            </flux:field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Tanggal Mulai') }}</flux:label>
                    <flux:input wire:model="tanggal_mulai" type="date" data-test="tanggal-mulai-input" />
                    <flux:error name="tanggal_mulai" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tanggal Selesai') }}</flux:label>
                    <flux:input wire:model="tanggal_selesai" type="date" data-test="tanggal-selesai-input" />
                    <flux:error name="tanggal_selesai" />
                </flux:field>
            </div>

            <div class="flex items-center justify-between rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-800/50">
                <flux:text class="text-sm">{{ __('Aktifkan Promo?') }}</flux:text>
                <flux:switch wire:model.live="is_active" data-test="is-active-switch" />
            </div>
        </flux:card>

        {{-- Select Product Varians --}}
        <flux:card class="space-y-4 p-6">
            <div>
                <flux:heading size="sm">{{ __('Produk Varian') }}</flux:heading>
                <flux:subheading>{{ __('Cari dan pilih produk varian yang ikut promo ini') }}</flux:subheading>
            </div>

            {{-- Search products --}}
            <div class="relative">
                <flux:input wire:model.live.debounce.300ms="searchProduk" icon="magnifying-glass" :placeholder="__('Cari produk atau SKU...')" clearable data-test="search-produk-input" />

                @if ($this->produkResults->isNotEmpty())
                    <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="max-h-64 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->produkResults as $produk)
                                @foreach ($produk->varians as $varian)
                                    <button
                                        type="button"
                                        wire:click="addVarian({{ $varian->id }}, '{{ addslashes($produk->nama_produk) }}', '{{ addslashes($varian->nama_varian) }}', '{{ $varian->harga_jual }}', '{{ $varian->harga_modal }}')"
                                        class="flex w-full items-center justify-between px-3 py-2 text-left text-sm transition hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                    >
                                        <div>
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $produk->nama_produk }}</span>
                                            <span class="text-zinc-500"> — {{ $varian->nama_varian }}</span>
                                            @if ($varian->sku)
                                                <span class="text-xs text-zinc-400 ml-1">({{ $varian->sku }})</span>
                                            @endif
                                        </div>
                                        <span class="shrink-0 text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                            Rp {{ number_format($varian->harga_jual, 0, ',', '.') }}
                                        </span>
                                    </button>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <flux:error name="selectedVarians" />

            {{-- Selected Varians --}}
            @if (count($selectedVarians) > 0)
                <div class="space-y-3">
                    @foreach ($selectedVarians as $index => $varian)
                        <div wire:key="selected-varian-{{ $index }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $varian['produk_nama'] }}</div>
                                    <div class="text-sm text-zinc-500">{{ $varian['varian_nama'] }} — Harga Jual: Rp {{ number_format($varian['harga_jual'], 0, ',', '.') }} | Harga Modal: Rp {{ number_format($varian['harga_modal'], 0, ',', '.') }}</div>
                                </div>
                                <flux:button variant="ghost" size="xs" icon="x-mark" class="shrink-0 text-red-500" wire:click="removeVarian({{ $index }})" />
                            </div>

                            <div class="mt-3">
                                <flux:field>
                                    <flux:label>{{ __('Minimal Harga Jual') }}</flux:label>
                                    <flux:input
                                        wire:model="selectedVarians.{{ $index }}.minimal_harga_jual"
                                        type="number"
                                        prefix="Rp"
                                        placeholder="0"
                                        min="0"
                                        step="100"
                                        required
                                        data-test="minimal-harga-input-{{ $index }}"
                                    />
                                    <flux:error name="selectedVarians.{{ $index }}.minimal_harga_jual" />
                                    <flux:text class="text-xs text-zinc-400 mt-1">
                                        {{ __('Kasir tidak bisa memberikan harga di bawah nominal ini') }}
                                    </flux:text>
                                </flux:field>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="rounded-lg border-2 border-dashed border-zinc-200 py-8 text-center dark:border-zinc-700">
                    <flux:icon name="tag" class="mx-auto mb-2 size-8 text-zinc-400" />
                    <flux:text class="text-zinc-400">{{ __('Belum ada produk varian yang dipilih.') }}</flux:text>
                    <flux:text class="text-xs text-zinc-400 mt-1">{{ __('Gunakan pencarian di atas untuk menambahkan produk.') }}</flux:text>
                </div>
            @endif
        </flux:card>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" class="flex-1 sm:flex-none" data-test="save-button">
                {{ __('Simpan Promo') }}
            </flux:button>
            <flux:button :href="route('promo.index')" wire:navigate>
                {{ __('Batal') }}
            </flux:button>
        </div>
    </form>
</div>
