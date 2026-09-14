<?php

namespace App\Http\Requests\Admin;

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\ServiceRequestVehiclePayload;
use App\Rules\CustomerPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpsertServiceRequest extends FormRequest
{
    private bool $usedLegacyVehiclePayload = false;

    private bool $legacyGeneralStorageMissingAlreadyStored = false;

    public function authorize(): bool
    {
        return $this->user()?->canOperateServiceRequests() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isCreate = $this->routeIs('admin.service-requests.store');
        $requestModel = $this->route('request');
        $requestModel = $requestModel instanceof ServiceRequest ? $requestModel : null;
        $slug = mb_strtolower((string) $this->input('service_slug', $requestModel?->service_slug));
        $effectiveFulfilment = (string) $this->input('fulfilment', $requestModel?->fulfilment);
        $serviceChanged = $isCreate || ($this->has('service_slug') && $slug !== mb_strtolower((string) $requestModel?->service_slug));
        $fulfilmentChanged = ! $isCreate
            && $this->has('fulfilment')
            && $effectiveFulfilment !== (string) $requestModel?->fulfilment;
        $serviceContractPresence = ($isCreate || $serviceChanged) ? [] : ['sometimes'];
        $addressPresence = ($isCreate || $fulfilmentChanged) ? [] : ['sometimes'];
        $isSeasonal = $this->isSeasonal($slug);
        $isMounting = $slug === 'omlegging-balansering';
        $isBattery = $slug === 'bilbatteri';
        $requiresRequestedDate = $isSeasonal || $isMounting || $isBattery;
        $workshopOnly = in_array($slug, ['omlegging-balansering', 'tpms-sensor'], true);
        $storedVehicles = is_array($requestModel?->vehicles) ? $requestModel->vehicles : [];
        $submittedVehicles = is_array($this->input('vehicles')) ? array_values($this->input('vehicles')) : [];
        $effectiveCarCount = count($submittedVehicles) ?: ((! $isCreate && ! $serviceChanged) ? count($storedVehicles) : 0);
        $supportsGeneralStorage = $this->supportsGeneralStorage($slug);
        $storedGeneralPlan = ServiceRequestVehiclePayload::generalPlan(
            (bool) $requestModel?->dekkhotell,
            $requestModel?->dekkhotell_already_stored,
        );
        $generalPlan = (string) $this->input('storage_plan', $storedGeneralPlan);
        $generalPlanChanged = $isCreate || $serviceChanged || ($this->has('storage_plan') && $generalPlan !== $storedGeneralPlan);
        $generalPlans = ServiceRequestVehiclePayload::STORAGE_PLANS;
        if (! $isCreate && $storedGeneralPlan === 'unknown') {
            $generalPlans[] = 'unknown';
        }
        $seasonalVehicleTypes = $isSeasonal && $effectiveFulfilment === 'workshop'
            ? ['passenger']
            : ['passenger', 'large_vehicle', 'six_wheels'];
        $supportsAdditionalServices = in_array($slug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
        $requiresProblem = in_array($slug, ['tpms-sensor', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
        $detailsLocked = filled($requestModel?->booking_slot_key);
        $storedBookingMode = $requestModel?->booking_slot_key
            ? 'exact'
            : ($requestModel?->preferred_period_starts_at && $requestModel?->preferred_period_ends_at ? 'period' : 'manual');
        $bookingMode = (string) $this->input('booking_mode', $storedBookingMode);
        $operationalUpdate = $isCreate || $this->has('service_slug');
        $storedDate = $requestModel?->preferred_date?->format('Y-m-d');
        $dateChanged = $isCreate || ($this->has('preferred_date') && (string) $this->input('preferred_date') !== (string) $storedDate);
        $dateRequired = in_array($bookingMode, ['period', 'exact'], true)
            || ($requiresRequestedDate && ($isCreate || $serviceChanged || filled($storedDate)));
        $structuredModeSubmitted = $this->has('booking_mode') && in_array($bookingMode, ['period', 'exact'], true);
        $datePresence = $detailsLocked ? ['sometimes'] : ($structuredModeSubmitted ? [] : $serviceContractPresence);
        $generalStorageActive = in_array($generalPlan, ['new', 'renew', 'last'], true)
            || ($generalPlan === 'unknown'
                && ((bool) $requestModel?->dekkhotell || $requestModel?->dekkhotell_already_stored === true));
        $generalRegistrationRequired = ($isBattery && ($isCreate || $serviceChanged || $this->has('registration_number')))
            || ($supportsGeneralStorage && $generalStorageActive
                && ($generalPlanChanged || $this->has('registration_number')));
        $generalRegistrationPresence = $generalRegistrationRequired ? [] : $serviceContractPresence;
        $serviceExists = Rule::exists('service_pages', 'slug');
        if ($serviceChanged) {
            $serviceExists->where('publication_status', ServicePagePublicationStatus::Published->value);
        }

        return [
            'operational_form' => ['nullable', 'boolean'],
            'submission_token' => [$isCreate ? 'required' : 'nullable', 'uuid'],
            'client_id' => ['sometimes', 'nullable', 'integer', Rule::exists('clients', 'id')],
            'service_slug' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), 'string', $serviceExists],
            'service_variant' => [...$serviceContractPresence, Rule::requiredIf($isMounting), 'nullable', 'string', Rule::in(['mounting_balancing', 'balancing_only'])],
            'fulfilment' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), Rule::in($workshopOnly ? ['workshop'] : ['workshop', 'mobile'])],
            'booking_mode' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), Rule::in(['manual', 'period', 'exact'])],
            'preferred_date' => [
                ...$datePresence,
                Rule::requiredIf($dateRequired),
                'nullable',
                'date',
                ...($dateChanged ? ['after_or_equal:today'] : []),
            ],
            'preferred_window' => [$detailsLocked ? 'sometimes' : 'nullable', 'string', 'max:64'],
            'preferred_time_start' => [
                ...($operationalUpdate ? [] : ['sometimes']),
                Rule::requiredIf($bookingMode === 'period' && $operationalUpdate),
                'nullable', 'date_format:H:i',
            ],
            'preferred_time_end' => [
                ...($operationalUpdate ? [] : ['sometimes']),
                Rule::requiredIf($bookingMode === 'period' && $operationalUpdate),
                'nullable', 'date_format:H:i', 'after:preferred_time_start',
            ],
            'booking_slot_key' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($bookingMode === 'exact' && $effectiveCarCount <= 2), 'nullable', 'string', 'max:80'],
            'vehicle_payload_conflict' => ['prohibited'],
            'storage_payload_conflict' => ['prohibited'],
            'legacy_storage_clear' => ['nullable', 'boolean'],
            'car_count' => [...$serviceContractPresence, Rule::requiredIf($isSeasonal && $operationalUpdate), 'nullable', 'integer', 'between:1,'.ServiceRequestVehiclePayload::MAX_VEHICLES],
            'vehicles' => [...$serviceContractPresence, Rule::requiredIf($isSeasonal && $operationalUpdate), Rule::prohibitedIf(! $isSeasonal), 'nullable', 'array', 'min:1', 'max:'.ServiceRequestVehiclePayload::MAX_VEHICLES],
            'vehicles.*' => ['array:vehicle,registration_number,tire_size,vehicle_type,rim_band,storage_plan,storage_number'],
            'vehicles.*.vehicle' => ['required', 'string', 'max:120'],
            'vehicles.*.registration_number' => ['nullable', 'string', 'max:32'],
            'vehicles.*.tire_size' => ['nullable', 'string', 'max:64'],
            'vehicles.*.vehicle_type' => ['required', Rule::in($seasonalVehicleTypes)],
            'vehicles.*.rim_band' => ['required', Rule::in(['unknown', 'r15', 'r16-r18', 'r19'])],
            'vehicles.*.storage_plan' => ['required', Rule::in(ServiceRequestVehiclePayload::STORAGE_PLANS)],
            'vehicles.*.storage_number' => ['nullable', 'string', 'max:64', 'regex:/^[\pL\pN][\pL\pN ._\/-]*$/u'],
            'vehicle' => [...$serviceContractPresence, Rule::prohibitedIf($isSeasonal), 'nullable', 'string', 'max:120'],
            'registration_number' => [...$generalRegistrationPresence, Rule::requiredIf($generalRegistrationRequired), Rule::prohibitedIf($isSeasonal), 'nullable', 'string', 'max:32'],
            'tire_size' => [...$serviceContractPresence, Rule::requiredIf($isMounting), 'nullable', 'string', 'max:64'],
            'storage_plan' => [Rule::prohibitedIf($isSeasonal || ! $supportsGeneralStorage), 'sometimes', 'string', Rule::in($generalPlans)],
            'storage_number' => [Rule::prohibitedIf($isSeasonal || ! $supportsGeneralStorage), 'nullable', 'string', 'max:64', 'regex:/^[\pL\pN][\pL\pN ._\/-]*$/u'],
            'wheel_count' => [...$serviceContractPresence, Rule::requiredIf($isMounting), 'nullable', 'integer', 'between:1,4'],
            'dekkhotell' => ['prohibited'],
            'dekkhotell_control' => ['prohibited'],
            'dekkhotell_already_stored' => ['prohibited'],
            'assigned_van' => [Rule::prohibitedIf($effectiveFulfilment !== 'mobile'), 'sometimes', 'nullable', Rule::in(['van_1', 'van_2'])],
            'additional_services' => [Rule::prohibitedIf(! $supportsAdditionalServices), 'nullable', 'array', 'max:3'],
            'additional_services.*' => ['string', 'distinct', Rule::in(['air_filter', 'cabin_filter', 'wiper_blades'])],
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:24', new CustomerPhone],
            'address' => [
                ...$addressPresence,
                Rule::requiredIf($effectiveFulfilment === 'mobile'),
                'nullable',
                'string',
                'max:255',
            ],
            'district' => ['nullable', 'string', 'max:120'],
            'note' => [($requiresProblem && $serviceChanged) ? 'required' : 'nullable', 'string', 'max:2000'],
            'admin_note' => ['nullable', 'string', 'max:4000'],
            'status' => $isCreate
                ? ['prohibited']
                : ['sometimes', Rule::in(['new', 'contacted', 'closed', 'cancelled'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $isCreate = $this->routeIs('admin.service-requests.store');
            $requestModel = $this->route('request');
            $requestModel = $requestModel instanceof ServiceRequest ? $requestModel : null;
            $slug = mb_strtolower((string) $this->input('service_slug', $requestModel?->service_slug));
            $serviceChanged = $isCreate || ($this->has('service_slug') && $slug !== mb_strtolower((string) $requestModel?->service_slug));
            $storedVehicles = is_array($requestModel?->vehicles) ? $requestModel->vehicles : [];
            $vehicles = is_array($this->input('vehicles')) ? array_values($this->input('vehicles')) : [];

            foreach ($vehicles as $index => $vehicle) {
                if (! is_array($vehicle)) {
                    continue;
                }
                $plan = (string) ($vehicle['storage_plan'] ?? 'none');
                $stored = is_array($storedVehicles[$index] ?? null) ? $storedVehicles[$index] : [];
                $planChanged = $plan !== (string) ($stored['storage_plan'] ?? 'none');
                $registrationRequired = $plan !== 'none'
                    && ($isCreate || $serviceChanged || $planChanged || filled($stored['registration_number'] ?? null));
                if ($registrationRequired && blank($vehicle['registration_number'] ?? null)) {
                    $validator->errors()->add("vehicles.{$index}.registration_number", __('service_requests.validation.registration_required'));
                }
            }

            if ($this->usedLegacyVehiclePayload) {
                foreach ([
                    0 => [
                        'vehicle' => 'vehicle', 'registration_number' => 'registration_number',
                        'tire_size' => 'tire_size', 'vehicle_type' => 'vehicle_type',
                        'rim_band' => 'rim_band', 'storage_plan' => 'storage_plan',
                    ],
                    1 => [
                        'vehicle' => 'vehicle_2', 'registration_number' => 'registration_number_2',
                        'tire_size' => 'tire_size_2', 'vehicle_type' => 'vehicle_type_2',
                        'rim_band' => 'rim_band_2', 'storage_plan' => 'storage_plan_2',
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

            if ($this->legacyGeneralStorageMissingAlreadyStored) {
                $validator->errors()->add(
                    'dekkhotell_already_stored',
                    __('service_requests.validation.storage_already_required'),
                );
            }

            if (($this->input('booking_mode') ?? null) === 'exact' && count($vehicles) > 2) {
                $validator->errors()->add('booking_mode', __('service_requests.validation.exact_vehicle_limit'));
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'vehicle_payload_conflict.prohibited' => __('service_requests.validation.vehicle_payload_conflict'),
            'storage_payload_conflict.prohibited' => __('service_requests.validation.storage_payload_conflict'),
            'vehicles.*.storage_number.regex' => __('service_requests.validation.storage_number_format'),
            'storage_number.regex' => __('service_requests.validation.storage_number_format'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $isCreate = $this->routeIs('admin.service-requests.store');
        $requestModel = $this->route('request');
        $requestModel = $requestModel instanceof ServiceRequest ? $requestModel : null;
        $slug = mb_strtolower((string) $this->input('service_slug', $requestModel?->service_slug));
        $serviceChanged = $isCreate || ($this->has('service_slug') && $slug !== mb_strtolower((string) $requestModel?->service_slug));
        if ($this->has('assigned_van') && ! filled($this->input('assigned_van'))) {
            $this->merge(['assigned_van' => null]);
        }
        if ($this->boolean('operational_form')) {
            if (in_array($slug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true)) {
                $this->merge(['additional_services' => $this->input('additional_services', [])]);
            }
        }

        $operationalUpdate = $isCreate || $this->has('service_slug');
        if (! $operationalUpdate) {
            return;
        }

        $input = $this->all();
        unset($input['legacy_storage_clear']);
        if ($this->isSeasonal($slug)) {
            $conflict = ServiceRequestVehiclePayload::hasConflictingPayload($input);
            $hasCanonicalVehicles = is_array($input['vehicles'] ?? null);
            $hasLegacyVehicles = ServiceRequestVehiclePayload::hasLegacyFields($input);
            $vehicles = $hasCanonicalVehicles
                ? array_values($input['vehicles'])
                : ($hasLegacyVehicles ? $this->legacyVehiclesForRequest($input, $requestModel, $serviceChanged) : []);
            $this->usedLegacyVehiclePayload = ! $hasCanonicalVehicles && $hasLegacyVehicles;
            if ($vehicles === [] && ($isCreate || $serviceChanged)) {
                $vehicles = [[
                    'vehicle' => null, 'registration_number' => null, 'tire_size' => null,
                    'vehicle_type' => 'passenger', 'rim_band' => 'unknown', 'storage_plan' => 'none', 'storage_number' => null,
                ]];
            }
            if ($hasCanonicalVehicles || $hasLegacyVehicles || $isCreate || $serviceChanged) {
                foreach (['vehicle', 'registration_number', 'tire_size', 'vehicle_type', 'rim_band', 'storage_plan', 'vehicle_2', 'registration_number_2', 'tire_size_2', 'vehicle_type_2', 'rim_band_2', 'storage_plan_2'] as $legacyField) {
                    unset($input[$legacyField]);
                }
                $input['vehicles'] = $vehicles;
                $input['car_count'] = count($vehicles);
            }
            if ($conflict) {
                $input['vehicle_payload_conflict'] = true;
            }
        } elseif ($this->supportsGeneralStorage($slug)) {
            $legacyStorageSubmitted = array_key_exists('dekkhotell', $input)
                || array_key_exists('dekkhotell_control', $input)
                || array_key_exists('dekkhotell_already_stored', $input);
            if (array_key_exists('storage_plan', $input) && $legacyStorageSubmitted) {
                $input['storage_payload_conflict'] = true;
            } elseif (! array_key_exists('storage_plan', $input) && $legacyStorageSubmitted) {
                $futureStorage = array_key_exists('dekkhotell_control', $input)
                    ? filter_var($input['dekkhotell'] ?? false, FILTER_VALIDATE_BOOL)
                    : filter_var($input['dekkhotell'] ?? $requestModel?->dekkhotell, FILTER_VALIDATE_BOOL);
                $alreadyStored = array_key_exists('dekkhotell_already_stored', $input)
                    ? ($input['dekkhotell_already_stored'] === null || $input['dekkhotell_already_stored'] === ''
                        ? null
                        : filter_var($input['dekkhotell_already_stored'], FILTER_VALIDATE_BOOL))
                    : $requestModel?->dekkhotell_already_stored;
                if ($futureStorage && ! array_key_exists('dekkhotell_already_stored', $input)) {
                    $this->legacyGeneralStorageMissingAlreadyStored = true;
                    $input['storage_plan'] = 'new';
                } elseif (! $futureStorage) {
                    // The legacy checkbox contract meant “no new hotel period”; it
                    // did not assert whether a historical set existed.
                    $input['storage_plan'] = 'none';
                    $input['legacy_storage_clear'] = true;
                } else {
                    $input['storage_plan'] = ServiceRequestVehiclePayload::generalPlan($futureStorage, $alreadyStored);
                }
            }
            unset($input['dekkhotell'], $input['dekkhotell_control'], $input['dekkhotell_already_stored']);
        }

        $this->replace($input);
    }

    private function isSeasonal(string $slug): bool
    {
        $flow = ServicePage::query()->where('slug', $slug)->value('request_flow');
        $flow = $flow instanceof ServiceRequestFlow ? $flow->value : $flow;

        return $flow === ServiceRequestFlow::Seasonal->value;
    }

    private function supportsGeneralStorage(string $slug): bool
    {
        return in_array($slug, ['omlegging-balansering', 'dekkreparasjon', 'tpms-sensor'], true);
    }

    /**
     * Adapt the historical one/two-car admin form without losing omitted fields
     * on an existing request. Canonical `vehicles` submissions are never merged.
     *
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function legacyVehiclesForRequest(array $input, ?ServiceRequest $request, bool $serviceChanged): array
    {
        $stored = ! $serviceChanged && is_array($request?->vehicles) ? array_values($request->vehicles) : [];
        $count = array_key_exists('car_count', $input)
            ? (int) $input['car_count']
            : ($stored !== [] ? count($stored) : 1);
        if ($count < 1 || $count > ServiceRequestVehiclePayload::MAX_VEHICLES) {
            return [];
        }

        $fields = ['vehicle', 'registration_number', 'tire_size', 'vehicle_type', 'rim_band', 'storage_plan'];
        $vehicles = [];
        for ($index = 0; $index < $count; $index++) {
            $vehicle = is_array($stored[$index] ?? null) ? $stored[$index] : [];
            $suffix = $index === 0 ? '' : '_'.($index + 1);
            foreach ($fields as $field) {
                $legacyField = $field.$suffix;
                if (array_key_exists($legacyField, $input)) {
                    $vehicle[$field] = $input[$legacyField];
                } elseif (! array_key_exists($field, $vehicle)) {
                    $vehicle[$field] = null;
                }
            }
            $vehicles[] = $vehicle;
        }

        return $vehicles;
    }
}
