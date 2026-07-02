@props(['eventName'])

<flux:modal name="barcode-scanner" class="max-w-md" x-data="barcodeScanner('{{ $eventName }}')" @modal-show.window="if ($event.detail.name === 'barcode-scanner') { startScanner() }" @modal-close.window="if ($event.detail.name === 'barcode-scanner') { stopScanner() }">
    <div class="space-y-4">
        <flux:heading size="lg">{{ __('Scan Barcode / SKU') }}</flux:heading>
        
        <div class="relative overflow-hidden rounded-xl bg-black aspect-video flex items-center justify-center">
            <div id="reader" class="w-full h-full"></div>
            
            <div x-show="isLoading" class="absolute inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm">
                <flux:icon name="arrow-path" class="size-8 text-white animate-spin" />
            </div>
            
            <div x-show="error" class="absolute inset-0 flex flex-col items-center justify-center bg-black/80 p-6 text-center">
                <flux:icon name="exclamation-triangle" class="size-10 text-red-500 mb-2" />
                <p class="text-sm font-medium text-white" x-text="error"></p>
                <flux:button size="sm" variant="primary" class="mt-4" @click="startScanner()">{{ __('Coba Lagi') }}</flux:button>
            </div>
        </div>

        <div class="flex gap-3">
            <flux:modal.close>
                <flux:button class="w-full" @click="stopScanner()">{{ __('Batal') }}</flux:button>
            </flux:modal.close>
        </div>
    </div>

    @pushonce('scripts')
        <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('barcodeScanner', (eventName) => ({
                    scanner: null,
                    isLoading: false,
                    error: null,
                    
                    startScanner() {
                        this.isLoading = true;
                        this.error = null;
                        
                        // Prevent multiple instances
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
                                // On success
                                this.stopScanner();
                                
                                // Dispatch event to Livewire with the scanned SKU
                                $wire.dispatch(eventName, { sku: decodedText });
                                
                                // Close modal
                                Flux.modal('barcode-scanner').close();
                            },
                            (errorMessage) => {
                                // Ignore parse errors (happens when no barcode is in frame)
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
                }));
            });
        </script>
    @endpushonce
</flux:modal>
