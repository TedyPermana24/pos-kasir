<?php

use App\Models\Jabatan;
use App\Models\User;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Edit Pegawai')] class extends Component {
    public User $user;
    public string $name = '';
    public string $email = '';
    public ?int $jabatan_id = null;
    public string $pin = '';
    public string $pin_confirmation = '';

    public function mount(User $user): void
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email ?? '';
        $this->jabatan_id = $user->jabatan_id;
    }

    public function save(): void
    {
        $pinRules = $this->pin
            ? [
                'required', 'string', 'digits:6', 'confirmed',
                function ($attribute, $value, $fail) {
                    if (User::where('id', '!=', $this->user->id)->get()->contains(fn($u) => \Illuminate\Support\Facades\Hash::check($value, $u->pin ?? ''))) {
                        $fail(__('PIN ini sudah digunakan oleh pegawai lain.'));
                    }
                }
              ]
            : ['nullable'];

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', "unique:users,email,{$this->user->id}"],
            'jabatan_id' => ['nullable', 'exists:jabatans,id'],
            'pin' => $pinRules,
        ], [
            'pin.digits' => __('PIN harus berupa 6 angka.'),
            'pin.confirmed' => __('Konfirmasi PIN tidak cocok.'),
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'] ?: null,
            'jabatan_id' => $validated['jabatan_id'],
        ];

        if ($this->pin) {
            $data['pin'] = \Illuminate\Support\Facades\Hash::make($validated['pin']);
        }

        $this->user->update($data);

        Flux::toast(variant: 'success', text: __('Data pegawai berhasil diperbarui.'));
        $this->redirectRoute('pegawai.index', navigate: true);
    }

    #[Computed]
    public function jabatans()
    {
        return Jabatan::orderBy('nama')->get();
    }
}; ?>

<div class="mx-auto max-w-lg space-y-6">
    <div class="flex items-center gap-3">
        <flux:button variant="ghost" icon="arrow-left" :href="route('pegawai.index')" wire:navigate />
        <div>
            <flux:heading size="xl">{{ __('Edit Pegawai') }}</flux:heading>
            <flux:subheading>{{ $user->name }}</flux:subheading>
        </div>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card class="space-y-4 p-6">
            <flux:heading size="sm">{{ __('Data Pegawai') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Nama Lengkap') }}</flux:label>
                <flux:input wire:model="name" placeholder="Nama pegawai" required data-test="nama-pegawai-input" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Email') }} <span class="text-zinc-400 text-xs">(opsional)</span></flux:label>
                <flux:input wire:model="email" type="email" placeholder="pegawai@toko.com" data-test="email-pegawai-input" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Jabatan') }}</flux:label>
                <flux:select wire:model="jabatan_id" placeholder="{{ __('Pilih jabatan...') }}" data-test="jabatan-select">
                    @foreach ($this->jabatans as $jabatan)
                        <flux:select.option value="{{ $jabatan->id }}">{{ $jabatan->nama }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="jabatan_id" />
            </flux:field>
        </flux:card>

        <flux:card class="space-y-4 p-6">
            <div>
                <flux:heading size="sm">{{ __('Ubah PIN Login') }}</flux:heading>
                <flux:subheading>{{ __('Kosongkan jika tidak ingin mengubah PIN') }}</flux:subheading>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('PIN Baru') }}</flux:label>
                    <flux:input
                        wire:model="pin"
                        type="password"
                        maxlength="6"
                        inputmode="numeric"
                        placeholder="••••••"
                        data-test="pin-input"
                    />
                    <flux:error name="pin" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Konfirmasi PIN Baru') }}</flux:label>
                    <flux:input
                        wire:model="pin_confirmation"
                        type="password"
                        maxlength="6"
                        inputmode="numeric"
                        placeholder="••••••"
                        data-test="pin-confirmation-input"
                    />
                </flux:field>
            </div>
        </flux:card>

        <div class="flex items-center gap-3">
            <flux:button variant="primary" type="submit" class="flex-1 sm:flex-none" data-test="save-pegawai-button">
                {{ __('Simpan Perubahan') }}
            </flux:button>
            <flux:button :href="route('pegawai.index')" wire:navigate>{{ __('Batal') }}</flux:button>
        </div>
    </form>
</div>
