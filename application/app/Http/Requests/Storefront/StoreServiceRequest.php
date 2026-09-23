<?php

namespace App\Http\Requests\Storefront;

use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\ServiceRequestVehiclePayload;
use App\Rules\CustomerPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceRequest extends FormRequest
{
    private bool $usedLegacyVehiclePayload = false;

    public function rules(): array
    {
        $slug = mb_strtolower((string) $this->route('slug'));
        $isSeasonal = $this->isSeasonal($slug);
        $isMounting = $slug === 'omlegging-balansering';
        $isBattery = $slug === 'bilbatteri';
        $workshopOnly = in_array($slug, ['omlegging-balansering', 'tpms-sensor'], true);
        $vehicleCount = is_array($this->input('vehicles')) ? count($this->input('vehicles')) : 0;
        $seasonalVehicleTypes = $isSeasonal && $this->input('fulfilment') === 'workshop'
            ? ['passenger']
            : ['passenger', 'large_vehicle', 'six_wheels'];
        $requiresProblem = in_array($slug, ['tpms-sensor', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
        $supportsAdditionalServices = in_array($slug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
        $bookingMode = (string) $this->input('booking_mode', '');
        $requiresStructuredDate = in_array($bookingMode, ['period', 'exact'], true);
        $bookingToday = now((string) config('service_booking.timezone', 'Europe/Oslo'))->format('Y-m-d');

        return [
            'submission_token' => ['required', 'uuid'],
            'service_variant' => [Rule::requiredIf($isMounting), 'nullable', 'string', Rule::in(['mounting_balancing', 'balancing_only'])],
            'fulfilment' => ['required', Rule::in($workshopOnly ? ['workshop'] : ['workshop', 'mobile'])],
            'preferred_date' => [Rule::requiredIf($isSeasonal || $isMounting || $isBattery || $requiresStructuredDate), 'nullable', 'date_format:Y-m-d', 'after_or_equal:'.$bookingToday],
            'preferred_window' => ['nullable', 'string', 'max:64'],
            'booking_mode' => ['nullable', Rule::in(['period', 'exact'])],
            'preferred_time_start' => [Rule::requiredIf($bookingMode === 'period'), 'nullable', 'date_format:H:i'],
            'preferred_time_end' => [Rule::requiredIf($bookingMode === 'period'), 'nullable', 'date_format:H:i', 'after:preferred_time_start'],
            'booking_slot_key' => [Rule::requiredIf($bookingMode === 'exact' && $vehicleCount <= 2), 'nullable', 'string', 'max:80'],
            'vehicle_payload_conflict' => ['prohibited'],
            'internal_storage_payload' => ['prohibited'],
            'car_count' => [Rule::requiredIf($isSeasonal), 'nullable', 'integer', 'between:1,'.ServiceRequestVehiclePayload::MAX_VEHICLES],
            'vehicles' => [Rule::requiredIf($isSeasonal), Rule::prohibitedIf(! $isSeasonal), 'array', 'min:1', 'max:'.ServiceRequestVehiclePayload::MAX_VEHICLES],
            'vehicles.*' => ['array:vehicle,registration_number,tire_size,vehicle_type,rim_band,storage_plan'],
            'vehicles.*.vehicle' => ['required', 'string', 'max:120'],
            'vehicles.*.registration_number' => ['nullable', 'string', 'max:32'],
            'vehicles.*.tire_size' => ['nullable', 'string', 'max:64'],
            'vehicles.*.vehicle_type' => ['required', Rule::in($seasonalVehicleTypes)],
            'vehicles.*.rim_band' => ['required', Rule::in(['unknown', 'r15', 'r16-r18', 'r19'])],
            'vehicles.*.storage_plan' => ['required', Rule::in(ServiceRequestVehiclePayload::STORAGE_PLANS)],
            'vehicle' => [Rule::prohibitedIf($isSeasonal), 'nullable', 'string', 'max:120'],
            'registration_number' => [Rule::prohibitedIf($isSeasonal), Rule::requiredIf($isBattery), 'nullable', 'string', 'max:32'],
            'tire_size' => [Rule::requiredIf($isMounting), 'nullable', 'string', 'max:64'],
            'wheel_count' => [Rule::requiredIf($isMounting), 'nullable', 'integer', 'between:1,4'],
            'dekkhotell' => ['nullable', 'boolean'],
            'dekkhotell_already_stored' => ['prohibited'],
            'dekkhotell_number' => ['prohibited'],
            'storage_number' => ['prohibited'],
            'additional_services' => [Rule::prohibitedIf(! $supportsAdditionalServices), 'nullable', 'array', 'max:3'],
            'additional_services.*' => ['string', 'distinct', Rule::in(['air_filter', 'cabin_filter', 'wiper_blades'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:24', new CustomerPhone],
            'address' => ['nullable', 'required_if:fulfilment,mobile', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:120'],
            'note' => [$requiresProblem ? 'required' : 'nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => __('project.request.problem_required'),
            'registration_number.required' => __('service_requests.validation.registration_required'),
            'registration_number.string' => __('service_requests.validation.registration_invalid'),
            'registration_number.max' => __('service_requests.validation.registration_max'),
            'preferred_date.required' => __('service_requests.validation.preferred_date_required'),
            'preferred_date.date_format' => __('service_requests.validation.preferred_date_invalid'),
            'preferred_date.after_or_equal' => __('service_requests.validation.preferred_date_not_past'),
            'preferred_time_start.required' => __('service_requests.validation.period_start_required'),
            'preferred_time_end.required' => __('service_requests.validation.period_end_required'),
            'preferred_time_end.after' => __('service_requests.validation.period_order'),
            'vehicle_payload_conflict.prohibited' => __('service_requests.validation.vehicle_payload_conflict'),
            'internal_storage_payload.prohibited' => __('service_requests.validation.internal_storage_payload'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $vehicles = is_array($this->input('vehicles')) ? array_values($this->input('vehicles')) : [];
            foreach ($vehicles as $index => $vehicle) {
                if (! is_array($vehicle)) {
                    continue;
                }
                if (($vehicle['storage_plan'] ?? 'none') !== 'none' && blank($vehicle['registration_number'] ?? null)) {
                    $validator->errors()->add("vehicles.{$index}.registration_number", __('service_requests.validation.registration_required'));
                }
            }

            if ($this->usedLegacyVehiclePayload) {
                foreach ([
                    0 => [
                        'vehicle' => 'vehicle',
                        'registration_number' => 'registration_number',
                        'tire_size' => 'tire_size',
                        'vehicle_type' => 'vehicle_type',
                        'rim_band' => 'rim_band',
                        'storage_plan' => 'storage_plan',
                    ],
                    1 => [
                        'vehicle' => 'vehicle_2',
                        'registration_number' => 'registration_number_2',
                        'tire_size' => 'tire_size_2',
                        'vehicle_type' => 'vehicle_type_2',
                        'rim_band' => 'rim_band_2',
                        'storage_plan' => 'storage_plan_2',
                    ],
                ] as $index => $aliases) {
                    if (! isset($vehicles[$index])) {
                        continue;
                    }
                    foreach ($aliases as $canonical => $legacy) {
                        foreach ($validator->errors()->get("vehicles.{$index}.{$canonical}") as $message) {
                            $validator->errors()->add($legacy, $message);
                        }
                    }
                }
            }

            if (($this->input('booking_mode') ?? null) === 'exact' && count($vehicles) > 2) {
                $validator->errors()->add('booking_mode', __('service_requests.validation.exact_vehicle_limit'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if (! filled($this->input('submission_token'))) {
            $this->merge(['submission_token' => (string) Str::uuid()]);
        }

        $slug = mb_strtolower((string) $this->route('slug'));
        if (! $this->isSeasonal($slug)) {
            return;
        }

        $input = $this->all();
        $conflict = ServiceRequestVehiclePayload::hasConflictingPayload($input);
        $this->usedLegacyVehiclePayload = ! is_array($input['vehicles'] ?? null)
            && ServiceRequestVehiclePayload::legacyVehicles($input) !== [];
        $vehicles = is_array($input['vehicles'] ?? null)
            ? array_values($input['vehicles'])
            : ServiceRequestVehiclePayload::legacyVehicles($input);
        if ($vehicles === []) {
            $vehicles = [[
                'vehicle' => null,
                'registration_number' => null,
                'tire_size' => null,
                'vehicle_type' => 'passenger',
                'rim_band' => 'unknown',
                'storage_plan' => $this->boolean('dekkhotell') ? 'new' : 'none',
            ]];
        }

        $internalStoragePayload = false;
        foreach ($vehicles as &$vehicle) {
            if (is_array($vehicle) && array_key_exists('storage_number', $vehicle)) {
                $internalStoragePayload = true;
                unset($vehicle['storage_number']);
            }
        }
        unset($vehicle);

        foreach (['vehicle', 'registration_number', 'tire_size', 'vehicle_type', 'rim_band', 'storage_plan', 'vehicle_2', 'registration_number_2', 'tire_size_2', 'vehicle_type_2', 'rim_band_2', 'storage_plan_2'] as $legacyField) {
            unset($input[$legacyField]);
        }
        $input['vehicles'] = $vehicles;
        $input['car_count'] = count($vehicles);
        if ($conflict) {
            $input['vehicle_payload_conflict'] = true;
        }
        if ($internalStoragePayload) {
            $input['internal_storage_payload'] = true;
        }
        $this->replace($input);
    }

    private function isSeasonal(string $slug): bool
    {
        $flow = ServicePage::query()->where('slug', $slug)->value('request_flow');
        $flow = $flow instanceof ServiceRequestFlow ? $flow->value : $flow;

        return $flow === ServiceRequestFlow::Seasonal->value
            || in_array($slug, ['mobilt-dekkskift', 'dekkverksted', 'dekkskift', 'dekkskift-tromso', 'hjulskift', 'sesongskift'], true);
    }
}
