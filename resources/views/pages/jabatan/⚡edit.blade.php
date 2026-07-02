<?php

use App\Models\Jabatan;
use App\Models\Permission;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Jabatan')] class extends Component {
    public Jabatan $jabatan;
    public string $nama = '';

    /** @var array<int, int> Selected permission IDs */
    public array $selectedPermissions = [];

    public function mount(Jabatan $jabatan): void
    {
        $this->jabatan = $jabatan;
        $this->nama = $jabatan->nama;
        $this->selectedPermissions = $jabatan->permissions->pluck('id')->toArray();
    }

    public function save(): void
    {
        $this->validate([
            'nama' => ['required', 'string', 'max:255', "unique:jabatans,nama,{$this->jabatan->id}"],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['exists:permissions,id'],
        ]);

        $this->jabatan->update(['nama' => $this->nama]);
        $this->jabatan->permissions()->sync($this->selectedPermissions);

        Flux::toast(variant: 'success', text: __('Jabatan berhasil diperbarui.'));
        $this->redirectRoute('jabatan.index', navigate: true);
    }

    #[Computed]
    public function permissions()
    {
        return Permission::orderBy('nama')->get()->groupBy(fn ($p) => explode('.', $p->nama)[0]);
    }
}; ?>

<div class="mx-auto max-w-2xl space-y-6">
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('jabatan.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Edit Jabatan') }}</flux:heading>
            <flux:subheading>{{ $jabatan->nama }}</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4 p-6">
            <flux:field>
                <flux:label>{{ __('Nama Jabatan') }}</flux:label>
                <flux:input wire:model="nama" placeholder="Contoh: Kasir, Manajer" required data-test="nama-jabatan-input" />
                <flux:error name="nama" />
            </flux:field>
        </flux:card>

        <flux:card class="p-6 space-y-4">
            <div>
                <flux:heading size="sm">{{ __('Hak Akses') }}</flux:heading>
                <flux:subheading>{{ __('Centang permission yang boleh dilakukan oleh jabatan ini') }}</flux:subheading>
            </div>

            @foreach ($this->permissions as $group => $groupPermissions)
                <div class="space-y-2">
                    <flux:text class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                        {{ strtoupper($group) }}
                    </flux:text>
                    <div class="space-y-1 rounded-lg border border-zinc-100 p-3 dark:border-zinc-800">
                        @foreach ($groupPermissions as $permission)
                            <div class="flex items-center gap-3 py-1">
                                <flux:checkbox
                                    wire:model="selectedPermissions"
                                    value="{{ $permission->id }}"
                                    id="perm-{{ $permission->id }}"
                                    data-test="permission-{{ $permission->nama }}"
                                />
                                <label for="perm-{{ $permission->id }}" class="flex-1 cursor-pointer">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $permission->nama }}</span>
                                    @if ($permission->keterangan)
                                        <span class="ml-2 text-xs text-zinc-400">— {{ $permission->keterangan }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </flux:card>

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" class="flex-1 sm:flex-none" data-test="save-jabatan-button">
                {{ __('Simpan Perubahan') }}
            </flux:button>
            <flux:button :href="route('jabatan.index')" wire:navigate>{{ __('Batal') }}</flux:button>
        </div>
    </form>
</div>
