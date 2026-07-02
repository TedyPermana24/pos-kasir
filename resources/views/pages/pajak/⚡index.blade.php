<?php

use App\Models\Pajak;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Pengaturan Pajak')] class extends Component {
    public ?Pajak $pajak = null;

    public string $nama = '';
    public string $persentase = '';
    public bool $is_active = false;

    public function mount(): void
    {
        $this->pajak = Pajak::first();
        
        if (! $this->pajak) {
            $this->pajak = Pajak::create([
                'nama' => 'PPN', 
                'persentase' => 11.00, 
                'is_active' => false
            ]);
        }

        $this->nama = $this->pajak->nama;
        $this->persentase = (string) $this->pajak->persentase;
        $this->is_active = $this->pajak->is_active;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'persentase' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $this->pajak->update([
            'nama' => $validated['nama'],
            'persentase' => $validated['persentase'],
            'is_active' => $validated['is_active'],
        ]);

        Flux::toast(variant: 'success', text: __('Pengaturan pajak berhasil disimpan.'));
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    {{-- Header --}}
    <div>
        <flux:heading size="xl">{{ __('Pengaturan Pajak') }}</flux:heading>
        <flux:subheading>{{ __('Kelola persentase dan status pajak toko secara global') }}</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="sm">{{ __('Status Pajak') }}</flux:heading>
                    <flux:subheading>{{ __('Aktifkan jika harga produk perlu ditambah pajak saat transaksi') }}</flux:subheading>
                </div>
                <flux:switch wire:model.live="is_active" data-test="pajak-active-switch" />
            </div>

            <flux:separator />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Nama Pajak') }}</flux:label>
                    <flux:input wire:model="nama" placeholder="Contoh: PPN" required data-test="nama-pajak-input" />
                    <flux:error name="nama" />
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Nama yang akan tampil di struk/nota') }}</flux:text>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Persentase (%)') }}</flux:label>
                    <flux:input wire:model="persentase" type="number" step="0.01" min="0" max="100" placeholder="11" required data-test="persentase-pajak-input" />
                    <flux:error name="persentase" />
                    <flux:text class="mt-1 text-xs text-zinc-500">{{ __('Contoh: 11 atau 11.5') }}</flux:text>
                </flux:field>
            </div>
        </flux:card>

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" class="flex-1 sm:flex-none" data-test="save-button">
                {{ __('Simpan Perubahan') }}
            </flux:button>
        </div>
    </form>
</div>
