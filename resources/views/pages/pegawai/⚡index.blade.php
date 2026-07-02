<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Manajemen Pegawai')] class extends Component {
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    public ?int $deletingUserId = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingUserId = $id;
        Flux::modal('confirm-delete-pegawai')->show();
    }

    public function deletePegawai(): void
    {
        $user = User::findOrFail($this->deletingUserId);

        if ($user->id === auth()->id()) {
            Flux::toast(variant: 'warning', text: __('Anda tidak bisa menghapus akun Anda sendiri.'));
            Flux::modal('confirm-delete-pegawai')->close();

            return;
        }

        $user->delete();

        $this->deletingUserId = null;
        Flux::modal('confirm-delete-pegawai')->close();
        Flux::toast(variant: 'success', text: __('Pegawai berhasil dihapus.'));
    }

    #[Computed]
    public function pegawais()
    {
        return User::query()
            ->with('jabatan')
            ->when($this->search, fn (Builder $q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(15);
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Manajemen Pegawai') }}</flux:heading>
            <flux:subheading>{{ __('Kelola data pegawai dan PIN login mereka') }}</flux:subheading>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('pegawai.create')" wire:navigate data-test="add-pegawai-button">
            {{ __('Tambah Pegawai') }}
        </flux:button>
    </div>

    {{-- Search --}}
    <div class="max-w-sm">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Cari pegawai...')" clearable data-test="search-input" />
    </div>

    {{-- Desktop Table --}}
    <div class="hidden md:block">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nama') }}</flux:table.column>
                <flux:table.column>{{ __('Jabatan') }}</flux:table.column>
                <flux:table.column>{{ __('PIN') }}</flux:table.column>
                <flux:table.column class="w-24"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->pegawais as $pegawai)
                    <flux:table.row wire:key="pegawai-{{ $pegawai->id }}">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ $pegawai->initials() }}
                                </div>
                                <div>
                                    <div class="font-medium text-zinc-900 dark:text-white">{{ $pegawai->name }}</div>
                                    @if ($pegawai->email)
                                        <div class="text-xs text-zinc-400">{{ $pegawai->email }}</div>
                                    @endif
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($pegawai->jabatan)
                                <flux:badge size="sm" color="blue">{{ $pegawai->jabatan->nama }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">{{ __('Super Admin') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" color="{{ $pegawai->pin ? 'lime' : 'red' }}">
                                {{ $pegawai->pin ? __('Sudah Diset') : __('Belum Ada PIN') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" :href="route('pegawai.edit', $pegawai)" wire:navigate>
                                        {{ __('Edit') }}
                                    </flux:menu.item>
                                    @if ($pegawai->id !== auth()->id())
                                        <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $pegawai->id }})">
                                            {{ __('Hapus') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center">
                            <div class="py-8">
                                <flux:icon name="users" class="mx-auto mb-2 size-8 text-zinc-400" />
                                <flux:text>{{ __('Belum ada pegawai.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Mobile Cards --}}
    <div class="space-y-3 md:hidden">
        @forelse ($this->pegawais as $pegawai)
            <flux:card wire:key="pegawai-mobile-{{ $pegawai->id }}" class="p-4">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-sm font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                            {{ $pegawai->initials() }}
                        </div>
                        <div class="min-w-0">
                            <div class="font-medium text-zinc-900 dark:text-white truncate">{{ $pegawai->name }}</div>
                            @if ($pegawai->jabatan)
                                <flux:badge size="sm" color="blue">{{ $pegawai->jabatan->nama }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">{{ __('Super Admin') }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                        <flux:menu>
                            <flux:menu.item icon="pencil-square" :href="route('pegawai.edit', $pegawai)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:menu.item>
                            @if ($pegawai->id !== auth()->id())
                                <flux:menu.item icon="trash" variant="danger" wire:click="confirmDelete({{ $pegawai->id }})">
                                    {{ __('Hapus') }}
                                </flux:menu.item>
                            @endif
                        </flux:menu>
                    </flux:dropdown>
                </div>
            </flux:card>
        @empty
            <div class="py-12 text-center">
                <flux:icon name="users" class="mx-auto mb-2 size-8 text-zinc-400" />
                <flux:text>{{ __('Belum ada pegawai.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $this->pegawais->links() }}</div>

    <flux:modal name="confirm-delete-pegawai" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Hapus Pegawai?') }}</flux:heading>
            <flux:text>{{ __('Data pegawai ini akan dihapus secara permanen.') }}</flux:text>
            <div class="flex gap-3">
                <flux:modal.close>
                    <flux:button class="w-full">{{ __('Batal') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" class="w-full" wire:click="deletePegawai" data-test="confirm-delete-pegawai-button">
                    {{ __('Hapus') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
