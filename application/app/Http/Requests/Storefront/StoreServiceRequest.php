<?php

namespace App\Http\Requests\Storefront;

use App\Modules\Content\Enums\ServiceRequestFlow;
use App\Modules\Content\Models\ServicePage;
use App\Rules\CustomerPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function rules(): array
    {
        $slug = mb_strtolower((string) $this->route('slug'));
        $isSeasonal = $this->isSeasonal($slug);
        $isMounting = $slug === 'omlegging-balansering';
        $isBattery = $slug === 'bilbatteri';
        $workshopOnly = in_array($slug, ['omlegging-balansering', 'tpms-sensor'], true);
        $secondVehicle = $isSeasonal && (int) $this->input('car_count', 1) === 2;
        $seasonalVehicleTypes = $isSeasonal && $this->input('fulfilment') === 'workshop'
            ? ['passenger']
            : ['passenger', 'large_vehicle', 'six_wheels'];
        $requiresProblem = in_array($slug, ['tpms-sensor', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);
        $supportsAdditionalServices = in_array($slug, ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'], true);

        return [
            'submission_token' => ['required', 'uuid'],
            'service_variant' => [Rule::requiredIf($isMounting), 'nullable', 'string', Rule::in(['mounting_balancing', 'balancing_only'])],
            'fulfilment' => ['required', Rule::in($workshopOnly ? ['workshop'] : ['workshop', 'mobile'])],
            'preferred_date' => [Rule::requiredIf($isSeasonal || $isMounting || $isBattery), 'nullable', 'date', 'after_or_equal:today'],
            'preferred_window' => ['nullable', 'string', 'max:64'],
            'booking_slot_key' => ['nullable', 'string', 'max:80'],
            'car_count' => [Rule::requiredIf($isSeasonal), 'nullable', 'integer', Rule::in([1, 2])],
            'vehicle' => [Rule::requiredIf($isSeasonal), 'nullable', 'string', 'max:120'],
            'registration_number' => [Rule::requiredIf($isBattery), 'nullable', 'string', 'max:32'],
            'tire_size' => [Rule::requiredIf($isMounting), 'nullable', 'string', 'max:64'],
            'vehicle_type' => [Rule::requiredIf($isSeasonal), 'nullable', Rule::in($seasonalVehicleTypes)],
            'rim_band' => [Rule::requiredIf($isSeasonal), 'nullable', Rule::in(['unknown', 'r15', 'r16-r18', 'r19'])],
            'storage_plan' => [Rule::requiredIf($isSeasonal), 'nullable', Rule::in(['none', 'new', 'renew'])],
            'vehicle_2' => [Rule::requiredIf($secondVehicle), 'nullable', 'string', 'max:120'],
            'registration_number_2' => ['nullable', 'string', 'max:32'],
            'tire_size_2' => ['nullable', 'string', 'max:64'],
            'vehicle_type_2' => [Rule::requiredIf($secondVehicle), 'nullable', Rule::in($seasonalVehicleTypes)],
            'rim_band_2' => [Rule::requiredIf($secondVehicle), 'nullable', Rule::in(['unknown', 'r15', 'r16-r18', 'r19'])],
            'storage_plan_2' => [Rule::requiredIf($secondVehicle), 'nullable', Rule::in(['none', 'new', 'renew'])],
            'wheel_count' => [Rule::requiredIf($isMounting), 'nullable', 'integer', 'between:1,4'],
            'dekkhotell' => ['nullable', 'boolean'],
            'additional_services' => [Rule::prohibitedIf(! $supportsAdditionalServices), 'nullable', 'array', 'max:3'],
            'additional_services.*' => ['string', 'distinct', Rule::in(['air_filter', 'cabin_filter', 'wiper_blades'])],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:24', new CustomerPhone],
            'address' => ['nullable', 'required_if:fulfilment,mobile', 'string', 'max:255'],
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
            'preferred_date.date' => __('service_requests.validation.preferred_date_invalid'),
            'preferred_date.after_or_equal' => __('service_requests.validation.preferred_date_not_past'),
        ];
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

        $this->merge([
            'car_count' => $this->input('car_count', 1),
            'vehicle_type' => $this->input('vehicle_type', 'passenger'),
            'rim_band' => $this->input('rim_band', 'unknown'),
            'storage_plan' => $this->input('storage_plan', $this->boolean('dekkhotell') ? 'new' : 'none'),
        ]);
    }

    private function isSeasonal(string $slug): bool
    {
        $flow = ServicePage::query()->where('slug', $slug)->value('request_flow');
        $flow = $flow instanceof ServiceRequestFlow ? $flow->value : $flow;

        return $flow === ServiceRequestFlow::Seasonal->value
            || in_array($slug, ['mobilt-dekkskift', 'dekkverksted', 'dekkskift', 'dekkskift-tromso', 'hjulskift', 'sesongskift'], true);
    }
}
