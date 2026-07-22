<x-layouts::app :title="__('Dashboard')">
    @php
        $todayTransaksiCount = \App\Models\Transaksi::where('status', 'selesai')->whereDate('created_at', \Carbon\Carbon::today())->count();
        $todayOmzet = \App\Models\Transaksi::where('status', 'selesai')->whereDate('created_at', \Carbon\Carbon::today())->sum('grand_total');
        $totalProdukCount = \App\Models\Produk::count();
        $totalVarianCount = \App\Models\ProdukVarian::count();
    @endphp

    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
        {{-- Top 3 Summary Cards --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {{-- Card 1: Transaksi Hari Ini --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Transaksi Hari Ini') }}
                        </p>
                        <h3 class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">
                            {{ number_format($todayTransaksiCount, 0, ',', '.') }}
                        </h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Transaksi dibuat hari ini') }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-emerald-500/10 p-3.5 text-emerald-600 dark:text-emerald-400">
                        <flux:icon name="shopping-bag" class="size-7" />
                    </div>
                </div>
            </div>

            {{-- Card 2: Omzet Hari Ini --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Omzet Hari Ini') }}
                        </p>
                        <h3 class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                            Rp {{ number_format($todayOmzet, 0, ',', '.') }}
                        </h3>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Total pendapatan hari ini') }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-blue-500/10 p-3.5 text-blue-600 dark:text-blue-400">
                        <flux:icon name="banknotes" class="size-7" />
                    </div>
                </div>
            </div>

            {{-- Card 3: Total Produk --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-800 sm:col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500 dark:text-zinc-400">
                            {{ __('Total Produk') }}
                        </p>
                        <h3 class="mt-2 text-3xl font-bold text-zinc-900 dark:text-white">
                            {{ number_format($totalProdukCount, 0, ',', '.') }}
                        </h3>
                        <p class="mt-1 text-xs text-purple-600 dark:text-purple-400">
                            {{ number_format($totalVarianCount, 0, ',', '.') }} {{ __('Varian aktif') }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-purple-500/10 p-3.5 text-purple-600 dark:text-purple-400">
                        <flux:icon name="cube" class="size-7" />
                    </div>
                </div>
            </div>
        </div>

        {{-- Mobile-Friendly Menu Shortcut Card --}}
        <div class="rounded-2xl border border-zinc-200 bg-white p-5 shadow-xs dark:border-zinc-700 dark:bg-zinc-800 sm:p-6">
            <div class="mb-4 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Menu Akses Cepat') }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Pilih menu untuk bernavigasi dengan cepat') }}</p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 sm:gap-4">
                {{-- Extended Full-Row Mulai Kasir Hero Button --}}
                @if (auth()->user()->hasPermission('transaksi.create'))
                    <a
                        href="{{ route('kasir.index') }}"
                        wire:navigate
                        class="group col-span-full flex items-center justify-between rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 transition hover:border-emerald-500 hover:bg-emerald-50/60 active:scale-[0.99] dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:border-emerald-500 dark:hover:bg-emerald-950/30 sm:p-5"
                    >
                        <div class="flex items-center gap-4">
                            <div class="flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition group-hover:bg-emerald-600 group-hover:text-white dark:bg-zinc-700 dark:text-zinc-200">
                                <flux:icon name="calculator" class="size-6" />
                            </div>
                            <div class="text-left">
                                <h4 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('Mulai Kasir') }}</h4>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Buka mesin kasir & proses transaksi pelanggan baru') }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                            <span class="hidden sm:inline">{{ __('Buka Kasir') }}</span>
                            <flux:icon name="arrow-right" class="size-5 transition group-hover:translate-x-1" />
                        </div>
                    </a>
                @endif

                @if (auth()->user()->hasPermission('produk.view'))
                    <a
                        href="{{ route('produk.index') }}"
                        wire:navigate
                        class="group flex flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 text-center transition hover:border-zinc-300 hover:bg-zinc-100 active:scale-95 dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:bg-zinc-700/50"
                    >
                        <div class="mb-2 flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition group-hover:scale-110 dark:bg-zinc-700 dark:text-zinc-200">
                            <flux:icon name="cube" class="size-6" />
                        </div>
                        <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ __('Produk') }}</span>
                    </a>
                @endif

                @if (auth()->user()->hasPermission('promo.view'))
                    <a
                        href="{{ route('promo.index') }}"
                        wire:navigate
                        class="group flex flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 text-center transition hover:border-zinc-300 hover:bg-zinc-100 active:scale-95 dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:bg-zinc-700/50"
                    >
                        <div class="mb-2 flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition group-hover:scale-110 dark:bg-zinc-700 dark:text-zinc-200">
                            <flux:icon name="tag" class="size-6" />
                        </div>
                        <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ __('Promo') }}</span>
                    </a>
                @endif

                @if (auth()->user()->hasPermission('pajak.manage'))
                    <a
                        href="{{ route('pajak.index') }}"
                        wire:navigate
                        class="group flex flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 text-center transition hover:border-zinc-300 hover:bg-zinc-100 active:scale-95 dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:bg-zinc-700/50"
                    >
                        <div class="mb-2 flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition group-hover:scale-110 dark:bg-zinc-700 dark:text-zinc-200">
                            <flux:icon name="receipt-percent" class="size-6" />
                        </div>
                        <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ __('Pajak') }}</span>
                    </a>
                @endif

                @if (auth()->user()->hasPermission('pegawai.manage'))
                    <a
                        href="{{ route('jabatan.index') }}"
                        wire:navigate
                        class="group flex flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 text-center transition hover:border-zinc-300 hover:bg-zinc-100 active:scale-95 dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:bg-zinc-700/50"
                    >
                        <div class="mb-2 flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition group-hover:scale-110 dark:bg-zinc-700 dark:text-zinc-200">
                            <flux:icon name="briefcase" class="size-6" />
                        </div>
                        <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ __('Jabatan') }}</span>
                    </a>

                    <a
                        href="{{ route('pegawai.index') }}"
                        wire:navigate
                        class="group flex flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 text-center transition hover:border-zinc-300 hover:bg-zinc-100 active:scale-95 dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:bg-zinc-700/50"
                    >
                        <div class="mb-2 flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition group-hover:scale-110 dark:bg-zinc-700 dark:text-zinc-200">
                            <flux:icon name="users" class="size-6" />
                        </div>
                        <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ __('Pegawai') }}</span>
                    </a>
                @endif

                @if (auth()->user()->hasPermission('laporan.view'))
                    <a
                        href="{{ route('laporan.transaksi') }}"
                        wire:navigate
                        class="group flex flex-col items-center justify-center rounded-2xl border border-zinc-200 bg-zinc-50/50 p-4 text-center transition hover:border-zinc-300 hover:bg-zinc-100 active:scale-95 dark:border-zinc-700 dark:bg-zinc-800/60 dark:hover:bg-zinc-700/50"
                    >
                        <div class="mb-2 flex size-12 items-center justify-center rounded-xl bg-zinc-100 text-zinc-700 transition group-hover:scale-110 dark:bg-zinc-700 dark:text-zinc-200">
                            <flux:icon name="document-text" class="size-6" />
                        </div>
                        <span class="text-xs font-medium text-zinc-900 dark:text-white">{{ __('Laporan') }}</span>
                    </a>
                @endif
            </div>
        </div>

        {{-- Card Besar Notifikasi Stok --}}
        @if (auth()->user()->hasPermission('stok.notifikasi'))
            @php
                $lowStockVarians = \App\Models\ProdukVarian::with(['produk', 'satuan'])
                    ->whereNotNull('stok')
                    ->whereRaw('stok <= COALESCE(minimum_stok, 0)')
                    ->orderBy('stok', 'asc')
                    ->get();
            @endphp

            @if ($lowStockVarians->isNotEmpty())
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <div class="rounded-xl bg-zinc-100 p-3 text-zinc-900 dark:bg-zinc-700 dark:text-white">
                                <flux:icon name="bell" class="size-6" />
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Peringatan Stok Produk') }}</h3>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Terdapat :count varian produk dengan stok menyentuh atau di bawah batas minimum.', ['count' => $lowStockVarians->count()]) }}
                                </p>
                            </div>
                        </div>
                        <flux:button size="sm" variant="subtle" icon="arrow-right" :href="route('produk.index')" wire:navigate>
                            {{ __('Kelola Produk') }}
                        </flux:button>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-zinc-50 text-xs font-semibold uppercase tracking-wider text-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-300">
                                <tr>
                                    <th class="px-4 py-3">{{ __('Nama Produk') }}</th>
                                    <th class="px-4 py-3">{{ __('Varian') }}</th>
                                    <th class="px-4 py-3">{{ __('Stok Saat Ini') }}</th>
                                    <th class="px-4 py-3">{{ __('Stok Minimal') }}</th>
                                    <th class="px-4 py-3 text-right">{{ __('Keterangan Status') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700">
                                @foreach ($lowStockVarians as $varian)
                                    <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-700/30">
                                        <td class="px-4 py-3 font-medium text-zinc-900 dark:text-white">{{ $varian->produk->nama_produk }}</td>
                                        <td class="px-4 py-3 text-zinc-600 dark:text-zinc-400">{{ $varian->nama_varian }}</td>
                                        <td class="px-4 py-3 font-bold text-zinc-900 dark:text-white">{{ $varian->stok }} {{ $varian->satuan?->nama }}</td>
                                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">{{ $varian->minimum_stok ?? 0 }} {{ $varian->satuan?->nama }}</td>
                                        <td class="px-4 py-3 text-right text-xs font-semibold text-rose-600 dark:text-rose-400">
                                            @if ($varian->stok <= 0)
                                                <span>{{ __('Stok telah habis, segera lakukan pengisian ulang.') }}</span>
                                            @else
                                                <span>{{ __('Stok menipis, mendekati atau telah menyentuh batas minimum.') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @else
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-6 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                    <div class="flex items-center gap-3">
                        <div class="rounded-xl bg-emerald-500/10 p-3 text-emerald-600 dark:text-emerald-400">
                            <flux:icon name="check-circle" class="size-6" />
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-emerald-900 dark:text-emerald-200">{{ __('Semua Stok Aman') }}</h3>
                            <p class="text-xs text-emerald-600 dark:text-emerald-400">
                                {{ __('Seluruh stok produk berada dalam batas aman dan mencukupi.') }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-layouts::app>
