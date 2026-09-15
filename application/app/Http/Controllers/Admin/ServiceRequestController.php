<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertServiceRequest;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\CustomerPhoneNormalizer;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\Client;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\SeasonalQuoteCalculator;
use App\Modules\ServiceBooking\ServiceRequestCreator;
use App\Modules\ServiceBooking\ServiceRequestMapLink;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function index(Request $input, CustomerPhoneNormalizer $phones): View
    {
        $filters = $input->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
                ...($input->filled('date_to') ? ['before_or_equal:date_to'] : []),
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                ...($input->filled('date_from') ? ['after_or_equal:date_from'] : []),
            ],
            'service' => ['nullable', 'string', Rule::exists('service_pages', 'slug')],
            'fulfilment' => ['nullable', Rule::in(['workshop', 'mobile'])],
            'status' => ['nullable', Rule::in(['new', 'contacted', 'closed', 'cancelled'])],
            'district' => ['nullable', 'string', 'max:120'],
            'dekkhotell' => ['nullable', 'boolean'],
            'assigned_van' => ['nullable', Rule::in(['unassigned', 'van_1', 'van_2'])],
            'view' => ['nullable', Rule::in(['all', 'new', 'workshop', 'mobile', 'additional'])],
            'new_only' => ['nullable', 'boolean'],
            'without_date' => ['nullable', 'boolean'],
        ]);

        $withoutDate = $input->boolean('without_date');

        $query = ServiceRequest::query()->with(['createdBy', 'client'])->latest();
        if (filled($filters['q'] ?? null)) {
            $needle = trim((string) $filters['q']);
            $phoneNeedle = $phones->searchTerm($needle);
            $query->where(function (Builder $query) use ($needle, $phoneNeedle): void {
                $query->where('reference', 'like', "%{$needle}%")
                    ->orWhere('name', 'like', "%{$needle}%")
                    ->orWhere('email', 'like', "%{$needle}%");
                if ($phoneNeedle !== null) {
                    $query->orWhere('phone_normalized', 'like', "%{$phoneNeedle}%");
                }
            });
        }
        $query
            ->when($withoutDate, fn (Builder $query) => $query->whereNull('preferred_date'))
            ->when(! $withoutDate && filled($filters['date'] ?? null), fn (Builder $query) => $query->whereDate('preferred_date', $filters['date']))
            ->when(! $withoutDate && filled($filters['date_from'] ?? null), fn (Builder $query) => $query->whereDate('preferred_date', '>=', $filters['date_from']))
            ->when(! $withoutDate && filled($filters['date_to'] ?? null), fn (Builder $query) => $query->whereDate('preferred_date', '<=', $filters['date_to']))
            ->when(filled($filters['service'] ?? null), fn (Builder $query) => $query->where('service_slug', $filters['service']))
            ->when(filled($filters['fulfilment'] ?? null), fn (Builder $query) => $query->where('fulfilment', $filters['fulfilment']))
            ->when(filled($filters['status'] ?? null), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(filled($filters['district'] ?? null), fn (Builder $query) => $query->where('district', 'like', '%'.trim((string) $filters['district']).'%'))
            ->when(isset($filters['dekkhotell']), fn (Builder $query) => $query->where('dekkhotell', $input->boolean('dekkhotell')))
            ->when(filled($filters['assigned_van'] ?? null), function (Builder $query) use ($filters): void {
                $query->where('fulfilment', 'mobile');
                if ($filters['assigned_van'] === 'unassigned') {
                    $query->whereNull('assigned_van');

                    return;
                }
                $query->where('assigned_van', $filters['assigned_van']);
            })
            ->when($input->boolean('new_only'), fn (Builder $query) => $query->where('status', ServiceRequestStatus::New));

        match ($filters['view'] ?? 'all') {
            'new' => $query->where('status', ServiceRequestStatus::New),
            'workshop' => $query->where('fulfilment', 'workshop'),
            'mobile' => $query->where('fulfilment', 'mobile'),
            'additional' => $query->where(function (Builder $query): void {
                $query->whereIn('service_slug', ['oljeskift', 'bilbatteri', 'bildiagnose', 'lyspaerer-viskerblad'])
                    ->orWhereJsonLength('additional_services', '>', 0);
            }),
            default => null,
        };

        $requests = $query->paginate(25)->withQueryString();

        return view('admin.requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'serviceOptions' => $this->services(includeUnpublished: true),
            'serviceTitles' => $this->serviceTitles($requests->pluck('service_slug')->all()),
            'activeView' => $filters['view'] ?? 'all',
        ]);
    }

    public function create(Request $input, SeasonalQuoteCalculator $quotes): View
    {
        $returnTo = $this->safeDayReturnTo($input);
        $client = $input->filled('client')
            ? Client::query()->findOrFail($input->integer('client'))
            : null;
        $request = new ServiceRequest([
            'locale' => app()->getLocale(),
            'source' => ServiceRequestSource::AdminManual,
            'status' => ServiceRequestStatus::New,
            'fulfilment' => 'workshop',
            'client_id' => $client?->getKey(),
            'name' => $client?->name,
            'phone' => $client?->phone,
            'email' => $client?->email,
            'address' => $client?->address,
            'district' => $client?->district,
        ]);

        return view('admin.requests.form', $this->formData($request, $quotes) + [
            'submissionToken' => old('submission_token', (string) Str::uuid()),
            'returnTo' => $returnTo,
        ]);
    }

    public function store(UpsertServiceRequest $input, ServiceRequestCreator $creator): RedirectResponse
    {
        $request = $creator->createManual($input->validated(), $input->user(), app()->getLocale());

        return redirect()->route('admin.service-requests.show', $this->requestRouteParameters(
            $request,
            $this->safeDayReturnTo($input),
        ))
            ->with('status', __('service_requests.created'));
    }

    public function show(ServiceRequest $request, ServiceRequestMapLink $maps, Request $input): View
    {
        $request->load(['createdBy', 'client']);

        return view('admin.requests.show', [
            'request' => $request,
            'returnTo' => $this->safeDayReturnTo($input),
            'mapUrl' => $maps->forAddress($request->address),
            'serviceTitle' => $this->serviceTitles([$request->service_slug])[$request->service_slug] ?? $request->service_slug,
        ]);
    }

    public function edit(ServiceRequest $request, SeasonalQuoteCalculator $quotes, Request $input): View
    {
        $request->load(['createdBy', 'client']);

        return view('admin.requests.form', $this->formData($request, $quotes) + [
            'returnTo' => $this->safeDayReturnTo($input),
        ]);
    }

    public function update(UpsertServiceRequest $input, ServiceRequest $request, ServiceRequestCreator $creator): RedirectResponse
    {
        $creator->updateManual($request, $input->validated());

        return redirect()->route('admin.service-requests.show', $this->requestRouteParameters(
            $request,
            $this->safeDayReturnTo($input),
        ))
            ->with('status', __('service_requests.saved'));
    }

    /** @return array<string, mixed> */
    private function formData(ServiceRequest $request, SeasonalQuoteCalculator $quotes): array
    {
        $selectedClientId = old('client_id', $request->client_id);
        $selectedClient = filled($selectedClientId) ? Client::query()->find($selectedClientId) : null;
        $clientOptions = $selectedClient === null ? collect() : collect([$selectedClient]);

        return [
            'request' => $request,
            'serviceOptions' => $this->services($request->service_slug),
            'seasonalPrices' => $quotes->publicMatrix(),
            'detailsLocked' => filled($request->booking_slot_key),
            'clientOptions' => $clientOptions,
        ];
    }

    /** @return Collection<int, ServicePage> */
    private function services(?string $includeSlug = null, bool $includeUnpublished = false): Collection
    {
        return ServicePage::query()
            ->when(! $includeUnpublished, function (Builder $query) use ($includeSlug): void {
                $query->where(function (Builder $query) use ($includeSlug): void {
                    $query->where('publication_status', ServicePagePublicationStatus::Published->value)
                        ->when(filled($includeSlug), fn (Builder $query) => $query->orWhere('slug', $includeSlug));
                });
            })
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();
    }

    /** @param list<string> $slugs @return array<string, string> */
    private function serviceTitles(array $slugs): array
    {
        $locale = app()->getLocale();

        return ServicePage::query()
            ->whereIn('slug', array_unique($slugs))
            ->get(['slug', 'title_nb', 'title_ru'])
            ->mapWithKeys(fn (ServicePage $page): array => [
                $page->slug => $locale === 'ru' && filled($page->title_ru) ? $page->title_ru : $page->title_nb,
            ])
            ->all();
    }

    private function safeDayReturnTo(Request $input): ?string
    {
        $candidate = $input->query('return_to');
        if (! is_string($candidate) || $candidate === '' || str_contains($candidate, '\\')) {
            return null;
        }

        $parts = parse_url($candidate);
        $expectedPath = route('admin.service-requests.day', [], false);
        if ($parts === false
            || ($parts['path'] ?? null) !== $expectedPath
            || array_intersect_key($parts, array_flip(['scheme', 'host', 'port', 'user', 'pass', 'fragment'])) !== []) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        if (array_diff(array_keys($query), ['date', 'resource', 'include_history']) !== []) {
            return null;
        }

        $validator = Validator::make($query, [
            'date' => ['required', 'date_format:Y-m-d'],
            'resource' => ['required', Rule::in(['all', 'workshop', 'van_1', 'van_2', 'mobile_unassigned'])],
            'include_history' => ['sometimes', Rule::in(['1'])],
        ]);
        if ($validator->fails()) {
            return null;
        }

        $validated = $validator->validated();
        $parameters = [
            'date' => $validated['date'],
            'resource' => $validated['resource'],
        ];
        if (($validated['include_history'] ?? null) === '1') {
            $parameters['include_history'] = 1;
        }

        return route('admin.service-requests.day', $parameters, false);
    }

    /** @return array<string, mixed> */
    private function requestRouteParameters(ServiceRequest $request, ?string $returnTo): array
    {
        $parameters = ['request' => $request];
        if ($returnTo !== null) {
            $parameters['return_to'] = $returnTo;
        }

        return $parameters;
    }
}
