<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\ServiceBooking\ClientDirectorySearch;
use App\Modules\ServiceBooking\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClientLookupController extends Controller
{
    public function __invoke(Request $request, ClientDirectorySearch $search): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $search->apply(Client::query(), (string) $filters['q']);
        $clients = $query
            ->orderBy('name')
            ->orderBy('id')
            ->simplePaginate(20, ['id', 'name', 'phone']);

        return response()->json([
            'items' => $clients->getCollection()->map(fn (Client $client): array => [
                'id' => $client->getKey(),
                'label' => $client->name.' · '.$client->phone,
            ])->values(),
            'pagination' => [
                'page' => $clients->currentPage(),
                'has_more' => $clients->hasMorePages(),
            ],
        ])->header('Cache-Control', 'no-store, private');
    }
}
