<?php

namespace App\Http\Requests\Admin;

use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
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
        $slug = mb_strtolower((string) $this->input('service_slug'));
        $isSeasonal = $this->isSeasonal($slug);
        $isMounting = $slug === 'omlegging-balansering';
        $isBattery = $slug === 'bilbatteri';
        $workshopOnly = in_array($slug, ['omlegging-balansering', 'tpms-sensor'], true);
        $secondVehicle = $isSeasonal && (int) $this->input('car_count', 1) === 2;
        $seasonalVehicleTypes = $isSeasonal && $this->input('fulfilment') === 'workshop'
            ? ['passenger']
            : ['passenger', 'large_vehicle', 'six_wheels'];
        $supportsAdditionalServices = in_array($slug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
        $isCreate = $this->routeIs('admin.service-requests.store');
        $requestModel = $this->route('request');
        $detailsLocked = is_object($requestModel) && filled($requestModel->booking_slot_key ?? null);
        $bookingMode = (string) $this->input('booking_mode', 'manual');
        $operationalUpdate = $isCreate || $this->has('service_slug');

        return [
            'submission_token' => [$isCreate ? 'required' : 'nullable', 'uuid'],
            'service_slug' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), 'string', Rule::exists('service_pages', 'slug')],
            'service_variant' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isMounting), 'nullable', 'string', Rule::in(['mounting_balancing', 'balancing_only'])],
            'fulfilment' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), Rule::in($workshopOnly ? ['workshop'] : ['workshop', 'mobile'])],
            'booking_mode' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($operationalUpdate), Rule::in(['manual', 'exact'])],
            'preferred_date' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($bookingMode === 'exact'), 'nullable', 'date', 'after_or_equal:today'],
            'preferred_window' => [$detailsLocked ? 'sometimes' : 'nullable', 'string', 'max:64'],
            'booking_slot_key' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($bookingMode === 'exact'), 'nullable', 'string', 'max:80'],
            'car_count' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isSeasonal), 'nullable', 'integer', Rule::in([1, 2])],
            'vehicle' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isSeasonal), 'nullable', 'string', 'max:120'],
            'registration_number' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isBattery), 'nullable', 'string', 'max:32'],
            'tire_size' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isMounting), 'nullable', 'string', 'max:64'],
            'vehicle_type' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isSeasonal), 'nullable', Rule::in($seasonalVehicleTypes)],
            'rim_band' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isSeasonal), 'nullable', Rule::in(['unknown', 'r15', 'r16-r18', 'r19'])],
            'storage_plan' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isSeasonal), 'nullable', Rule::in(['none', 'new', 'renew'])],
            'vehicle_2' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($secondVehicle), 'nullable', 'string', 'max:120'],
            'registration_number_2' => ['nullable', 'string', 'max:32'],
            'tire_size_2' => ['nullable', 'string', 'max:64'],
            'vehicle_type_2' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($secondVehicle), 'nullable', Rule::in($seasonalVehicleTypes)],
            'rim_band_2' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($secondVehicle), 'nullable', Rule::in(['unknown', 'r15', 'r16-r18', 'r19'])],
            'storage_plan_2' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($secondVehicle), 'nullable', Rule::in(['none', 'new', 'renew'])],
            'wheel_count' => [$detailsLocked ? 'sometimes' : Rule::requiredIf($isMounting), 'nullable', 'integer', 'between:1,4'],
            'dekkhotell' => ['nullable', 'boolean'],
            'additional_services' => [Rule::prohibitedIf(! $supportsAdditionalServices), 'nullable', 'array', 'max:3'],
            'additional_services.*' => ['string', 'distinct', Rule::in(['air_filter', 'cabin_filter', 'wiper_blades'])],
            'name' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [$isCreate ? 'required' : 'sometimes', 'string', 'max:24', new CustomerPhone],
            'address' => ['nullable', 'required_if:fulfilment,mobile', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'admin_note' => ['nullable', 'string', 'max:4000'],
            'status' => ['required', Rule::in(['new', 'contacted', 'closed', 'cancelled'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = mb_strtolower((string) $this->input('service_slug'));
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
