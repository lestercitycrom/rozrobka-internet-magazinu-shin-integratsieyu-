<?php

namespace App\Http\Requests\Admin;

use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Rules\CustomerPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpsertServiceRequest extends FormRequest
{
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
        $isSeasonal = $this->isSeasonal($slug);
        $isMounting = $slug === 'omlegging-balansering';
        $isBattery = $slug === 'bilbatteri';
        $requiresRequestedDate = $isSeasonal || $isMounting || $isBattery;
        $workshopOnly = in_array($slug, ['omlegging-balansering', 'tpms-sensor'], true);
        $secondVehicle = $isSeasonal && (int) $this->input('car_count', 1) === 2;
        $seasonalVehicleTypes = $isSeasonal && $effectiveFulfilment === 'workshop'
            ? ['passenger']
            : ['passenger', 'large_vehicle', 'six_wheels'];
        $supportsAdditionalServices = in_array($slug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
        $requiresProblem = in_array($slug, ['tpms-sensor', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
        $detailsLocked = filled($requestModel?->booking_slot_key);
        $bookingMode = (string) $this->input('booking_mode', $requestModel?->booking_slot_key ? 'exact' : 'manual');
        $operationalUpdate = $isCreate || $this->has('service_slug');
        $storedDate = $requestModel?->preferred_date?->format('Y-m-d');
        $dateChanged = $isCreate || ($this->has('preferred_date') && (string) $this->input('preferred_date') !== (string) $storedDate);
        $dateRequired = $bookingMode === 'exact'
            || ($requiresRequestedDate && ($isCreate || $serviceChanged || filled($storedDate)));
        $serviceExists = Rule::exists('service_pages', 'slug');
        if ($serviceChanged) {
            $serviceExists->where('publication_status', ServicePagePublicationStatus::Published->value);
        }

        return [
            'operational_form' => ['nullable', 'boolean'],
            'submission_token' => [$isCreate ? 'required' : 'nullable', 'uuid'],
            'service_slug' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), 'string', $serviceExists],
            'service_variant' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isMounting), 'nullable', 'string', Rule::in(['mounting_balancing', 'balancing_only'])],
            'fulfilment' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), Rule::in($workshopOnly ? ['workshop'] : ['workshop', 'mobile'])],
            'booking_mode' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), Rule::in(['manual', 'exact'])],
            'preferred_date' => [
                ...($isCreate ? [] : ['sometimes']),
                Rule::requiredIf($dateRequired),
                'nullable',
                'date',
                ...($dateChanged ? ['after_or_equal:today'] : []),
            ],
            'preferred_window' => [$detailsLocked ? 'sometimes' : 'nullable', 'string', 'max:64'],
            'booking_slot_key' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($bookingMode === 'exact'), 'nullable', 'string', 'max:80'],
            'car_count' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isSeasonal), 'nullable', 'integer', Rule::in([1, 2])],
            'vehicle' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isSeasonal), 'nullable', 'string', 'max:120'],
            'registration_number' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isBattery), 'nullable', 'string', 'max:32'],
            'tire_size' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isMounting), 'nullable', 'string', 'max:64'],
            'vehicle_type' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isSeasonal), 'nullable', Rule::in($seasonalVehicleTypes)],
            'rim_band' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isSeasonal), 'nullable', Rule::in(['unknown', 'r15', 'r16-r18', 'r19'])],
            'storage_plan' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isSeasonal), 'nullable', Rule::in(['none', 'new', 'renew'])],
            'vehicle_2' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($secondVehicle), 'nullable', 'string', 'max:120'],
            'registration_number_2' => ['nullable', 'string', 'max:32'],
            'tire_size_2' => ['nullable', 'string', 'max:64'],
            'vehicle_type_2' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($secondVehicle), 'nullable', Rule::in($seasonalVehicleTypes)],
            'rim_band_2' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($secondVehicle), 'nullable', Rule::in(['unknown', 'r15', 'r16-r18', 'r19'])],
            'storage_plan_2' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($secondVehicle), 'nullable', Rule::in(['none', 'new', 'renew'])],
            'wheel_count' => [...($isCreate ? [] : ['sometimes']), Rule::requiredIf($isMounting), 'nullable', 'integer', 'between:1,4'],
            'dekkhotell' => ['nullable', 'boolean'],
            'additional_services' => [Rule::prohibitedIf(! $supportsAdditionalServices), 'nullable', 'array', 'max:3'],
            'additional_services.*' => ['string', 'distinct', Rule::in(['air_filter', 'cabin_filter', 'wiper_blades'])],
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:24', new CustomerPhone],
            'address' => [
                ...($isCreate ? [] : ['sometimes']),
                Rule::requiredIf($effectiveFulfilment === 'mobile'),
                'nullable',
                'string',
                'max:255',
            ],
            'note' => [($requiresProblem && $serviceChanged) ? 'required' : 'nullable', 'string', 'max:2000'],
            'admin_note' => ['nullable', 'string', 'max:4000'],
            'status' => $isCreate
                ? ['prohibited']
                : ['sometimes', Rule::in(['new', 'contacted', 'closed', 'cancelled'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = mb_strtolower((string) $this->input('service_slug'));
        if ($this->boolean('operational_form')) {
            $this->merge(['dekkhotell' => $this->boolean('dekkhotell')]);
            if (in_array($slug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true)) {
                $this->merge(['additional_services' => $this->input('additional_services', [])]);
            }
        }

        if (! $this->isSeasonal($slug)) {
            return;
        }

        $this->merge([
            'car_count' => $this->input('car_count', 1),
            'vehicle_type' => $this->input('vehicle_type', 'passenger'),
            'rim_band' => $this->input('rim_band', 'unknown'),
            'storage_plan' => $this->input('storage_plan', 'none'),
        ]);
    }

    private function isSeasonal(string $slug): bool
    {
        $flow = ServicePage::query()->where('slug', $slug)->value('request_flow');
        $flow = $flow instanceof ServiceRequestFlow ? $flow->value : $flow;

        return $flow === ServiceRequestFlow::Seasonal->value;
    }
}
