<?php

namespace App\Http\Controllers\API;

use App\Events\BookingCancelled;
use App\Events\BookingRescheduled;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\Room;
use App\Models\Venue;
use App\Services\BookingActionOtpService;
use App\Support\BookingPricing;
use App\Support\RoomInventoryGroupAvailability;
use App\Support\RoomInventoryGroupKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BookingController extends Controller
{
    public function __construct(
        private BookingActionOtpService $bookingActionOtpService,
    ) {}

    /**
     * Send email OTP for cancel or reschedule.
     */
    public function sendBookingOtp(Request $request, Booking $booking)
    {
        if ($this->expireIfNeeded($booking)) {
            return response()->json([
                'message' => 'Booking expired after 3 days without payment and was cancelled.',
            ], 422);
        }

        $request->validate([
            'purpose' => 'required|in:cancel,reschedule',
        ]);

        $purpose = (string) $request->input('purpose');

        if ($purpose === BookingActionOtpService::PURPOSE_CANCEL) {
            $allowedStatuses = [
                Booking::STATUS_UNPAID,
                Booking::STATUS_PAID,
            ];

            if (defined(Booking::class.'::STATUS_RESCHEDULED')) {
                $allowedStatuses[] = Booking::STATUS_RESCHEDULED;
            }

            if (! in_array($booking->status, $allowedStatuses, true)) {
                return response()->json([
                    'message' => 'Booking cannot be cancelled in its current state.',
                ], 422);
            }
        } else {
            if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)) {
                return response()->json([
                    'message' => 'Cannot reschedule this booking.',
                ], 422);
            }
        }

        $this->bookingActionOtpService->send($booking, $purpose);

        return response()->json([
            'message' => 'Verification code sent.',
        ]);
    }

    /**
     * Display all bookings (paginated).
     */
    public function index(Request $request)
    {
        try {
            $perPage = min((int) $request->query('per_page', 15), 50);

            $bookings = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])
                ->orderByDesc('created_at')
                ->paginate($perPage);

            return response()->json($bookings, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving bookings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a booking by opaque receipt token (public receipt URL — non-guessable).
     */
    public function showByReceiptToken(string $token)
    {
        try {
            $booking = $this->findReceiptBooking($token);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            return $this->jsonReceiptForBooking($booking);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a booking by reference number (legacy links and testimonial flow).
     */
    public function showByReferenceNumber(string $reference)
    {
        try {
            $booking = $this->findReceiptBooking($reference);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            return $this->jsonReceiptForBooking($booking);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm a successful online payment from receipt return flow.
     * Uses opaque receipt token only and updates booking status to paid/partial.
     */
    public function confirmReceiptPayment(Request $request, string $token): JsonResponse
    {
        if (! Str::isUuid($token)) {
            return response()->json([
                'message' => 'Invalid receipt token.',
            ], 422);
        }

        $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])
            ->where('receipt_token', $token)
            ->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        $validated = $request->validate([
            'payment_mode' => ['nullable', 'string', 'regex:/^(full|partial_([1-9]|[1-9][0-9]))$/'],
        ]);

        if (in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_COMPLETED], true)) {
            return response()->json([
                'message' => 'Booking cannot be updated for payment in its current state.',
            ], 422);
        }

        $paymentMode = (string) ($validated['payment_mode'] ?? 'full');
        if ($paymentMode !== 'full' && ! $this->isAllowedPartialPlan($paymentMode)) {
            return response()->json([
                'message' => 'Selected partial payment option is not allowed.',
            ], 422);
        }
        if (empty($booking->payment_method)) {
            $booking->update(['payment_method' => 'online']);
        }
        if (empty($booking->online_payment_plan) && $paymentMode !== '') {
            $booking->update(['online_payment_plan' => $paymentMode]);
        }

        // Persist a payment row immediately so receipts can display "Amount paid" even
        // if webhook delivery is delayed. Webhook will upsert the same provider_ref.
        $invoiceId = trim((string) ($booking->xendit_invoice_id ?? ''));
        $chargeAmount = $this->plannedPaymentAmountForMode($booking, $paymentMode);
        $this->upsertConfirmedPaymentRecord($booking, $invoiceId, $chargeAmount);

        $nextStatus = $this->extractPartialPercentage($paymentMode) !== null
            ? Booking::STATUS_PARTIAL
            : Booking::STATUS_PAID;

        if ($booking->status !== $nextStatus) {
            $booking->update(['status' => $nextStatus]);
        }
        Cache::forget($this->pendingOnlinePaymentCacheKey((int) $booking->id));

        return response()->json([
            'success' => true,
            'booking' => $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']),
        ]);
    }

    private function jsonReceiptForBooking(Booking $booking): JsonResponse
    {
        $this->expireIfNeeded($booking);

        $hasTestimonial = $booking->reviews()->exists();

        $this->ensureBookingQrExists($booking);

        $filename = $booking->qr_code ? basename($booking->qr_code) : null;

        $bookingPayload = $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']);
        $amountPaid = (float) $bookingPayload->total_paid;
        $balance = max(0, (float) $bookingPayload->balance);
        $amountDueNow = $this->resolveAmountDueNow($bookingPayload);

        return response()->json([
            'booking' => $bookingPayload,
            'payment' => [
                'method' => (string) ($bookingPayload->payment_method ?? 'cash'),
                'plan' => (string) ($bookingPayload->online_payment_plan ?? ''),
                'invoice_id' => (string) ($bookingPayload->xendit_invoice_id ?? ''),
                'invoice_url' => (string) ($bookingPayload->xendit_invoice_url ?? ''),
                'can_retry' => $this->canRetryOnlinePayment($bookingPayload),
                'amount_paid' => $amountPaid,
                'balance' => $balance,
                'amount_due_now' => $amountDueNow,
            ],
            'unpaid_expires_at' => $bookingPayload->unpaidExpiresAt()?->toIso8601String(),
            'unpaid_expiry_days' => Booking::UNPAID_EXPIRY_DAYS,
            'use_messenger_deposit_instructions' => $bookingPayload->useMessengerDepositInstructions(),
            'down_payment_notice_applies' => $bookingPayload->downPaymentNoticeApplies(),
            'down_payment_notice_min_lead_days' => Booking::DOWN_PAYMENT_NOTICE_MIN_LEAD_DAYS,
            'qr_code_url' => $filename ? url("/qr-image/{$filename}") : null,
            'has_testimonial' => $hasTestimonial,
        ], 200);
    }

    /**
     * Resolve a public booking identifier used by receipt pages.
     * Supports both receipt token (UUID) and legacy reference number.
     */
    private function findReceiptBooking(string $identifier): ?Booking
    {
        return Booking::with(['guest', 'rooms', 'venues', 'roomLines'])
            ->where('receipt_token', $identifier)
            ->orWhere('reference_number', $identifier)
            ->first();
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();

        $roomLines = isset($validated['room_lines']) && is_array($validated['room_lines'])
            ? $validated['room_lines']
            : [];
        $hasRoomLines = count($roomLines) > 0;
        $hasVenues = isset($validated['venues']) && is_array($validated['venues']) && count($validated['venues']) > 0;

        if (! $hasRoomLines && ! $hasVenues) {
            return response()->json([
                'message' => 'Must select at least one room type or one venue.',
                'error' => 'accommodation_required',
            ], 422);
        }

        try {
            $checkInDate = Carbon::createFromFormat('M d, Y', $validated['check_in'])->startOfDay();
            $checkOutDate = Carbon::createFromFormat('M d, Y', $validated['check_out'])->startOfDay();

            if ($checkOutDate->lt($checkInDate)) {
                return response()->json([
                    'message' => 'Invalid date range',
                    'error' => 'Check-out cannot be before check-in',
                ], 422);
            }

            $hasRoomComponent = $hasRoomLines;
            [$checkIn, $checkOut] = $this->bookingWindowForStorage($hasRoomComponent, $checkInDate, $checkOutDate);

            $guest = Guest::store($request);

            $venueIds = $hasVenues
                ? collect($validated['venues'])
                    ->map(fn ($id) => (int) $id)
                    ->filter()
                    ->values()
                    ->all()
                : [];

            if ($hasRoomLines) {
                $roomLineError = $this->validateGuestRoomLines($roomLines, $checkIn, $checkOut, null);
                if ($roomLineError !== null) {
                    return $roomLineError;
                }
            }

            if ($hasVenues) {
                $availableVenueIds = Venue::whereIn('id', $venueIds)
                    ->availableBetween($checkIn, $checkOut)
                    ->pluck('id')
                    ->all();

                $conflictingVenueIds = array_values(array_diff($venueIds, $availableVenueIds));

                if (! empty($conflictingVenueIds)) {
                    $conflictingVenues = Venue::whereIn('id', $conflictingVenueIds)
                        ->get(['id', 'name']);

                    return response()->json([
                        'message' => 'Booking conflict: one or more venues are already booked for the selected dates.',
                        'error' => 'date_range_conflict',
                        'conflicts' => [
                            'venues' => $conflictingVenues
                                ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])
                                ->values()
                                ->all(),
                        ],
                    ], 422);
                }
            }

            $venueEventType = $hasVenues
                ? ($validated['venue_event_type'] ?? BookingPricing::VENUE_EVENT_WEDDING)
                : null;

            $expectedTotal = BookingPricing::expectedTotalFromRoomLines(
                (int) $validated['days'],
                $roomLines,
                $hasVenues ? Venue::whereIn('id', $venueIds)->get() : collect(),
                $venueEventType,
            );

            if (! BookingPricing::totalsMatch($expectedTotal, (float) $validated['total_price'])) {
                return response()->json([
                    'message' => 'Total price does not match the selected room types, venues, and event type.',
                    'error' => 'price_mismatch',
                ], 422);
            }

            $booking = DB::transaction(function () use (
                $guest,
                $validated,
                $checkIn,
                $checkOut,
                $roomLines,
                $venueIds,
                $venueEventType,
                $expectedTotal
            ) {
                $booking = Booking::create([
                    'guest_id' => $guest->id,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'no_of_days' => $validated['days'],
                    'venue_event_type' => $venueEventType,
                    'total_price' => $expectedTotal,
                    'status' => Booking::STATUS_UNPAID,
                    'payment_method' => (string) ($validated['payment_method'] ?? 'cash'),
                    'online_payment_plan' => (string) ($validated['online_payment_plan'] ?? ''),
                ]);

                $this->generateBookingQr($booking);

                foreach ($roomLines as $line) {
                    $booking->roomLines()->create([
                        'room_type' => $line['room_type'],
                        'inventory_group_key' => $line['inventory_group_key'],
                        'quantity' => (int) $line['quantity'],
                        'unit_price_per_night' => (float) $line['unit_price'],
                    ]);
                }

                if (! empty($venueIds)) {
                    $booking->venues()->attach($venueIds);
                }

                return $booking->fresh(['guest', 'rooms', 'venues', 'roomLines']);
            });

            $paymentMethod = (string) ($validated['payment_method'] ?? 'cash');
            $onlinePaymentPlan = (string) ($validated['online_payment_plan'] ?? 'full');
            $paymentUrl = null;

            if ($paymentMethod === 'online') {
                if ($onlinePaymentPlan !== 'full' && ! $this->isAllowedPartialPlan($onlinePaymentPlan)) {
                    $booking->delete();

                    return response()->json([
                        'message' => 'Selected partial payment option is not allowed by admin settings.',
                        'error' => 'invalid_partial_payment_plan',
                    ], 422);
                }

                $paymentConfigEnabled = filter_var(env('PAYMENT_ONLINE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
                if (! $paymentConfigEnabled) {
                    $booking->delete();

                    return response()->json([
                        'message' => 'Online payment is currently disabled by admin settings.',
                        'error' => 'online_payment_disabled',
                    ], 422);
                }

                $invoice = $this->createXenditInvoiceForBooking($booking, $guest, $onlinePaymentPlan);
                $paymentUrl = $invoice['invoice_url'] ?? null;

                if (! is_string($paymentUrl) || trim($paymentUrl) === '') {
                    $booking->delete();

                    return response()->json([
                        'message' => 'Unable to create Xendit payment invoice.',
                        'error' => 'xendit_invoice_failed',
                    ], 502);
                }

                $booking->update([
                    'xendit_invoice_id' => (string) ($invoice['id'] ?? ''),
                    'xendit_invoice_url' => $paymentUrl,
                ]);

                Cache::put($this->pendingOnlinePaymentCacheKey((int) $booking->id), true, now()->addHours(2));
            }

            return response()->json([
                'message' => 'Booking created successfully',
                'guest' => $guest,
                'booking' => $booking,
                'total_price' => $expectedTotal,
                'payment_url' => $paymentUrl,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to create booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Room stays: check-in 12:00 PM, check-out 10:00 AM (local) on the selected calendar dates.
     * Venue-only: full-day window (start of first day → end of last day) for availability overlap.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bookingWindowForStorage(bool $hasRoomComponent, Carbon $checkInDate, Carbon $checkOutDate): array
    {
        if ($hasRoomComponent) {
            return [
                $checkInDate->copy()->setTime(12, 0, 0),
                $checkOutDate->copy()->setTime(10, 0, 0),
            ];
        }

        return [
            $checkInDate->copy()->startOfDay(),
            $checkOutDate->copy()->endOfDay(),
        ];
    }

    /**
     * Validate each room line against catalogue (type + spec key + rate) and remaining capacity
     * for the stay window (room_lines + assigned rooms on other bookings). Staff still pick concrete rooms.
     */
    private function validateGuestRoomLines(array $roomLines, Carbon $checkIn, Carbon $checkOut, ?int $excludeBookingId): ?JsonResponse
    {
        foreach ($roomLines as $line) {
            $type = $line['room_type'];
            $key = $line['inventory_group_key'];
            $submittedUnit = (float) $line['unit_price'];

            $candidates = Room::query()
                ->where('type', $type)
                ->where('status', '!=', Room::STATUS_MAINTENANCE)
                ->with(['bedSpecifications'])
                ->get()
                ->filter(fn (Room $r) => RoomInventoryGroupKey::forRoom($r) === $key);

            if ($candidates->isEmpty()) {
                return response()->json([
                    'message' => 'One or more room selections do not match available inventory.',
                    'error' => 'invalid_room_line',
                ], 422);
            }

            $unitMatchesAnyCandidate = $candidates->contains(
                fn (Room $r) => BookingPricing::totalsMatch((float) $r->price, $submittedUnit),
            );

            if (! $unitMatchesAnyCandidate) {
                return response()->json([
                    'message' => 'Room line price does not match current rates.',
                    'error' => 'price_mismatch',
                ], 422);
            }
        }

        $remainingMap = RoomInventoryGroupAvailability::remainingForRangeMap($checkIn, $checkOut, $excludeBookingId);
        $requestedTotals = [];
        foreach ($roomLines as $line) {
            $c = RoomInventoryGroupAvailability::compositeKey($line['room_type'], $line['inventory_group_key']);
            $requestedTotals[$c] = ($requestedTotals[$c] ?? 0) + (int) $line['quantity'];
        }
        foreach ($requestedTotals as $composite => $qty) {
            $rem = $remainingMap[$composite] ?? 0;
            if ($qty > $rem) {
                [$type, $invKey] = explode("\0", $composite, 2);

                return response()->json([
                    'message' => 'Not enough units left for one of your selected room types for these dates.',
                    'error' => 'date_range_conflict',
                    'conflicts' => [
                        'room_lines' => [
                            [
                                'room_type' => $type,
                                'inventory_group_key' => $invKey,
                                'requested' => $qty,
                                'available' => $rem,
                            ],
                        ],
                    ],
                ], 422);
            }
        }

        return null;
    }

    private function expectedTotalForBooking(Booking $booking, int $nights): float
    {
        $nights = max(1, $nights);
        if ($booking->rooms->isNotEmpty()) {
            return BookingPricing::expectedTotal(
                $nights,
                $booking->rooms,
                $booking->venues,
                $booking->venue_event_type,
            );
        }
        if ($booking->roomLines->isNotEmpty()) {
            return BookingPricing::expectedTotalFromRoomLines(
                $nights,
                $booking->roomLines,
                $booking->venues,
                $booking->venue_event_type,
            );
        }

        return BookingPricing::expectedTotal(
            $nights,
            collect(),
            $booking->venues,
            $booking->venue_event_type,
        );
    }

    /**
     * Display a specific booking.
     */
    public function show($id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])->find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            $this->expireIfNeeded($booking);

            return response()->json($booking, 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error retrieving booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $booking = Booking::with(['guest', 'rooms', 'venues', 'roomLines'])->find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            if ($this->expireIfNeeded($booking)) {
                return response()->json([
                    'message' => 'Booking expired after 3 days without payment and was cancelled.',
                ], 422);
            }

            $validated = $request->validate([
                'status' => 'sometimes|string|in:'.implode(',', [
                    Booking::STATUS_UNPAID,
                    Booking::STATUS_PAID,
                    Booking::STATUS_COMPLETED,
                    Booking::STATUS_OCCUPIED,
                    Booking::STATUS_CANCELLED,
                ]),
            ]);

            if (! empty($validated['status'])) {
                $booking->update([
                    'status' => $validated['status'],
                ]);
            }

            $booking->refresh()->load(['guest', 'rooms', 'venues']);

            return response()->json([
                'message' => 'Booking updated successfully',
                'booking' => $booking,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error updating booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $booking = Booking::find($id);

            if (! $booking) {
                return response()->json([
                    'message' => 'Booking not found',
                ], 404);
            }

            $booking->delete();

            return response()->json([
                'message' => 'Booking deleted successfully',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error deleting booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, Booking $booking)
    {
        if ($this->expireIfNeeded($booking)) {
            return response()->json([
                'message' => 'Booking already expired and has been cancelled automatically.',
            ], 422);
        }

        $request->validate([
            'otp' => 'required|string',
        ]);

        try {
            $allowedStatuses = [
                Booking::STATUS_UNPAID,
                Booking::STATUS_PAID,
            ];

            if (defined(Booking::class.'::STATUS_RESCHEDULED')) {
                $allowedStatuses[] = Booking::STATUS_RESCHEDULED;
            }

            if (! in_array($booking->status, $allowedStatuses, true)) {
                return response()->json([
                    'message' => 'Booking cannot be cancelled in its current state.',
                ], 422);
            }

            if (! $this->bookingActionOtpService->verifyAndConsume(
                $booking->reference_number,
                BookingActionOtpService::PURPOSE_CANCEL,
                (string) $request->input('otp'),
            )) {
                return response()->json([
                    'message' => 'Invalid or expired verification code.',
                ], 422);
            }

            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
            ]);

            broadcast(new BookingCancelled($booking))->toOthers();

            return response()->json([
                'message' => 'Booking cancelled successfully.',
                'booking' => $booking,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error cancelling booking',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reschedule(Request $request, $reference)
    {
        $request->validate([
            'check_in' => 'required|date',
            'check_out' => 'required|date|after:check_in',
            'otp' => 'required|string',
        ]);

        $booking = Booking::where('reference_number', $reference)->firstOrFail();

        if ($this->expireIfNeeded($booking)) {
            return response()->json([
                'message' => 'Booking expired after 3 days without payment and cannot be rescheduled.',
            ], 422);
        }

        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            return response()->json([
                'message' => 'Cannot reschedule this booking',
            ], 422);
        }

        $booking->loadMissing(['rooms', 'venues', 'roomLines']);

        $checkInDate = Carbon::parse($request->check_in)->startOfDay();
        $checkOutDate = Carbon::parse($request->check_out)->startOfDay();

        $hasRoomComponent = $booking->rooms->isNotEmpty() || $booking->roomLines->isNotEmpty();
        [$checkIn, $checkOut] = $this->bookingWindowForStorage($hasRoomComponent, $checkInDate, $checkOutDate);

        $roomIds = $booking->rooms->pluck('id')->toArray();
        if (! empty($roomIds)) {
            $availableRoomIds = Room::whereIn('id', $roomIds)
                ->availableBetween($checkIn, $checkOut, $booking->id)
                ->pluck('id')
                ->toArray();

            if (count($availableRoomIds) !== count($roomIds)) {
                return response()->json([
                    'message' => 'One or more currently booked rooms are not available for the new dates',
                ], 422);
            }
        } elseif ($booking->roomLines->isNotEmpty()) {
            $roomLineError = $this->validateGuestRoomLines(
                $booking->roomLines->map(fn ($l) => [
                    'room_type' => $l->room_type,
                    'inventory_group_key' => $l->inventory_group_key,
                    'quantity' => $l->quantity,
                    'unit_price' => (float) $l->unit_price_per_night,
                ])->all(),
                $checkIn,
                $checkOut,
                $booking->id,
            );
            if ($roomLineError !== null) {
                return $roomLineError;
            }
        }

        $venueIds = $booking->venues->pluck('id')->toArray();
        if (! empty($venueIds)) {
            $availableVenueIds = Venue::whereIn('id', $venueIds)
                ->availableBetween($checkIn, $checkOut, $booking->id)
                ->pluck('id')
                ->toArray();

            if (count($availableVenueIds) !== count($venueIds)) {
                return response()->json([
                    'message' => 'One or more currently booked venues are not available for the new dates',
                ], 422);
            }
        }

        if (! $this->bookingActionOtpService->verifyAndConsume(
            $booking->reference_number,
            BookingActionOtpService::PURPOSE_RESCHEDULE,
            (string) $request->input('otp'),
        )) {
            return response()->json([
                'message' => 'Invalid or expired verification code.',
            ], 422);
        }

        $nights = max(1, (int) $checkInDate->diffInDays($checkOutDate));

        $newTotal = $this->expectedTotalForBooking($booking, $nights);

        $booking->update([
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total_price' => $newTotal,
            'status' => 'rescheduled',
        ]);

        broadcast(new BookingRescheduled($booking))->toOthers();

        return response()->json([
            'message' => 'Booking rescheduled successfully',
            'booking' => $booking,
        ]);
    }

    public function paymentStatusByReceiptToken(string $token): JsonResponse
    {
        if (! Str::isUuid($token)) {
            return response()->json([
                'message' => 'Invalid receipt token.',
            ], 422);
        }

        $booking = Booking::query()->where('receipt_token', $token)->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => (string) $booking->status,
                'payment_method' => (string) ($booking->payment_method ?? 'cash'),
                'online_payment_plan' => (string) ($booking->online_payment_plan ?? ''),
                'invoice_id' => (string) ($booking->xendit_invoice_id ?? ''),
                'invoice_url' => (string) ($booking->xendit_invoice_url ?? ''),
                'can_retry' => $this->canRetryOnlinePayment($booking),
            ],
        ]);
    }

    public function retryOnlinePaymentByReceiptToken(string $token): JsonResponse
    {
        if (! Str::isUuid($token)) {
            return response()->json([
                'message' => 'Invalid receipt token.',
            ], 422);
        }

        $booking = Booking::with('guest')->where('receipt_token', $token)->first();

        if (! $booking) {
            return response()->json([
                'message' => 'Booking not found',
            ], 404);
        }

        if (! $this->canRetryOnlinePayment($booking)) {
            return response()->json([
                'message' => 'Payment retry is not allowed for this booking state.',
            ], 422);
        }

        $plan = (string) ($booking->online_payment_plan ?: 'full');
        $guest = $booking->guest;
        if (! $guest) {
            return response()->json([
                'message' => 'Guest not found for this booking.',
            ], 422);
        }

        $overrideAmount = null;
        if ((string) $booking->status === Booking::STATUS_PARTIAL) {
            $overrideAmount = max(1, (float) $booking->balance);
        }

        $invoice = $this->createXenditInvoiceForBooking($booking, $guest, $plan, $overrideAmount);
        $paymentUrl = $invoice['invoice_url'] ?? null;

        if (! is_string($paymentUrl) || trim($paymentUrl) === '') {
            return response()->json([
                'message' => 'Unable to create a new payment invoice.',
            ], 502);
        }

        $booking->update([
            'xendit_invoice_id' => (string) ($invoice['id'] ?? ''),
            'xendit_invoice_url' => $paymentUrl,
            'payment_method' => 'online',
        ]);

        Cache::put($this->pendingOnlinePaymentCacheKey((int) $booking->id), true, now()->addHours(2));

        return response()->json([
            'success' => true,
            'payment_url' => $paymentUrl,
            'booking' => $booking->fresh(),
        ]);
    }

    private function ensureBookingQrExists(Booking $booking): void
    {
        if ($booking->qr_code && Storage::disk('public')->exists($booking->qr_code)) {
            return;
        }

        $this->generateBookingQr($booking, $booking->qr_code ? basename($booking->qr_code) : null);
    }

    private function generateBookingQr(Booking $booking, ?string $filename = null): string
    {
        $payload = json_encode([
            'booking_id' => $booking->id,
            // Keep both key names for backward compatibility with existing scanners.
            'reference_number' => $booking->reference_number,
            'reference' => $booking->reference_number,
            'guest_id' => $booking->guest_id,
        ]);

        $filename = $filename ?: Str::uuid().'.svg';
        $path = 'qr/bookings/'.$filename;

        $svg = QrCode::format('svg')->size(300)->generate($payload);

        Storage::disk('public')->put($path, $svg);

        if ($booking->qr_code !== $path) {
            $booking->update([
                'qr_code' => $path,
            ]);
        }

        return $path;
    }

    private function expireIfNeeded(Booking $booking): bool
    {
        if (Cache::has($this->pendingOnlinePaymentCacheKey((int) $booking->id))) {
            return false;
        }

        return $booking->expireIfUnpaidExceededRule();
    }

    private function pendingOnlinePaymentCacheKey(int $bookingId): string
    {
        return "booking_online_payment_pending_{$bookingId}";
    }

    private function canRetryOnlinePayment(Booking $booking): bool
    {
        if ((string) ($booking->payment_method ?? '') !== 'online') {
            return false;
        }

        return in_array((string) $booking->status, [
            Booking::STATUS_UNPAID,
            Booking::STATUS_PARTIAL,
        ], true);
    }

    /**
     * @return array{invoice_url?: string}
     */
    private function createXenditInvoiceForBooking(
        Booking $booking,
        Guest $guest,
        string $paymentPlan,
        ?float $overrideAmount = null
    ): array
    {
        $secretKey = trim((string) config('services.xendit.secret_key'));
        if ($secretKey === '') {
            return [];
        }

        $totalAmount = (float) $booking->total_price;
        $partialPercent = $this->extractPartialPercentage($paymentPlan);
        $chargeAmount = $partialPercent !== null
            ? max(1, (float) round($totalAmount * ($partialPercent / 100), 2))
            : max(1, $totalAmount);
        if ($overrideAmount !== null) {
            $chargeAmount = max(1, (float) round($overrideAmount, 2));
        }

        $frontendBase = rtrim((string) config('app.frontend_url'), '/');
        $receiptToken = (string) $booking->receipt_token;
        $successQuery = $partialPercent !== null
            ? '?payment=success&payment_mode='.rawurlencode($paymentPlan)
            : '?payment=success&payment_mode=full';
        $failureQuery = '?payment=failed';

        $successRedirect = "{$frontendBase}/booking-receipt/{$receiptToken}{$successQuery}";
        $failureRedirect = "{$frontendBase}/booking-receipt/{$receiptToken}{$failureQuery}";

        $payload = [
            'external_id' => $booking->reference_number,
            'amount' => $chargeAmount,
            'payer_email' => (string) ($guest->email ?? ''),
            'description' => "Booking {$booking->reference_number} ({$paymentPlan})",
            'success_redirect_url' => $successRedirect,
            'failure_redirect_url' => $failureRedirect,
            'currency' => 'PHP',
            'metadata' => [
                'reference_number' => $booking->reference_number,
                'receipt_token' => $booking->receipt_token,
                'payment_mode' => $paymentPlan,
                'full_amount' => $totalAmount,
                'override_amount' => $overrideAmount,
            ],
        ];

        $response = Http::withBasicAuth($secretKey, '')
            ->timeout(20)
            ->post((string) config('services.xendit.invoice_url'), $payload);

        if (! $response->successful()) {
            return [];
        }

        $body = $response->json();

        return is_array($body) ? $body : [];
    }

    private function extractPartialPercentage(string $plan): ?int
    {
        if (preg_match('/^partial_([1-9]|[1-9][0-9])$/', $plan, $matches) !== 1) {
            return null;
        }

        $percent = (int) ($matches[1] ?? 0);
        if ($percent <= 0 || $percent >= 100) {
            return null;
        }

        return $percent;
    }

    private function resolveAmountDueNow(Booking $booking): float
    {
        $totalAmount = (float) $booking->total_price;
        $balance = max(0, (float) $booking->balance);
        $plan = (string) ($booking->online_payment_plan ?? '');
        $status = (string) ($booking->status ?? '');
        $partialPercent = $this->extractPartialPercentage($plan);

        if ($status === Booking::STATUS_PARTIAL) {
            return $balance;
        }

        if ($partialPercent !== null) {
            return max(0, (float) round($totalAmount * ($partialPercent / 100), 2));
        }

        return max(0, $totalAmount);
    }

    private function plannedPaymentAmountForMode(Booking $booking, string $paymentMode): float
    {
        $totalAmount = (float) $booking->total_price;
        $balance = max(0, (float) $booking->balance);
        $partialPercent = $this->extractPartialPercentage($paymentMode);

        if ((string) $booking->status === Booking::STATUS_PARTIAL) {
            return $balance;
        }

        if ($partialPercent !== null) {
            return max(0, (float) round($totalAmount * ($partialPercent / 100), 2));
        }

        return max(0, $totalAmount);
    }

    private function upsertConfirmedPaymentRecord(Booking $booking, string $invoiceId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $totalAmount = (int) round((float) $booking->total_price);
        $partialAmount = (int) round($amount);
        $isFullyPaid = $totalAmount > 0 && $partialAmount >= $totalAmount;

        // Best-effort: prefer linking to invoice id; if missing, still store the payment.
        if ($invoiceId !== '') {
            $existing = Payment::query()
                ->where('booking_id', $booking->id)
                ->where('provider', 'xendit')
                ->where('provider_ref', $invoiceId)
                ->first();

            if ($existing) {
                $existing->update([
                    'total_amount' => $totalAmount,
                    'partial_amount' => $partialAmount,
                    'is_fullypaid' => $isFullyPaid,
                    'provider_status' => 'confirmed',
                ]);

                return;
            }
        }

        $booking->payments()->create([
            'total_amount' => $totalAmount,
            'partial_amount' => $partialAmount,
            'is_fullypaid' => $isFullyPaid,
            'provider' => $invoiceId !== '' ? 'xendit' : null,
            'provider_ref' => $invoiceId !== '' ? $invoiceId : null,
            'provider_status' => 'confirmed',
        ]);
    }

    /**
     * @return array{partial_payment_options: array<int>, allow_custom_partial_payment: bool}
     */
    private function paymentSettingsConfig(): array
    {
        $cached = Cache::get('payment_settings_config');
        if (is_array($cached)) {
            $options = collect($cached['partial_payment_options'] ?? [])
                ->map(fn ($v): int => (int) $v)
                ->filter(fn (int $v): bool => $v > 0 && $v < 100)
                ->unique()
                ->sort()
                ->values()
                ->all();

            return [
                'partial_payment_options' => $options !== [] ? $options : [30],
                'allow_custom_partial_payment' => (bool) ($cached['allow_custom_partial_payment'] ?? false),
            ];
        }

        $options = collect(explode(',', (string) env('PAYMENT_PARTIAL_OPTIONS', '10,20,30')))
            ->map(fn (string $v): int => (int) trim($v))
            ->filter(fn (int $v): bool => $v > 0 && $v < 100)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'partial_payment_options' => $options !== [] ? $options : [30],
            'allow_custom_partial_payment' => filter_var(env('PAYMENT_PARTIAL_ALLOW_CUSTOM', false), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    private function isAllowedPartialPlan(string $plan): bool
    {
        $percent = $this->extractPartialPercentage($plan);
        if ($percent === null) {
            return false;
        }

        $settings = $this->paymentSettingsConfig();
        if ($settings['allow_custom_partial_payment']) {
            return true;
        }

        return in_array($percent, $settings['partial_payment_options'], true);
    }
}
