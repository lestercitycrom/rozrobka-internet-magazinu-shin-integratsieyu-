<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\StoreServiceRequest;
use App\Modules\Content\Enums\ServicePageLocale;
use App\Modules\Content\ReadModel\PublishedServicePageReadService;
use App\Modules\Outbox\OutboxPublisher;
use App\Modules\ServiceBooking\BookingCalendar;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\SeasonalQuoteCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function create(string $slug, PublishedServicePageReadService $services, SeasonalQuoteCalculator $quotes, BookingCalendar $calendar): View
    {
        $service = $services->findBySlug($slug, $this->locale());
        abort_if($service === null, 404);

        $requestedFulfilment = old('fulfilment', request()->query('fulfilment', 'workshop'));
        $requestedFulfilment = in_array($requestedFulfilment, ['workshop', 'mobile'], true) ? $requestedFulfilment : 'workshop';

        return view('storefront.requests.create', [
            'service' => $service,
            'seasonalPrices' => $quotes->publicMatrix(),
            'scheduleConfigured' => $calendar->hasConfiguredSchedule($requestedFulfilment),
            'submissionToken' => old('submission_token', (string) Str::uuid()),
        ]);
    }

    public function store(StoreServiceRequest $request, string $slug, PublishedServicePageReadService $services, SeasonalQuoteCalculator $quotes, BookingCalendar $calendar, OutboxPublisher $outbox): RedirectResponse
    {
        abort_if($services->findBySlug($slug, $this->locale()) === null, 404);
        $data = $request->validated();
        unset($data['consent'], $data['website']);
        $submissionToken = $data['submission_token'];
        unset($data['submission_token']);
        $submittedSlotKey = $data['booking_slot_key'] ?? null;
        unset($data['booking_slot_key']);
        $vehicles = $this->vehicles($data);
        $quote = $vehicles === [] ? null : $quotes->calculate($data['fulfilment'], $vehicles);
        $usesStorage = collect($vehicles)->contains(fn (array $vehicle): bool => $vehicle['storage_plan'] !== 'none');
        unset(
            $data['car_count'], $data['vehicle_type'], $data['rim_band'], $data['storage_plan'],
            $data['vehicle_2'], $data['registration_number_2'], $data['tire_size_2'],
            $data['vehicle_type_2'], $data['rim_band_2'], $data['storage_plan_2'],
        );

        $item = DB::transaction(function () use ($data, $submissionToken, $submittedSlotKey, $slug, $vehicles, $quote, $usesStorage, $request, $calendar, $outbox): ServiceRequest {
            $ownsSubmission = DB::table('service_request_submissions')->insertOrIgnore([
                'token' => $submissionToken,
                'service_request_id' => null,
                'created_at' => now(),
            ]) === 1;
            if (! $ownsSubmission) {
                $existingId = DB::table('service_request_submissions')
                    ->where('token', $submissionToken)
                    ->lockForUpdate()
                    ->value('service_request_id');

                return ServiceRequest::query()->findOrFail($existingId);
            }

            $slot = $vehicles === [] ? null : $calendar->claim(
                $submittedSlotKey,
                $data['fulfilment'],
                $calendar->localDate($data['preferred_date']),
                $data['preferred_window'] ?? null,
            );
            if ($slot !== null) {
                $data['preferred_window'] = $slot->startsAt->setTimezone($calendar->timezone())->format('H:i')
                    .'–'.$slot->endsAt->setTimezone($calendar->timezone())->format('H:i');
            }
            $item = ServiceRequest::create([
                ...$data,
                'reference' => $this->reference(),
                'submission_token' => $submissionToken,
                'locale' => app()->getLocale(),
                'service_slug' => $slug,
                'booking_schedule_interval_id' => $slot?->intervalId,
                'booking_slot_key' => $slot?->key,
                'booking_starts_at' => $slot?->startsAt,
                'booking_ends_at' => $slot?->endsAt,
                'dekkhotell' => $vehicles === [] ? $request->boolean('dekkhotell') : $usesStorage,
                'vehicles' => $vehicles === [] ? null : $vehicles,
                'quote_min_minor' => $quote['min_minor'] ?? null,
                'quote_max_minor' => $quote['max_minor'] ?? null,
                'quote_currency' => $quote['currency'] ?? null,
                'email' => mb_strtolower($data['email']),
                'status' => ServiceRequestStatus::New,
                'consent_at' => now(),
            ]);
            DB::table('service_request_submissions')->where('token', $submissionToken)->update([
                'service_request_id' => $item->getKey(),
            ]);
            $payload = [
                'event' => 'service_request_created',
                'reference' => $item->reference,
                'service_slug' => $item->service_slug,
                'fulfilment' => $item->fulfilment,
                'preferred_date' => $item->preferred_date?->format('Y-m-d'),
                'booking_starts_at' => $item->booking_starts_at?->toIso8601String(),
                'booking_ends_at' => $item->booking_ends_at?->toIso8601String(),
                'car_count' => max(1, count($item->vehicles ?? [])),
                'dekkhotell' => $item->dekkhotell,
                'quote_min_minor' => $item->quote_min_minor,
                'quote_max_minor' => $item->quote_max_minor,
                'currency' => $item->quote_currency,
            ];
            $outbox->forAggregate('service_request', (string) $item->getKey(), 'notion.service_request_created', "notion:service-request-created:{$item->id}", $payload);
            $outbox->forAggregate('service_request', (string) $item->getKey(), 'telegram.service_request_created', "telegram:service-request-created:{$item->id}", $payload);

            return $item;
        }, 3);

        return redirect()->route($this->routePrefix().'requests.thanks', $item->reference);
    }

    public function thanks(string $reference): View
    {
        $request = ServiceRequest::query()->where('reference', $reference)->first();
        abort_if($request === null, 404);

        return view('storefront.requests.thanks', ['reference' => $request->reference]);
    }

    private function reference(): string
    {
        do {
            $reference = 'SR-'.Str::upper(Str::random(14));
        } while (ServiceRequest::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function locale(): ServicePageLocale
    {
        return app()->getLocale() === 'ru' ? ServicePageLocale::Russian : ServicePageLocale::NorwegianBokmal;
    }

    private function routePrefix(): string
    {
        return app()->getLocale() === 'ru' ? 'storefront.preview.' : 'storefront.';
    }

    /** @param array<string, mixed> $data @return list<array<string, string|null>> */
    private function vehicles(array $data): array
    {
        if (! isset($data['car_count'])) {
            return [];
        }

        $vehicles = [[
            'vehicle' => $data['vehicle'],
            'registration_number' => $data['registration_number'] ?? null,
            'tire_size' => $data['tire_size'] ?? null,
            'vehicle_type' => $data['vehicle_type'],
            'rim_band' => $data['rim_band'],
            'storage_plan' => $data['storage_plan'],
        ]];

        if ((int) $data['car_count'] === 2) {
            $vehicles[] = [
                'vehicle' => $data['vehicle_2'],
                'registration_number' => $data['registration_number_2'] ?? null,
                'tire_size' => $data['tire_size_2'] ?? null,
                'vehicle_type' => $data['vehicle_type_2'],
                'rim_band' => $data['rim_band_2'],
                'storage_plan' => $data['storage_plan_2'],
            ];
        }

        return $vehicles;
    }
}
