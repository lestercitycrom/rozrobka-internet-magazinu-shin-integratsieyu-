<?php

use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Data\NdiInquiryLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;
use App\Modules\NdiApi\Exceptions\NdiTransportException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function ndiInquiryRequest(): NdiInquiryRequest
{
    return new NdiInquiryRequest('INQUIRY-001', 'CHECKOUT-001', [new NdiInquiryLine('1', '000205550016', 4)]);
}

test('external inquiry is disabled by default', function (): void {
    Http::preventStrayRequests();

    expect(fn () => app(NdiInquiryClient::class)->inquire(ndiInquiryRequest()))->toThrow(NdiApiDisabled::class);
});

test('it posts an authenticated A2 inquiry to the configured HTTPS endpoint', function (): void {
    config()->set('ndi_api', [
        'inquiry_enabled' => true,
        'endpoint' => 'https://ndi.test/EndPoint',
        'username' => 'fixture-user',
        'password' => 'fixture-password',
        'party_id' => 'PARTY-TEST',
        'agency_code' => 'AGENCY-TEST',
        'connect_timeout_seconds' => 1,
        'timeout_seconds' => 2,
    ]);
    Http::fake(['https://ndi.test/EndPoint' => Http::response(file_get_contents(ndiFixture('quote_a2_success.xml')), 200, ['Content-Type' => 'application/xml'])]);

    $result = app(NdiInquiryClient::class)->inquire(ndiInquiryRequest());

    expect($result->isSuccessful())->toBeTrue();
    Http::assertSent(function (Request $request): bool {
        $authorization = $request->header('Authorization')[0] ?? '';

        return $request->url() === 'https://ndi.test/EndPoint'
            && $request->method() === 'POST'
            && $authorization === 'Basic '.base64_encode('fixture-user:fixture-password')
            && str_contains($request->body(), 'xmlns:ew="http://www.reifen.net"')
            && str_contains($request->body(), '<ManufacturersArticleID>000205550016</ManufacturersArticleID>');
    });
});

test('it refuses a non-HTTPS endpoint before sending credentials', function (): void {
    config()->set('ndi_api.inquiry_enabled', true);
    config()->set('ndi_api.endpoint', 'http://ndi.test/EndPoint');
    Http::preventStrayRequests();

    expect(fn () => app(NdiInquiryClient::class)->inquire(ndiInquiryRequest()))->toThrow(NdiTransportException::class, 'HTTPS');
});

test('it classifies a non-success HTTP status as transport failure without returning its body', function (): void {
    config()->set('ndi_api', [
        'inquiry_enabled' => true,
        'endpoint' => 'https://ndi.test/EndPoint',
        'username' => 'fixture-user',
        'password' => 'fixture-password',
        'party_id' => 'PARTY-TEST',
        'agency_code' => 'AGENCY-TEST',
        'connect_timeout_seconds' => 1,
        'timeout_seconds' => 2,
    ]);
    Http::fake(['https://ndi.test/EndPoint' => Http::response('secret response body', 401)]);

    expect(fn () => app(NdiInquiryClient::class)->inquire(ndiInquiryRequest()))->toThrow(NdiTransportException::class, 'HTTP 401');
});
