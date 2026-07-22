@props(['eventName'])

@pushonce('scripts')
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" type="text/javascript"></script>
    <style>
        #reader {
            width: 100% !important;
            max-width: 100% !important;
            height: 100% !important;
            max-height: 240px !important;
            border: none !important;
            overflow: hidden !important;
            position: relative !important;
        }
        #reader * {
            max-width: 100% !important;
            box-sizing: border-box !important;
        }
        #reader video {
            width: 100% !important;
            max-width: 100% !important;
            height: 100% !important;
            max-height: 240px !important;
            object-fit: cover !important;
            border-radius: 0.75rem !important;
        }
        #reader__scan_region {
            width: 100% !important;
            max-width: 100% !important;
            height: 100% !important;
            max-height: 240px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
        }
        #reader__scan_region img {
            display: none !important;
        }
        #reader__dashboard {
            display: none !important;
        }
    </style>
@endpushonce

<flux:modal name="barcode-scanner" class="max-w-md w-full overflow-hidden" x-data="{
    scanner: null,
    isLoading: false,
    error: null,
    eventName: '{{ $eventName }}',

    async loadScriptIfNeeded() {
        if (typeof Html5Qrcode !== 'undefined') {
            return true;
        }
        return new Promise((resolve) => {
            if (document.getElementById('html5-qrcode-script')) {
                let interval = setInterval(() => {
                    if (typeof Html5Qrcode !== 'undefined') {
                        clearInterval(interval);
                        resolve(true);
                    }
                }, 100);
                return;
            }
            let script = document.createElement('script');
            script.id = 'html5-qrcode-script';
            script.src = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
            script.onload = () => resolve(true);
            script.onerror = () => resolve(false);
            document.head.appendChild(script);
        });
    },

    async startScanner() {
        this.isLoading = true;
        this.error = null;

        let loaded = await this.loadScriptIfNeeded();
        if (!loaded || typeof Html5Qrcode === 'undefined') {
            this.isLoading = false;
            this.error = 'Gagal memuat pustaka pengenal barcode. Periksa koneksi internet Anda.';
            return;
        }

        await this.stopScanner();

        this.$nextTick(async () => {
            try {
                let cameras = await Html5Qrcode.getCameras().catch(() => []);
                let cameraConfig = { facingMode: 'environment' };

                if (cameras && cameras.length > 0) {
                    let backCamera = cameras.find(c =>
                        c.label && (
                            c.label.toLowerCase().includes('back') ||
                            c.label.toLowerCase().includes('rear') ||
                            c.label.toLowerCase().includes('belakang') ||
                            c.label.toLowerCase().includes('environment')
                        )
                    );
                    cameraConfig = backCamera ? backCamera.id : cameras[0].id;
                }

                this.scanner = new Html5Qrcode('reader');

                const config = {
                    fps: 15,
                    qrbox: (viewfinderWidth, viewfinderHeight) => {
                        let minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                        let boxSize = Math.max(120, Math.floor(minEdge * 0.7));
                        return { width: boxSize, height: boxSize };
                    },
                    experimentalFeatures: {
                        useBarCodeDetectorIfSupported: true
                    }
                };

                await this.scanner.start(
                    cameraConfig,
                    config,
                    (decodedText) => {
                        this.stopScanner();
                        $wire.dispatch(this.eventName, { sku: decodedText });
                        Flux.modal('barcode-scanner').close();
                    },
                    () => {}
                );

                this.isLoading = false;
            } catch (err) {
                this.isLoading = false;
                console.error('Camera access error:', err);
                let errStr = (err && err.toString ? err.toString() : '').toLowerCase();
                if (errStr.includes('notallowed') || errStr.includes('permission') || errStr.includes('denied')) {
                    this.error = 'Akses kamera ditolak. Mohon berikan izin kamera pada browser Anda.';
                } else if (errStr.includes('notfound') || errStr.includes('device')) {
                    this.error = 'Kamera tidak ditemukan pada perangkat Anda.';
                } else {
                    this.error = 'Gagal membuka kamera: ' + (err.message || 'Periksa izin kamera browser');
                }
            }
        });
    },

    async stopScanner() {
        if (this.scanner) {
            try {
                if (this.scanner.isScanning) {
                    await this.scanner.stop();
                }
                this.scanner.clear();
            } catch (e) {
                console.error('Error stopping scanner:', e);
            } finally {
                this.scanner = null;
            }
        }
    }
}"
@modal-show.window="if ($event.detail.name === 'barcode-scanner') { $nextTick(() => startScanner()) }"
@modal-close.window="if ($event.detail.name === 'barcode-scanner') { stopScanner() }">
    <div class="space-y-4 max-w-full overflow-hidden">
        <div>
            <flux:heading size="lg">{{ __('Scan Barcode / QR Code / SKU') }}</flux:heading>
        </div>

        <div class="relative w-full max-w-full overflow-hidden rounded-xl bg-zinc-950 h-60 flex items-center justify-center border border-zinc-800">
            <div id="reader" class="w-full h-full max-w-full overflow-hidden"></div>

            <div x-show="isLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-zinc-950/80 backdrop-blur-xs">
                <flux:icon name="arrow-path" class="size-8 text-emerald-500 animate-spin mb-2" />
                <span class="text-xs font-medium text-zinc-300">{{ __('Membuka kamera...') }}</span>
            </div>

            <div x-show="error" class="absolute inset-0 flex flex-col items-center justify-center bg-zinc-950/90 p-6 text-center">
                <flux:icon name="exclamation-triangle" class="size-10 text-rose-500 mb-2" />
                <p class="text-sm font-medium text-zinc-200" x-text="error"></p>
                <flux:button size="sm" variant="primary" class="mt-4" @click="startScanner()">{{ __('Coba Lagi') }}</flux:button>
            </div>
        </div>

        <p class="text-xs text-center text-zinc-500 dark:text-zinc-400">
            {{ __('Arahkan kamera ke Barcode (EAN/Code128) atau QR Code produk.') }}
        </p>

        <div class="flex gap-3">
            <flux:modal.close>
                <flux:button class="w-full" @click="stopScanner()">{{ __('Batal') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>
</flux:modal>
