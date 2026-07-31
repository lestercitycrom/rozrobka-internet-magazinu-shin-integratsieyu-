<?php

namespace App\Modules\VehicleFitment;

use App\Modules\VehicleFitment\Data\VehicleFitment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class VehicleLookup
{
    public function normalize(string $registration): ?string
    {
        $v = strtoupper(preg_replace('/\s+/', '', trim($registration)));

        return preg_match('/^[A-Z]{2}\d{5}$/', $v) ? $v : null;
    }

    public function lookup(string $registration): ?VehicleFitment
    {
        $reg = $this->normalize($registration);
        if ($reg === null || ! config('vehicle_fitment.enabled') || blank(config('vehicle_fitment.api_key'))) {
            return null;
        }

        return Cache::remember('fitment:'.hash('sha256', $reg), config('vehicle_fitment.cache_ttl'), function () use ($reg) {
            $r = Http::timeout(config('vehicle_fitment.timeout'))->withHeaders([config('vehicle_fitment.header') => 'Apikey '.config('vehicle_fitment.api_key')])->get(config('vehicle_fitment.endpoint'), ['kjennemerke' => $reg]);
            if (! $r->successful()) {
                return null;
            }$d = $r->json();
            $id = $d['kjoretoydata']['godkjenning']['tekniskGodkjenning'] ?? [];

            return new VehicleFitment((string) ($d['kjoretoydata']['merke'] ?? ''), (string) ($d['kjoretoydata']['handelsbetegnelse'] ?? ''), collect($id['dekkOgFelg'] ?? [])->map(fn ($x) => ['width' => (string) ($x['dekkdimensjon']['bredde'] ?? ''), 'aspect' => (string) ($x['dekkdimensjon']['profil'] ?? ''), 'rim' => (string) ($x['dekkdimensjon']['diameter'] ?? '')])->filter(fn ($x) => $x['width'] !== '' && $x['rim'] !== '')->values()->all());
        });
    }
}
