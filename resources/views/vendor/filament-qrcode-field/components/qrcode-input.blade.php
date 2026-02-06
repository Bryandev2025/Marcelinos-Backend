@php
    use function Filament\Support\prepare_inherited_attributes;

    $fieldWrapperView = $getFieldWrapperView();
    $extraAlpineAttributes = $getExtraAlpineAttributes();
    $extraAttributeBag = $getExtraAttributeBag();
    $hasInlineLabel = $hasInlineLabel();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    :has-inline-label="$hasInlineLabel"
    class="fi-fo-text-input-wrp"
>
    <div
        xmlns:x-filament="http://www.w3.org/1999/html"
        x-load-js="['{{ config('filament-qrcode-field.asset_js') }}']"
        x-on:close-modal.window="stopScanning()"
        x-on:open-modal.window="startCameraWhenReady()"
        x-data="{
            html5QrcodeScanner: null,
            isStarting: false,
            stopScanning() {
                if (!this.html5QrcodeScanner) {
                    return;
                }
                this.html5QrcodeScanner.pause();
                this.html5QrcodeScanner.clear();
                this.html5QrcodeScanner = null;
            },
            onScanSuccess(decodedText, decodedResult) {
                $wire.set('{{ $getStatePath() }}', decodedText);
            },
            startCameraWhenReady() {
                if (this.html5QrcodeScanner || this.isStarting || {{ $isDisabled ? 'true' : 'false' }}) {
                    return;
                }
                this.isStarting = true;
                const tryStart = () => {
                    if (typeof Html5QrcodeScanner === 'undefined') {
                        setTimeout(tryStart, 150);
                        return;
                    }
                    this.html5QrcodeScanner = new Html5QrcodeScanner(
                        'reader-{{ $getName() }}',
                        {
                            fps: {{ config('filament-qrcode-field.scanner.fps') }},
                            qrbox: {
                                width: {{ config('filament-qrcode-field.scanner.width') }},
                                height: {{ config('filament-qrcode-field.scanner.height') }}
                            }
                        },
                        false
                    );
                    this.html5QrcodeScanner.render(this.onScanSuccess.bind(this));
                    this.isStarting = false;
                };
                tryStart();
            }
        }"
        x-init="startCameraWhenReady()"
    >
        <div class="qrcode-scanner-modal-container" {{ prepare_inherited_attributes($extraAttributeBag)->class([]) }}>
            <div id="scanner-container">
                <div
                    id="reader-{{ $getName() }}"
                    width="{{ config('filament-qrcode-field.reader.width') }}"
                    height="{{ config('filament-qrcode-field.reader.height') }}"
                ></div>
            </div>
        </div>
    </div>
</x-dynamic-component>
