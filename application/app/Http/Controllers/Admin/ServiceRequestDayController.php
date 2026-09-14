<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Models\ServicePage;
use App\Modules\ServiceBooking\Enums\ServiceRequestStatus;
use App\Modules\ServiceBooking\Models\ServiceRequest;
use App\Modules\ServiceBooking\ServiceRequestMapLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ServiceRequestDayController extends Controller
{
    public function __invoke(Request $request, ServiceRequestMapLink $maps): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'resource' => ['nullable', Rule::in(['all', 'workshop', 'van_1', 'van_2', 'mobile_unassigned'])],
            'include_cancelled' => ['nullable', 'boolean'],
        ]);
        $timezone = (string) config('service_booking.timezone', 'Europe/Oslo');
        $date = (string) ($filters['date'] ?? CarbonImmutable::now($timezone)->format('Y-m-d'));
        $resource = (string) ($filters['resource'] ?? 'all');

        $query = ServiceRequest::query()
            ->with('client')
            ->whereDate('preferred_date', $date);
        if (! $request->boolean('include_cancelled')) {
            $query->where('status', '!=', ServiceRequestStatus::Cancelled->value);
        }

        $this->applyResource($query, $resource);

        $requests = $query
            ->orderByRaw('CASE WHEN booking_starts_at IS NULL AND preferred_period_starts_at IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('COALESCE(booking_starts_at, preferred_period_starts_at)')
            ->orderByRaw('CASE WHEN booking_starts_at IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('id')
            ->get();

        $locale = app()->getLocale();
        $serviceTitles = ServicePage::query()
            ->whereIn('slug', $requests->pluck('service_slug')->filter()->unique())
            ->get(['slug', 'title_nb', 'title_ru'])
            ->mapWithKeys(fn (ServicePage $page): array => [
                $page->slug => $locale === 'ru' && filled($page->title_ru) ? $page->title_ru : $page->title_nb,
            ]);

        return view('admin.requests.day', compact(
            'requests', 'serviceTitles', 'maps', 'timezone', 'date', 'resource', 'filters',
        ));
    }

    /** @param Builder<ServiceRequest> $query */
    private function applyResource(Builder $query, string $resource): void
    {
        match ($resource) {
            'workshop' => $query->where('fulfilment', 'workshop'),
            'van_1', 'van_2' => $query->where('fulfilment', 'mobile')->where('assigned_van', $resource),
            'mobile_unassigned' => $query->where('fulfilment', 'mobile')->whereNull('assigned_van'),
            default => null,
        };
    }
}
