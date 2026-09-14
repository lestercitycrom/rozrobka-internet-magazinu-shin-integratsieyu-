<?php

namespace App\Modules\ServiceBooking;

use App\Modules\ServiceBooking\Models\Client;
use Illuminate\Database\Eloquent\Builder;

final class ClientDirectorySearch
{
    public function __construct(private readonly CustomerPhoneNormalizer $phones) {}

    /** @param Builder<Client> $query @return Builder<Client> */
    public function apply(Builder $query, string $search): Builder
    {
        $needle = trim($search);
        if ($needle === '') {
            return $query;
        }

        $pattern = $this->likePattern($needle);
        $phone = preg_match('/^[+\d\s().-]+$/u', $needle) === 1
            ? $this->phones->searchTerm($needle)
            : null;
        $phonePattern = $phone !== null
            ? $this->likePattern($phone)
            : null;
        $driver = $query->getModel()->getConnection()->getDriverName();

        return $query->where(function (Builder $clients) use ($driver, $pattern, $phonePattern): void {
            $clients
                ->whereRaw("LOWER(clients.name) LIKE LOWER(?) ESCAPE '!'", [$pattern])
                ->orWhereRaw("LOWER(COALESCE(clients.email, '')) LIKE LOWER(?) ESCAPE '!'", [$pattern]);

            if ($phonePattern !== null) {
                $clients->orWhereRaw("COALESCE(clients.phone_normalized, '') LIKE ? ESCAPE '!'", [$phonePattern]);
            }

            $clients->orWhereExists(function ($requests) use ($driver, $pattern): void {
                $requests->selectRaw('1')
                    ->from('service_requests as client_vehicle_requests')
                    ->whereColumn('client_vehicle_requests.client_id', 'clients.id')
                    ->where(function ($registration) use ($driver, $pattern): void {
                        $registration->whereRaw(
                            "UPPER(COALESCE(client_vehicle_requests.registration_number, '')) LIKE UPPER(?) ESCAPE '!'",
                            [$pattern],
                        );

                        if ($driver === 'sqlite') {
                            $registration->orWhereRaw(
                                "(json_valid(client_vehicle_requests.vehicles) = 1 AND EXISTS (SELECT 1 FROM json_each(client_vehicle_requests.vehicles) AS vehicle WHERE UPPER(COALESCE(json_extract(vehicle.value, '$.registration_number'), '')) LIKE UPPER(?) ESCAPE '!'))",
                                [$pattern],
                            );

                            return;
                        }

                        $registration->orWhereRaw(
                            "JSON_SEARCH(client_vehicle_requests.vehicles, 'one', ?, '!', '$[*].registration_number') IS NOT NULL",
                            [$pattern],
                        );
                    });
            });
        });
    }

    private function likePattern(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
    }
}
