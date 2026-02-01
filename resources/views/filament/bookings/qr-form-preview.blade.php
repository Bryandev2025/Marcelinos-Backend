@php
    $value = is_callable($reference ?? null) ? $reference() : ($reference ?? null);
@endphp

<div class="w-full flex justify-center">
    @if ($value)
        @php
            $qr = base64_encode(QrCode::format('png')->size(200)->generate($value));
        @endphp
        <img src="data:image/png;base64,{{ $qr }}" alt="QR Code" class="rounded-md border border-gray-200" />
    @else
        <div class="text-sm text-gray-500">QR code will appear after reference number is generated.</div>
    @endif
</div>
