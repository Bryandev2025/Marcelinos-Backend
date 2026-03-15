<style>
    .gallery-table-flat .fi-ta-ctn {
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
    }
</style>

<div class="grid grid-cols-2 gap-2 md:grid-cols-4">
    @foreach ($records as $record)
        @include('filament.tables.columns.gallery-card', ['record' => $record])
    @endforeach
</div>
