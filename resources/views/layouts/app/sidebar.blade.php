<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Manajemen')" class="grid">
                    @if (auth()->user()->hasPermission('produk.view'))
                        <flux:sidebar.item icon="cube" :href="route('produk.index')" :current="request()->routeIs('produk.*')" wire:navigate>
                            {{ __('Produk') }}
                        </flux:sidebar.item>
                    @endif
                    
                    @if (auth()->user()->hasPermission('promo.view'))
                        <flux:sidebar.item icon="tag" :href="route('promo.index')" :current="request()->routeIs('promo.*')" wire:navigate>
                            {{ __('Promo') }}
                        </flux:sidebar.item>
                    @endif
                    
                    @if (auth()->user()->hasPermission('pajak.manage'))
                        <flux:sidebar.item icon="receipt-percent" :href="route('pajak.index')" :current="request()->routeIs('pajak.*')" wire:navigate>
                            {{ __('Pajak') }}
                        </flux:sidebar.item>
                    @endif

                    @if (auth()->user()->hasPermission('pegawai.manage'))
                        <flux:sidebar.item icon="briefcase" :href="route('jabatan.index')" :current="request()->routeIs('jabatan.*')" wire:navigate>
                            {{ __('Jabatan') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('pegawai.index')" :current="request()->routeIs('pegawai.*')" wire:navigate>
                            {{ __('Pegawai') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>

                
            </flux:sidebar.nav>

            <flux:spacer />

            @if (auth()->user()->hasPermission('transaksi.create'))
                <div class="px-2 pb-4">
                    <flux:button variant="primary" :href="route('kasir.index')" wire:navigate class="w-full">
                        {{ __('Mulai Kasir') }}
                    </flux:button>
                </div>
            @endif

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('appearance.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
        @stack('scripts')
    </body>
</html>
