<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpsertClient;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\ClientDirectorySearch;
use App\Modules\ServiceBooking\CustomerPhoneNormalizer;
use App\Modules\ServiceBooking\Models\Client;
use App\Modules\ServiceBooking\Models\ServiceRequest;
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

    public function create(Request $request): View
    {
        $linkRequest = $request->filled('service_request')
            ? ServiceRequest::query()->findOrFail($request->integer('service_request'))
            : null;

        return view('admin.clients.form', [
            'client' => new Client([
                'name' => $linkRequest?->name,
                'phone' => $linkRequest?->phone,
                'email' => $linkRequest?->email,
                'address' => $linkRequest?->address,
                'district' => $linkRequest?->district,
            ]),
            'linkRequest' => $linkRequest,
        ]);
    }

    public function store(UpsertClient $request, CustomerPhoneNormalizer $phones): RedirectResponse
    {
        $client = DB::transaction(function () use ($request, $phones): Client {
            $client = Client::query()->create($this->attributes($request->validated(), $phones) + [
                'created_by_user_id' => $request->user()->getKey(),
            ]);

            if ($request->filled('link_request_id')) {
                ServiceRequest::query()->whereKey($request->integer('link_request_id'))->update([
                    'client_id' => $client->getKey(),
                ]);
            }

            return $client;
        }, 3);

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
        return view('admin.clients.form', ['client' => $client, 'linkRequest' => null]);
    }

    public function update(UpsertClient $request, Client $client, CustomerPhoneNormalizer $phones): RedirectResponse
    {
        $client->update($this->attributes($request->validated(), $phones));

        return redirect()->route('admin.clients.show', $client)->with('status', __('clients.saved'));
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function attributes(array $data, CustomerPhoneNormalizer $phones): array
    {
        $data = Arr::except($data, ['link_request_id']);

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
}
