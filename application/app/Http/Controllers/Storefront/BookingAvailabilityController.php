<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Modules\ServiceBooking\BookingCalendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BookingAvailabilityController extends Controller
{
    public function __invoke(Request $request, BookingCalendar $calendar): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'fulfilment' => ['required', Rule::in(['workshop', 'mobile'])],
        ]);

        $availability = $calendar->availability($data['fulfilment'], $calendar->localDate($data['date']));

        return response()->json([
            'configured' => $availability['configured'],
            'slots' => $availability['slots']->map(
                fn ($slot): array => $slot->toPublicArray($calendar->timezone())
            )->values()->all(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
