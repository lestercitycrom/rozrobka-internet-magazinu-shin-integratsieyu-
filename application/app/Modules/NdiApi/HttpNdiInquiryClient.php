<?php

namespace App\Modules\NdiApi;

use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Data\NdiQuoteResult;
use App\Modules\NdiApi\Exceptions\NdiTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class HttpNdiInquiryClient implements NdiInquiryClient
{
    public function __construct(
        private readonly NdiA2XmlCodec $codec,
        private readonly NdiIntegrationProfileResolver $profiles,
    ) {}

    public function inquire(NdiInquiryRequest $request): NdiQuoteResult
    {
        $settings = $this->profiles->forInquiry($request->integrationProfile);
        $endpoint = $settings->endpoint;
        if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
            throw new NdiTransportException('NDI endpoint must use HTTPS.');
        }

        $xml = $this->codec->buildInquiry(
            $request,
            $settings->partyId,
            $settings->agencyCode,
        );

        try {
            $response = Http::withBasicAuth($settings->username, $settings->password)
                ->withUserAgent('EasyTire NDI-A2 client')
                ->accept('application/xml')
                ->withBody($xml, 'application/xml; charset=UTF-8')
                ->connectTimeout($settings->connectTimeoutSeconds)
                ->timeout($settings->timeoutSeconds)
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
