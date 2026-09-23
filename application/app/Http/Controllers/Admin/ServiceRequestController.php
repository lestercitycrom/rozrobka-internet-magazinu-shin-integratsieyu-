<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertServiceRequest;
use App\Modules\Content\Enums\ServicePagePublicationStatus;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestSource;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\Client;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\SeasonalQuoteCalculator;
use App\Modules\ServiceBooking\ServiceRequestCreator;
use App\Modules\ServiceBooking\ServiceRequestDirectorySearch;
use App\Modules\ServiceBooking\ServiceRequestMapLink;
use App\Modules\ServiceBooking\ServiceRequestReturnContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public function index(
        Request $input,
        ServiceRequestDirectorySearch $search,
        ServiceRequestReturnContext $contexts,
        ServiceRequestMapLink $maps,
    ): View {
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
            'storage_operation' => ['nullable', Rule::in(['none', 'new', 'renew', 'last', 'unknown'])],
            'assigned_van' => ['nullable', Rule::in(['unassigned', 'van_1', 'van_2'])],
            'view' => ['nullable', Rule::in(['all', 'new', 'workshop', 'mobile', 'additional'])],
            'queue' => ['nullable', Rule::in(['new', 'pending_time', 'unassigned_van', 'missing_kit', 'today'])],
            'new_only' => ['nullable', 'boolean'],
            'without_date' => ['nullable', 'boolean'],
            'selected' => ['nullable', 'integer', 'min:1'],
        ]);

        $withoutDate = $input->boolean('without_date');

        $query = ServiceRequest::query()->with(['createdBy', 'client'])->latest();
        $search->apply($query, $filters['q'] ?? null, $filters['storage_operation'] ?? null);
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

        $this->applyQueue($query, $filters['queue'] ?? null, $search);

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
        if (filled($filters['q'] ?? null)) {
            $requests->getCollection()->each(function (ServiceRequest $request) use ($search, $filters): void {
                $request->setAttribute('search_match_reason', $search->matchReason(
                    $request,
                    (string) $filters['q'],
                    $filters['storage_operation'] ?? null,
                ));
            });
        }

        $selectedRequest = null;
        if (filled($filters['selected'] ?? null)) {
            $selectedRequest = ServiceRequest::query()
                ->with(['createdBy', 'client', 'events.actor'])
                ->findOrFail((int) $filters['selected']);
        }
        $listReturnTo = $contexts->list($input, $selectedRequest?->getKey());
        $closeSelectedUrl = $contexts->withoutSelected($listReturnTo);
        $queueCounts = $this->queueCounts($search);
        $slugs = $requests->pluck('service_slug')->push($selectedRequest?->service_slug)->filter()->all();

        return view('admin.requests.index', [
            'requests' => $requests,
            'filters' => $filters,
            'serviceOptions' => $this->services(includeUnpublished: true),
            'serviceTitles' => $this->serviceTitles($slugs),
            'activeView' => $filters['view'] ?? 'all',
            'selectedRequest' => $selectedRequest,
            'selectedInResults' => $selectedRequest !== null && $requests->contains('id', $selectedRequest->getKey()),
            'selectedMapUrl' => $selectedRequest === null ? null : $maps->forAddress($selectedRequest->address),
            'listReturnTo' => $listReturnTo,
            'closeSelectedUrl' => $closeSelectedUrl,
            'queueCounts' => $queueCounts,
        ]);
    }

    public function create(Request $input, SeasonalQuoteCalculator $quotes, ServiceRequestReturnContext $contexts): View
    {
        $returnTo = $contexts->fromRequest($input);
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

    public function store(UpsertServiceRequest $input, ServiceRequestCreator $creator, ServiceRequestReturnContext $contexts): RedirectResponse
    {
        $request = $creator->createManual($input->validated(), $input->user(), app()->getLocale());

        return redirect()->route('admin.service-requests.show', $this->requestRouteParameters(
            $request,
            $contexts->fromRequest($input),
        ))
            ->with('status', __('service_requests.created'));
    }

    public function show(ServiceRequest $request, ServiceRequestMapLink $maps, Request $input, ServiceRequestReturnContext $contexts): View
    {
        $request->load(['createdBy', 'client', 'events.actor']);

        return view('admin.requests.show', [
            'request' => $request,
            'returnTo' => $contexts->fromRequest($input),
            'mapUrl' => $maps->forAddress($request->address),
            'serviceTitle' => $this->serviceTitles([$request->service_slug])[$request->service_slug] ?? $request->service_slug,
        ]);
    }

    public function edit(ServiceRequest $request, SeasonalQuoteCalculator $quotes, Request $input, ServiceRequestReturnContext $contexts): View
    {
        $request->load(['createdBy', 'client']);

        return view('admin.requests.form', $this->formData($request, $quotes) + [
            'returnTo' => $contexts->fromRequest($input),
        ]);
    }

    public function update(UpsertServiceRequest $input, ServiceRequest $request, ServiceRequestCreator $creator, ServiceRequestReturnContext $contexts): RedirectResponse
    {
        $updated = $creator->updateManual($request, $input->validated(), $input->user());
        $returnTo = $contexts->fromRequest($input);
        if ($returnTo !== null
            && parse_url($returnTo, PHP_URL_PATH) === route('admin.service-requests.index', [], false)) {
            return redirect($contexts->withSelected($returnTo, $updated->getKey()))
                ->with('status', __('service_requests.saved'));
        }

        return redirect()->route('admin.service-requests.show', $this->requestRouteParameters(
            $updated,
            $returnTo,
        ))
            ->with('status', __('service_requests.saved'));
    }

    /** @param Builder<ServiceRequest> $query */
    private function applyQueue(Builder $query, ?string $queue, ServiceRequestDirectorySearch $search): void
    {
        if ($queue === null) {
            return;
        }
        $active = [ServiceRequestStatus::New->value, ServiceRequestStatus::Contacted->value];
        if ($queue === 'new') {
            $query->where('status', ServiceRequestStatus::New);
        } elseif ($queue === 'pending_time') {
            $query->whereIn('status', $active)->whereNull('booking_starts_at');
        } elseif ($queue === 'unassigned_van') {
            $query->whereIn('status', $active)->where('fulfilment', 'mobile')->whereNull('assigned_van');
        } elseif ($queue === 'missing_kit') {
            $query->whereIn('status', $active);
            $search->whereMissingStoredKit($query);
        } elseif ($queue === 'today') {
            $query->whereIn('status', $active)->whereDate('preferred_date', now(config('service_booking.timezone'))->format('Y-m-d'));
        }
    }

    /** @return array<string, int> */
    private function queueCounts(ServiceRequestDirectorySearch $search): array
    {
        $active = [ServiceRequestStatus::New->value, ServiceRequestStatus::Contacted->value];
        $missing = ServiceRequest::query()->whereIn('status', $active);
        $search->whereMissingStoredKit($missing);

        return [
            'new' => ServiceRequest::query()->where('status', ServiceRequestStatus::New)->count(),
            'pending_time' => ServiceRequest::query()->whereIn('status', $active)->whereNull('booking_starts_at')->count(),
            'unassigned_van' => ServiceRequest::query()->whereIn('status', $active)->where('fulfilment', 'mobile')->whereNull('assigned_van')->count(),
            'missing_kit' => $missing->count(),
            'today' => ServiceRequest::query()->whereIn('status', $active)->whereDate('preferred_date', now(config('service_booking.timezone'))->format('Y-m-d'))->count(),
        ];
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
