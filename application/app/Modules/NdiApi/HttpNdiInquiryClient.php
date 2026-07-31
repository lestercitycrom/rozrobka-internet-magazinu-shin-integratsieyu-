<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Data\NdiQuoteResult;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;
use App\Modules\NdiApi\Exceptions\NdiTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpNdiInquiryClient implements NdiInquiryClient
{
    public function __construct(private readonly NdiA2XmlCodec $codec) {}

    public function inquire(NdiInquiryRequest $request): NdiQuoteResult
    {
        if (! config('ndi_api.inquiry_enabled', false)) {
            throw new NdiApiDisabled('NDI read-only inquiry is disabled.');
        }

        $endpoint = (string) config('ndi_api.endpoint');
        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new NdiTransportException('NDI endpoint must use HTTPS.');
        }

        $username = (string) config('ndi_api.username');
        $password = (string) config('ndi_api.password');
        if ($username === '' || $password === '') {
            throw new NdiTransportException('NDI credentials are not configured.');
        }

        $xml = $this->codec->buildInquiry(
            $request,
            (string) config('ndi_api.party_id'),
            (string) config('ndi_api.agency_code'),
        );

        try {
            $response = Http::withBasicAuth($username, $password)
                ->withUserAgent('EasyTire NDI-A2 client')
                ->accept('application/xml')
                ->withBody($xml, 'application/xml; charset=UTF-8')
                ->connectTimeout((int) config('ndi_api.connect_timeout_seconds', 3))
                ->timeout((int) config('ndi_api.timeout_seconds', 8))
                ->post($endpoint);
        } catch (ConnectionException $exception) {
            throw new NdiTransportException('NDI inquiry transport failed.', previous: $exception);
        } catch (Throwable $exception) {
            throw new NdiTransportException('NDI inquiry could not be sent.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new NdiTransportException('NDI inquiry returned HTTP '.$response->status().'.');
        }

        return $this->codec->parseQuote($response->body());
    }
}
