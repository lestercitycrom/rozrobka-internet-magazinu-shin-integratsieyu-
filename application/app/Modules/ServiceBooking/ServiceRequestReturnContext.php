<?php

namespace App\Modules\ServiceBooking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class ServiceRequestReturnContext
{
    /** @var list<string> */
    private const LIST_KEYS = [
        'q', 'date', 'date_from', 'date_to', 'service', 'fulfilment', 'status',
        'district', 'dekkhotell', 'assigned_van', 'storage_operation', 'view',
        'queue', 'new_only', 'without_date', 'page', 'selected',
    ];

    public function fromRequest(Request $request): ?string
    {
        $candidate = $request->input('return_to', $request->query('return_to'));

        return is_string($candidate) ? $this->canonicalize($candidate) : null;
    }

    public function list(Request $request, ?int $selected = null): string
    {
        $query = $request->only(self::LIST_KEYS);
        if ($selected === null) {
            unset($query['selected']);
        } else {
            $query['selected'] = $selected;
        }

        return $this->canonicalize(route('admin.service-requests.index', $query, false))
            ?? route('admin.service-requests.index', [], false);
    }

    public function withSelected(string $returnTo, int $selected): string
    {
        $canonical = $this->canonicalize($returnTo);
        if ($canonical === null) {
            return route('admin.service-requests.index', ['selected' => $selected], false);
        }
        $parts = parse_url($canonical);
        if (($parts['path'] ?? null) !== route('admin.service-requests.index', [], false)) {
            return $canonical;
        }
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['selected'] = $selected;

        return route('admin.service-requests.index', $query, false);
    }

    public function withoutSelected(string $returnTo): string
    {
        $canonical = $this->canonicalize($returnTo);
        if ($canonical === null) {
            return route('admin.service-requests.index', [], false);
        }
        $parts = parse_url($canonical);
        if (($parts['path'] ?? null) !== route('admin.service-requests.index', [], false)) {
            return $canonical;
        }
        parse_str((string) ($parts['query'] ?? ''), $query);
        unset($query['selected']);

        return route('admin.service-requests.index', $query, false);
    }

    public function canonicalize(string $candidate): ?string
    {
        if ($candidate === '' || str_contains($candidate, '\\')) {
            return null;
        }
        $parts = parse_url($candidate);
        if ($parts === false
            || array_intersect_key($parts, array_flip(['scheme', 'host', 'port', 'user', 'pass', 'fragment'])) !== []) {
            return null;
        }

        parse_str((string) ($parts['query'] ?? ''), $query);
        if (collect($query)->contains(fn ($value): bool => is_array($value))) {
            return null;
        }

        $listPath = route('admin.service-requests.index', [], false);
        $dayPath = route('admin.service-requests.day', [], false);
        if (($parts['path'] ?? null) === $listPath) {
            if (array_diff(array_keys($query), self::LIST_KEYS) !== []) {
                return null;
            }
            $validator = Validator::make($query, [
                'q' => ['sometimes', 'string', 'max:120'],
                'date' => ['sometimes', 'date_format:Y-m-d'],
                'date_from' => ['sometimes', 'date_format:Y-m-d'],
                'date_to' => ['sometimes', 'date_format:Y-m-d'],
                'service' => ['sometimes', 'string', 'max:120'],
                'fulfilment' => ['sometimes', Rule::in(['workshop', 'mobile'])],
                'status' => ['sometimes', Rule::in(['new', 'contacted', 'closed', 'cancelled'])],
                'district' => ['sometimes', 'string', 'max:120'],
                'dekkhotell' => ['sometimes', Rule::in(['0', '1'])],
                'assigned_van' => ['sometimes', Rule::in(['unassigned', 'van_1', 'van_2'])],
                'storage_operation' => ['sometimes', Rule::in(['none', 'new', 'renew', 'last', 'unknown'])],
                'view' => ['sometimes', Rule::in(['all', 'new', 'workshop', 'mobile', 'additional'])],
                'queue' => ['sometimes', Rule::in(['new', 'pending_time', 'unassigned_van', 'missing_kit', 'today'])],
                'new_only' => ['sometimes', Rule::in(['1'])],
                'without_date' => ['sometimes', Rule::in(['1'])],
                'page' => ['sometimes', 'integer', 'min:1'],
                'selected' => ['sometimes', 'integer', 'min:1'],
            ]);
            if ($validator->fails()) {
                return null;
            }

            return route('admin.service-requests.index', $validator->validated(), false);
        }

        if (($parts['path'] ?? null) === $dayPath) {
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

            return route('admin.service-requests.day', $validator->validated(), false);
        }

        return null;
    }
}
