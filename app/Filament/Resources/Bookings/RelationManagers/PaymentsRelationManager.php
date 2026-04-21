<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Filament\Actions\TypedDeleteAction;
use App\Filament\Actions\TypedDeleteBulkAction;
use App\Filament\Actions\TypedForceDeleteAction;
use App\Filament\Actions\TypedForceDeleteBulkAction;
use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\Payment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('total_amount')
                    ->label('Booking Total Amount')
                    ->default(fn (RelationManager $livewire): int|float => $livewire->getOwnerRecord()->total_price)
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->numeric()
                    ->prefix('₱'),
                TextInput::make('partial_amount')
                    ->label('Amount Paid (Cash)')
                    ->default(fn (RelationManager $livewire): int|float => max(0, $livewire->getOwnerRecord()->balance))
                    ->required()
                    ->numeric()
                    ->prefix('₱')
                    ->maxValue(fn (RelationManager $livewire): int|float => $livewire->getOwnerRecord()->balance),
                Toggle::make('is_fullypaid')
                    ->label('Mark as Fully Paid')
                    ->default(false)
                    ->hidden(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('total_amount')
            ->columns([
                TextColumn::make('total_amount')
                    ->label('Booking Total')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('partial_amount')
                    ->label('Amount Paid')
                    ->money('PHP')
                    ->sortable(),
                IconColumn::make('is_fullypaid')
                    ->boolean()
                    ->label('Fully Paid?'),
                TextColumn::make('created_at')
                    ->label('Date Paid')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => ! $this->getOwnerRecord()->trashed())
                    ->label('Record cash payment')
                    ->modalHeading('Record cash payment')
                    ->modalSubmitActionLabel('Submit')
                    ->modalDescription('Enter a partial or full cash amount. To settle only the remaining balance in one step and mark Paid, use Settle remaining balance on the booking instead.')
                    ->mutateFormDataUsing(function (array $data, RelationManager $livewire): array {
                        $booking = $livewire->getOwnerRecord();
                        $totalPaidSoFar = collect($livewire->getRelationship()->get())->sum('partial_amount');
                        $newTotalPaid = $totalPaidSoFar + $data['partial_amount'];

                        $data['is_fullypaid'] = $newTotalPaid >= $booking->total_price;

                        return $data;
                    })
                    ->after(function (array $data, RelationManager $livewire) {
                        $booking = $livewire->getOwnerRecord();
                        if (! $booking instanceof Booking) {
                            return;
                        }

                        $booking->refresh();

                        if (! in_array((string) $booking->stay_status, [Booking::STAY_STATUS_CANCELLED, Booking::STAY_STATUS_COMPLETED], true)) {
                            if ($booking->total_paid >= $booking->total_price) {
                                $booking->update(['payment_status' => Booking::PAYMENT_STATUS_PAID]);
                            } elseif ($booking->total_paid > 0 && $booking->total_paid < $booking->total_price) {
                                $booking->update(['payment_status' => Booking::PAYMENT_STATUS_PARTIAL]);
                            }
                        }

                        $booking->refresh();
                        $livewire->redirect(BookingResource::calendarUrlForBooking($booking));
                    }),
            ])
            ->recordActions([
                RestoreAction::make(),
                TypedForceDeleteAction::make(function (Payment $record): string {
                    $record->loadMissing('booking');

                    return $record->booking?->reference_number ?? 'Payment #'.$record->getKey();
                }),
                TypedDeleteAction::make(function (Payment $record): string {
                    $record->loadMissing('booking');

                    return $record->booking?->reference_number ?? 'Payment #'.$record->getKey();
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TypedDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    TypedForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
