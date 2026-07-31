<?php

use App\Modules\NdiApi\Contracts\NdiInquiryClient;
use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use App\Modules\NdiApi\Data\NdiC1OrderResult;
use App\Modules\NdiApi\Data\NdiC1OrderResultLine;
use App\Modules\NdiApi\Data\NdiInquiryRequest;
use App\Modules\NdiApi\Data\NdiQuoteLine;
use App\Modules\NdiApi\Data\NdiQuoteResult;

test('read-only smoke sends one explicit item and prints no raw response', function (): void {
    $client = new class implements NdiInquiryClient
    {
        public ?NdiInquiryRequest $request = null;

        public function inquire(NdiInquiryRequest $request): NdiQuoteResult
        {
            $this->request = $request;

            return new NdiQuoteResult('QUOTE-SMOKE', '0', [
                new NdiQuoteLine('1', '4765205551649102', 1, 8, '2030-01-03'),
            ]);
        }
    };
    app()->instance(NdiInquiryClient::class, $client);

    $this->artisan('ndi:inquiry:smoke', ['sku' => '4765205551649102'])
        ->expectsOutputToContain('4765205551649102')
        ->assertSuccessful();

    expect($client->request?->lines)->toHaveCount(1)
        ->and($client->request?->lines[0]->quantity)->toBe(1);
});

test('C1 test command cannot call a client unless every explicit guard passes', function (): void {
    $client = new class implements NdiOrderClient
    {
        public int $calls = 0;

        public function place(NdiC1OrderRequest $request): NdiC1OrderResult
        {
            $this->calls++;

            return new NdiC1OrderResult('C1', $request->customerReference, '0', []);
        }
    };
    app()->instance(NdiOrderClient::class, $client);
    config()->set('ndi_api.c1_test_mode', true);
    config()->set('ndi_api.party_id', '1220000');

    $this->artisan('ndi:c1:test', ['--reference' => 'EASYTIRE-TEST-001'])
        ->assertFailed();

    expect($client->calls)->toBe(0);
});

test('C1 guarded command uses only the approved item and quantity', function (): void {
    $client = new class implements NdiOrderClient
    {
        public int $calls = 0;

        public ?NdiC1OrderRequest $request = null;

        public function place(NdiC1OrderRequest $request): NdiC1OrderResult
        {
            $this->calls++;
            $this->request = $request;

            return new NdiC1OrderResult('C1', $request->customerReference, '0', [
                new NdiC1OrderResultLine('1', '4765205551649102', '0', 'NDI-TEST-ORDER', 2, '2030-01-03'),
            ]);
        }
    };
    app()->instance(NdiOrderClient::class, $client);
    config()->set('ndi_api.c1_test_mode', true);
    config()->set('ndi_api.party_id', '1220000');

    $this->artisan('ndi:c1:test', [
        '--reference' => 'EASYTIRE-TEST-001',
        '--notice-sent' => true,
        '--acknowledge' => 'NDI_TEST_ACCOUNT_ONLY',
    ])->assertSuccessful();

    expect($client->calls)->toBe(1)
        ->and($client->request?->lines)->toHaveCount(1)
        ->and($client->request?->lines[0]->buyersArticleId)->toBe('4765205551649102')
        ->and($client->request?->lines[0]->quantity)->toBe(2);
});
