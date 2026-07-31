<?php

use App\Modules\NdiApi\Contracts\NdiDespatchClient;
use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Contracts\NdiOrderStatusClient;
use App\Modules\NdiApi\Data\NdiC1OrderLine;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Data\NdiDespatchRequest;
use App\Modules\NdiApi\Data\NdiOrderStatusRequest;
use App\Modules\NdiApi\Exceptions\NdiApiDisabled;
use App\Modules\NdiApi\Exceptions\NdiTransportException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('C1 order traffic is independently disabled by default', function (): void {
    Http::preventStrayRequests();

    expect(fn () => app(NdiOrderClient::class)->place(ndiC1Request()))->toThrow(NdiApiDisabled::class);
});

test('it sends one C1 request only and parses the accepted order', function (): void {
    configureNdiClients(order: true);
    Http::fake(['https://ndi.test/EndPoint' => Http::response(file_get_contents(ndiFixture('c1_order_response_success.xml')), 200)]);

    $result = app(NdiOrderClient::class)->place(ndiC1Request());

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->lines[0]->externalOrderReference)->toBe('NDI-ORDER-0001');
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://ndi.test/EndPoint'
        && str_contains($request->body(), '<PartyID>1220000</PartyID>')
        && str_contains($request->body(), '<BuyersArticleID>4765205551649102</BuyersArticleID>')
        && ! str_contains($request->body(), '<Consignee'));
});

test('an uncertain C1 connection outcome is never retried', function (): void {
    configureNdiClients(order: true);
    Http::fake(['https://ndi.test/EndPoint' => Http::failedConnection()]);

    expect(fn () => app(NdiOrderClient::class)->place(ndiC1Request()))
        ->toThrow(NdiTransportException::class, 'unknown');
    Http::assertSentCount(1);
});

test('status and despatch reads have a separate disabled switch', function (): void {
    Http::preventStrayRequests();

    expect(fn () => app(NdiOrderStatusClient::class)->get(new NdiOrderStatusRequest('NDI-ORDER-0001')))->toThrow(NdiApiDisabled::class)
        ->and(fn () => app(NdiDespatchClient::class)->get(new NdiDespatchRequest('NDI-ORDER-0001')))->toThrow(NdiApiDisabled::class);
});

test('it parses status and despatch through the shared authenticated endpoint', function (): void {
    configureNdiClients(read: true);
    Http::fakeSequence()
        ->push(file_get_contents(ndiFixture('status_response_completed.xml')), 200)
        ->push(file_get_contents(ndiFixture('desadv_response_success.xml')), 200);

    $status = app(NdiOrderStatusClient::class)->get(new NdiOrderStatusRequest('NDI-ORDER-0001'));
    $despatch = app(NdiDespatchClient::class)->get(new NdiDespatchRequest('NDI-ORDER-0001'));

    expect($status->salesStatus)->toBe('Completed')
        ->and($status->tracking[0]->trackingNumber)->toBe('TRACK-0001')
        ->and($despatch->documents[0]->tracking[0]->trackingNumber)->toBe('TRACK-0001');
    Http::assertSentCount(2);
});

function ndiC1Request(): NdiC1OrderRequest
{
    return new NdiC1OrderRequest('EASY-TEST-0001', [new NdiC1OrderLine('1', '4765205551649102', 2)]);
}

function configureNdiClients(bool $order = false, bool $read = false): void
{
    config()->set('ndi_api', [
        'inquiry_enabled' => false,
        'order_enabled' => $order,
        'fulfilment_read_enabled' => $read,
        'endpoint' => 'https://ndi.test/EndPoint',
        'username' => 'fixture-user',
        'password' => 'fixture-password',
        'party_id' => '1220000',
        'agency_code' => '91',
        'connect_timeout_seconds' => 1,
        'timeout_seconds' => 2,
    ]);
}
