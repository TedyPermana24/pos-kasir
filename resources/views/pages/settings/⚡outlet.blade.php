<?php

use Livewire\Component;
use App\Models\Outlet;
use Flux\Flux;

new class extends Component {
    public string $nama = '';
    public string $alamat = '';
    public string $telepon = '';

    public function mount()
    {
        $outlet = Outlet::first();
        if ($outlet) {
            $this->nama = $outlet->nama;
            $this->alamat = $outlet->alamat ?? '';
            $this->telepon = $outlet->telepon ?? '';
        }
    }

    public function save()
    {
        $this->validate([
            'nama' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
        ]);

        $outlet = Outlet::first();
        
        if ($outlet) {
            $outlet->update([
                'nama' => $this->nama,
                'alamat' => $this->alamat,
                'telepon' => $this->telepon,
            ]);
        } else {
            Outlet::create([
                'nama' => $this->nama,
                'alamat' => $this->alamat,
                'telepon' => $this->telepon,
            ]);
        }

        Flux::toast('Informasi outlet berhasil diperbarui.', variant: 'success');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Outlet Settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Outlet Settings')" :subheading="__('Kelola informasi profil outlet Anda.')">
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Nama Outlet') }}</flux:label>
                <flux:input wire:model="nama" required />
                <flux:error name="nama" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Alamat') }}</flux:label>
                <flux:textarea wire:model="alamat" rows="3" />
                <flux:error name="alamat" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('No. Telepon') }}</flux:label>
                <flux:input wire:model="telepon" />
                <flux:error name="telepon" />
            </flux:field>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" class="w-full sm:w-auto">{{ __('Simpan') }}</flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
