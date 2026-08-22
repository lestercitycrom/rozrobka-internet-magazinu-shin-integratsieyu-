<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Data\NdiConnectionSettings;
use App\Modules\NdiApi\Exceptions\NdiTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class NdiHttpTransport
{
    /**
     * One POST attempt only. In particular, mutating C1 traffic must never be
     * retried here because a lost response does not prove that NDI rejected it.
     */
    public function post(string $xml, string $operation, ?NdiConnectionSettings $settings = null): string
    {
        $endpoint = $settings?->endpoint ?? (string) config('ndi_api.endpoint');
        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new NdiTransportException('NDI endpoint must use HTTPS.');
        }

        $username = $settings?->username ?? (string) config('ndi_api.username');
        $password = $settings?->password ?? (string) config('ndi_api.password');
        if ($username === '' || $password === '') {
            throw new NdiTransportException('NDI credentials are not configured.');
        }

        try {
            $response = Http::withBasicAuth($username, $password)
                ->withUserAgent("EasyTire NDI {$operation} client")
                ->accept('application/xml')
                ->withBody($xml, 'application/xml; charset=UTF-8')
                ->connectTimeout($settings?->connectTimeoutSeconds ?? (int) config('ndi_api.connect_timeout_seconds', 3))
                ->timeout($settings?->timeoutSeconds ?? (int) config('ndi_api.timeout_seconds', 8))
                ->post($endpoint);
        } catch (ConnectionException $exception) {
            throw new NdiTransportException('NDI transport outcome is unknown.', previous: $exception);
        } catch (Throwable $exception) {
            throw new NdiTransportException('NDI request outcome is unknown.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new NdiTransportException('NDI request returned HTTP '.$response->status().'; outcome is unknown.');
        }

        return $response->body();
    }
}
