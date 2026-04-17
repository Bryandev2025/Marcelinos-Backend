<?php

namespace App\Filament\Forms\Components;

use App\Support\PsgcApi;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * Cascading PSGC-driven selects (PSGC API) + hidden string fields stored on the guest record.
 */
final class PhAddressFields
{
    /**
     * @return list<Field>
     */
    public static function make(): array
    {
        return [
            Select::make('ph_region_code')
                ->label('Region')
                ->placeholder('Select region')
                ->options(fn (): array => PsgcApi::regionOptions())
                ->searchable()
                ->preload()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function (Get $get, Set $set, ?string $state): void {
                    if ($state || (bool) $get('is_international')) {
                        return;
                    }

                    $regionCode = PsgcApi::regionCodeFromLabel((string) ($get('region') ?? ''));
                    if ($regionCode !== null) {
                        $set('ph_region_code', $regionCode);
                    }
                })
                ->required(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->visible(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->afterStateUpdated(function (Set $set, ?string $state): void {
                    $set('ph_province_code', null);
                    $set('ph_municipality_code', null);
                    $set('ph_barangay_code', null);
                    if (! $state) {
                        $set('region', null);
                        $set('province', null);
                        $set('municipality', null);
                        $set('barangay', null);

                        return;
                    }
                    $set('region', PsgcApi::regionOptions()[$state] ?? null);
                    $set('province', null);
                    $set('municipality', null);
                    $set('barangay', null);
                }),

            Select::make('ph_province_code')
                ->label('Province')
                ->placeholder('Select province')
                ->options(fn (Get $get): array => PsgcApi::provinceOptions((string) ($get('ph_region_code') ?? '')))
                ->searchable()
                ->preload()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function (Get $get, Set $set, ?string $state): void {
                    if ($state || (bool) $get('is_international')) {
                        return;
                    }

                    $regionCode = (string) ($get('ph_region_code') ?? '');
                    if ($regionCode === '' || PsgcApi::isNcr($regionCode)) {
                        return;
                    }

                    $provinceCode = PsgcApi::provinceCodeFromLabel($regionCode, (string) ($get('province') ?? ''));
                    if ($provinceCode !== null) {
                        $set('ph_province_code', $provinceCode);
                    }
                })
                ->visible(function (Get $get): bool {
                    if ((bool) $get('is_international')) {
                        return false;
                    }

                    $region = (string) ($get('ph_region_code') ?? '');

                    return $region !== '' && ! PsgcApi::isNcr($region);
                })
                ->required(fn (Get $get): bool => ! (bool) $get('is_international') && ! PsgcApi::isNcr((string) ($get('ph_region_code') ?? '')))
                ->disabled(fn (Get $get): bool => (string) ($get('ph_region_code') ?? '') === '')
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                    $set('ph_municipality_code', null);
                    $set('ph_barangay_code', null);
                    $regionCode = (string) ($get('ph_region_code') ?? '');
                    $options = $regionCode !== '' ? PsgcApi::provinceOptions($regionCode) : [];
                    $set('province', $state ? ($options[$state] ?? null) : null);
                    $set('municipality', null);
                    $set('barangay', null);
                }),

            Select::make('ph_municipality_code')
                ->label('Municipality / City')
                ->placeholder('Select municipality or city')
                ->options(fn (Get $get): array => PsgcApi::municipalityOptions(
                    $get('ph_region_code'),
                    $get('ph_province_code'),
                ))
                ->searchable()
                ->preload()
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function (Get $get, Set $set, ?string $state): void {
                    if ($state || (bool) $get('is_international')) {
                        return;
                    }

                    $regionCode = (string) ($get('ph_region_code') ?? '');
                    if ($regionCode === '') {
                        return;
                    }

                    $provinceCode = (string) ($get('ph_province_code') ?? '');
                    $municipalityCode = PsgcApi::municipalityCodeFromLabel(
                        $regionCode,
                        $provinceCode !== '' ? $provinceCode : null,
                        (string) ($get('municipality') ?? ''),
                    );

                    if ($municipalityCode !== null) {
                        $set('ph_municipality_code', $municipalityCode);
                    }
                })
                ->required(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->visible(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->disabled(function (Get $get): bool {
                    $region = (string) ($get('ph_region_code') ?? '');
                    if ($region === '') {
                        return true;
                    }
                    if (PsgcApi::isNcr($region)) {
                        return false;
                    }

                    return (string) ($get('ph_province_code') ?? '') === '';
                })
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                    $set('ph_barangay_code', null);
                    $options = PsgcApi::municipalityOptions(
                        $get('ph_region_code'),
                        $get('ph_province_code'),
                    );
                    $set('municipality', $state ? ($options[$state] ?? null) : null);
                    $set('barangay', null);
                }),

            Select::make('ph_barangay_code')
                ->label('Barangay')
                ->placeholder('Select barangay')
                ->options(fn (Get $get): array => PsgcApi::barangayOptions((string) ($get('ph_municipality_code') ?? '')))
                ->searchable()
                ->preload()
                ->dehydrated(false)
                ->afterStateHydrated(function (Get $get, Set $set, ?string $state): void {
                    if ($state || (bool) $get('is_international')) {
                        return;
                    }

                    $municipalityCode = (string) ($get('ph_municipality_code') ?? '');
                    if ($municipalityCode === '') {
                        return;
                    }

                    $barangayCode = PsgcApi::barangayCodeFromLabel($municipalityCode, (string) ($get('barangay') ?? ''));
                    if ($barangayCode !== null) {
                        $set('ph_barangay_code', $barangayCode);
                    }
                })
                ->required(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->visible(fn (Get $get): bool => ! (bool) $get('is_international'))
                ->disabled(fn (Get $get): bool => (string) ($get('ph_municipality_code') ?? '') === '')
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                    $municipalityCode = (string) ($get('ph_municipality_code') ?? '');
                    $options = $municipalityCode !== '' ? PsgcApi::barangayOptions($municipalityCode) : [];
                    $set('barangay', $state ? ($options[$state] ?? null) : null);
                }),

            Hidden::make('region'),
            Hidden::make('province'),
            Hidden::make('municipality'),
            Hidden::make('barangay'),
        ];
    }
}
