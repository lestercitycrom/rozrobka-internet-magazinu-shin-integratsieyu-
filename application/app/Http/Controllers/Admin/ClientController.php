<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertClient;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\ClientDirectorySearch;
use App\Modules\ServiceBooking\CustomerPhoneNormalizer;
use App\Modules\ServiceBooking\Models\Client;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\ServiceRequestChangeRecorder;
use App\Modules\ServiceBooking\ServiceRequestReturnContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ClientController extends Controller
{
    public function index(Request $request, ClientDirectorySearch $search): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = Client::query()->withCount('serviceRequests');
        if (filled($filters['q'] ?? null)) {
            $search->apply($query, (string) $filters['q']);
        }

        $clients = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.clients.index', compact('clients', 'filters'));
    }

    public function create(
        Request $request,
        ServiceRequestReturnContext $contexts,
        CustomerPhoneNormalizer $phones,
    ): View {
        $linkRequest = $request->filled('service_request')
            ? ServiceRequest::query()->with('client')->findOrFail($request->integer('service_request'))
            : null;
        $normalizedPhone = $phones->normalize($linkRequest?->phone);
        $email = filled($linkRequest?->email) ? mb_strtolower(trim((string) $linkRequest?->email)) : null;
        $duplicateClients = collect();

        if ($linkRequest !== null && ($normalizedPhone !== null || $email !== null)) {
            $duplicateClients = Client::query()
                ->where(function ($query) use ($normalizedPhone, $email): void {
                    if ($normalizedPhone !== null) {
                        $query->where('phone_normalized', $normalizedPhone);
                    }
                    if ($email !== null) {
                        if ($normalizedPhone !== null) {
                            $query->orWhereRaw('LOWER(email) = ?', [$email]);
                        } else {
                            $query->whereRaw('LOWER(email) = ?', [$email]);
                        }
                    }
                })
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'phone', 'email']);
        }

        return view('admin.clients.form', [
            'client' => new Client([
                'name' => $linkRequest?->name,
                'phone' => $linkRequest?->phone,
                'email' => $linkRequest?->email,
                'address' => $linkRequest?->address,
                'district' => $linkRequest?->district,
            ]),
            'linkRequest' => $linkRequest,
            'duplicateClients' => $duplicateClients,
            'returnTo' => $contexts->fromRequest($request),
        ]);
    }

    public function store(
        UpsertClient $request,
        CustomerPhoneNormalizer $phones,
        ServiceRequestChangeRecorder $changes,
        ServiceRequestReturnContext $contexts,
    ): RedirectResponse {
        $client = DB::transaction(function () use ($request, $phones, $changes): Client {
            $linkRequest = $request->filled('link_request_id')
                ? ServiceRequest::query()->whereKey($request->integer('link_request_id'))->lockForUpdate()->firstOrFail()
                : null;
            if ($linkRequest !== null && $changes->alreadyApplied(
                $linkRequest,
                $request->user(),
                (string) $request->input('command_id'),
                'client_link',
            )) {
                return Client::query()->findOrFail($linkRequest->fresh()->client_id);
            }
            if ($linkRequest !== null) {
                $changes->assertExpected($linkRequest, $this->expectedVersion($request));
            }

            $client = Client::query()->create($this->attributes($request->validated(), $phones) + [
                'created_by_user_id' => $request->user()->getKey(),
            ]);

            if ($linkRequest !== null) {
                $changes->apply(
                    request: $linkRequest,
                    attributes: ['client_id' => $client->getKey()],
                    actor: $request->user(),
                    expectedVersion: $this->expectedVersion($request),
                    commandId: (string) $request->input('command_id'),
                    command: 'client_link',
                );
            }

            return $client;
        }, 3);

        $returnTo = $contexts->fromRequest($request);
        if ($request->filled('link_request_id') && $returnTo !== null) {
            return redirect($contexts->withSelected($returnTo, $request->integer('link_request_id')))
                ->with('status', __('clients.created'));
        }

        return redirect()->route('admin.clients.show', $client)->with('status', __('clients.created'));
    }

    public function show(Client $client): View
    {
        $client->load('createdBy');
        $linkedRequests = $client->serviceRequests()->latest()->paginate(25)->withQueryString();

        $locale = app()->getLocale();
        $serviceTitles = ServicePage::query()
            ->whereIn('slug', $linkedRequests->pluck('service_slug')->unique())
            ->get(['slug', 'title_nb', 'title_ru'])
            ->mapWithKeys(fn (ServicePage $page): array => [
                $page->slug => $locale === 'ru' && filled($page->title_ru) ? $page->title_ru : $page->title_nb,
            ]);

        return view('admin.clients.show', compact('client', 'linkedRequests', 'serviceTitles'));
    }

    public function edit(Client $client): View
    {
        return view('admin.clients.form', ['client' => $client, 'linkRequest' => null, 'duplicateClients' => collect()]);
    }

    public function update(UpsertClient $request, Client $client, CustomerPhoneNormalizer $phones): RedirectResponse
    {
        $client->update($this->attributes($request->validated(), $phones));

        return redirect()->route('admin.clients.show', $client)->with('status', __('clients.saved'));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data, CustomerPhoneNormalizer $phones): array
    {
        $data = Arr::except($data, ['link_request_id', 'expected_version', 'resolve_conflict', 'conflict_version', 'command_id', 'return_to']);

        return [
            'name' => trim((string) $data['name']),
            'phone' => trim((string) $data['phone']),
            'phone_normalized' => $phones->normalize((string) $data['phone']),
            'email' => filled($data['email'] ?? null) ? mb_strtolower(trim((string) $data['email'])) : null,
            'address' => filled($data['address'] ?? null) ? trim((string) $data['address']) : null,
            'district' => filled($data['district'] ?? null) ? trim((string) $data['district']) : null,
            'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
        ];
    }

    private function expectedVersion(UpsertClient $request): int
    {
        return $request->boolean('resolve_conflict')
            ? $request->integer('conflict_version')
            : $request->integer('expected_version');
    }
}
