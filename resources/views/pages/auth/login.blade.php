<x-layouts::auth :title="__('Login Kasir')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Selamat Datang')" :description="__('Masukkan PIN 6 angka Anda untuk login')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-circle">
                <flux:callout.heading>{{ __('PIN Salah') }}</flux:callout.heading>
                <flux:callout.text>{{ __('PIN yang Anda masukkan tidak valid. Silakan coba lagi.') }}</flux:callout.text>
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6" id="pin-login-form"
            x-data="{
                pin: ['', '', '', '', '', ''],
                focusNext(index) {
                    if (index < 5) this.$refs['input' + (index + 1)].focus();
                },
                focusPrev(index) {
                    if (index > 0) this.$refs['input' + (index - 1)].focus();
                },
                handleInput(e, index) {
                    let val = e.target.value.replace(/\D/g, '');
                    this.pin[index] = val.substring(0, 1);
                    if (val.length > 0) this.focusNext(index);
                    
                    // Check if submit button should be enabled
                    this.checkSubmit();
                },
                handleKeydown(e, index) {
                    if (e.key === 'Backspace' && !this.pin[index]) {
                        this.focusPrev(index);
                    }
                },
                handlePaste(e) {
                    let paste = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                    for (let i = 0; i < Math.min(paste.length, 6); i++) {
                        this.pin[i] = paste[i];
                    }
                    let nextFocus = Math.min(paste.length, 5);
                    this.$refs['input' + nextFocus].focus();
                    this.checkSubmit();
                },
                checkSubmit() {
                    let fullPin = this.pin.join('');
                    if (fullPin.length === 6) {
                        this.$refs.submitBtn.removeAttribute('disabled');
                        setTimeout(() => this.$refs.submitBtn.click(), 300);
                    } else {
                        this.$refs.submitBtn.setAttribute('disabled', 'disabled');
                    }
                }
            }">
            @csrf
            
            <flux:field>
                <flux:label>{{ __('Nama Pegawai') }}</flux:label>
                <flux:input name="name" :value="old('name')" required autofocus placeholder="Masukkan nama..." data-test="name-input" />
                <flux:error name="name" />
            </flux:field>

            {{-- PIN Input (OTP Style) --}}
            <flux:field>
                <flux:label>{{ __('PIN (6 Angka)') }}</flux:label>
                <div class="mt-2 flex justify-between gap-2">
                    @for ($i = 0; $i < 6; $i++)
                        <input 
                            type="password" 
                            maxlength="1" 
                            x-model="pin[{{ $i }}]"
                            x-ref="input{{ $i }}"
                            @input="handleInput($event, {{ $i }})"
                            @keydown="handleKeydown($event, {{ $i }})"
                            @paste.prevent="handlePaste($event)"
                            class="flex h-14 w-full items-center justify-center rounded-xl border-2 border-zinc-200 bg-white text-center text-2xl font-bold text-zinc-900 transition-all focus:border-blue-500 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-800 dark:text-white dark:focus:border-blue-400"
                        />
                    @endfor
                    
                    <input type="hidden" name="password" :value="pin.join('')" />
                </div>
                <flux:error name="password" />
            </flux:field>

            {{-- Submit --}}
            <flux:button variant="primary" type="submit" class="w-full" x-ref="submitBtn" disabled>
                {{ __('Masuk') }}
            </flux:button>
</x-layouts::auth>
