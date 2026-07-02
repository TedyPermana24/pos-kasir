@props(['eventName'])

<flux:modal 
    name="barcode-scanner" 
    class="max-w-md" 
    x-data="{}" 
    x-init="if (window.barcodeScanner) { Object.assign($data, window.barcodeScanner('{{ $eventName }}')) } else { console.error('Script barcodeScanner belum termuat!') }" 
    @modal-show.window="if ($event.detail.name === 'barcode-scanner' && typeof startScanner === 'function') { startScanner() }" 
    @modal-close.window="if ($event.detail.name === 'barcode-scanner' && typeof stopScanner === 'function') { stopScanner() }"
>
    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Scan Barcode / SKU') }}</flux:heading>
        
        <div class="relative overflow-hidden rounded-xl bg-black aspect-video flex items-center justify-center">
            <div id="reader" class="w-full h-full"></div>
            
            <div x-show="$data.isLoading" class="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                <flux:icon name="arrow-path" class="size-8 text-white animate-spin" />
            </div>
            
            <div x-show="$data.error" class="absolute inset-0 flex flex-col items-center justify-center bg-black/80 p-6 text-center">
                <flux:icon name="exclamation-triangle" class="size-10 text-red-500 mb-2" />
                <p class="text-sm font-medium text-white" x-text="$data.error"></p>
                <flux:button size="sm" variant="primary" class="mt-4" @click="startScanner()">{{ __('Coba Lagi') }}</flux:button>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:modal.close>
                <flux:button class="w-full" @click="typeof stopScanner === 'function' && stopScanner()">{{ __('Batal') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>

    @pushonce('scripts')
        <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
        <script>
            window.barcodeScanner = function (eventName) {
                return {
                    scanner: null,
                    isLoading: false,
                    error: null,
                    
                    startScanner() {
                        this.isLoading = true;
                        this.error = null;
                        
                        if (this.scanner) {
                            this.scanner.clear();
                        }
                        
                        this.scanner = new Html5Qrcode("reader");
                        
                        this.scanner.start(
                            { facingMode: "environment" },
                            {
                                fps: 10,
                                qrbox: { width: 250, height: 150 },
                                aspectRatio: 1.777778
                            },
                            (decodedText, decodedResult) => {
                                this.stopScanner();
                                
                                // Memanggil Livewire dispatch
                                if (typeof $wire !== 'undefined') {
                                    $wire.dispatch(eventName, { sku: decodedText });
                                }
                                
                                Flux.modal('barcode-scanner').close();
                            },
                            (errorMessage) => {
                                // Abaikan parse error saat mencari barcode
                            }
                        ).then(() => {
                            this.isLoading = false;
                        }).catch((err) => {
                            this.isLoading = false;
                            this.error = "Gagal mengakses kamera. Pastikan Anda telah memberikan izin kamera.";
                            console.error("Camera access error:", err);
                        });
                    },
                    
                    stopScanner() {
                        if (this.scanner) {
                            this.scanner.stop().then(() => {
                                this.scanner.clear();
                                this.scanner = null;
                            }).catch(err => {
                                console.error("Error stopping scanner", err);
                            });
                        }
                    }
                }
            };
        </script>
    @endpushonce
</flux:modal>