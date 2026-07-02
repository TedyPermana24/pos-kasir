<?php

use App\Models\Jabatan;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Manajemen Jabatan')] class extends Component {
    public ?int $deletingJabatanId = null;

    public function confirmDelete(int $id): void
    {
        $this->deletingJabatanId = $id;
        Flux::modal('confirm-delete-jabatan')->show();
    }

    public function deleteJabatan(): void
    {
        $jabatan = Jabatan::findOrFail($this->deletingJabatanId);

        if ($jabatan->users()->exists()) {
            Flux::toast(variant: 'warning', text: __('Jabatan tidak bisa dihapus karena masih digunakan oleh pegawai.'));
            Flux::modal('confirm-delete-jabatan')->close();

            return;
        }

        $jabatan->permissions()->detach();
        $jabatan->delete();

        $this->deletingJabatanId = null;
        Flux::modal('confirm-delete-jabatan')->close();
        Flux::toast(variant: 'success', text: __('Jabatan berhasil dihapus.'));
    }

    #[Computed]
    public function jabatans()
    {
        return Jabatan::withCount(['users', 'permissions'])
            ->orderBy('nama')
            ->get();
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manajemen Jabatan') }}</flux:heading>
            <flux:subheading>{{ __('Kelola jabatan dan hak akses yang dimiliki setiap jabatan') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('jabatan.create')" wire:navigate data-test="add-jabatan-button">
            {{ __('Tambah Jabatan') }}
        </flux:button>
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Nama Jabatan') }}</flux:table.column>
            <flux:table.column>{{ __('Jumlah Pegawai') }}</flux:table.column>
            <flux:table.column>{{ __('Hak Akses') }}</flux:table.column>
            <flux:table.column class="w-24"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->jabatans as $jabatan)
                <flux:table.row wire:key="jabatan-{{ $jabatan->id }}">
                    <flux:table.cell>
                        <div class="font-medium text-zinc-900 dark:text-white">{{ $jabatan->nama }}</div>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="blue">{{ $jabatan->users_count }} {{ __('pegawai') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">{{ $jabatan->permissions_count }} {{ __('permission') }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item icon="pencil-square" :href="route('jabatan.edit', $jabatan)" wire:navigate>
                                    {{ __('Edit & Atur Hak Akses') }}
                                </flux:menu.item>
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $jabatan->id }})">
                                    {{ __('Hapus') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center">
                        <div class="py-8">
                            <flux:icon name="briefcase" class="mx-auto mb-2 size-8 text-zinc-400" />
                            <flux:text>{{ __('Belum ada jabatan.') }}</flux:text>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <flux:modal name="confirm-delete-jabatan" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Jabatan?') }}</flux:heading>
            <flux:text>{{ __('Jabatan ini dan semua hak aksesnya akan dihapus secara permanen.') }}</flux:text>
            <div class="flex gap-3">
                <flux:modal.close>
                    <flux:button class="w-full">{{ __('Batal') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" class="w-full" wire:click="deleteJabatan" data-test="confirm-delete-jabatan-button">
                    {{ __('Hapus') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
