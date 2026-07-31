<?php

namespace App\Console\Commands;

use App\Modules\NdiApi\Contracts\NdiOrderClient;
use App\Modules\NdiApi\Data\NdiC1OrderLine;
use App\Modules\NdiApi\Data\NdiC1OrderRequest;
use Illuminate\Console\Command;
use Throwable;

final class NdiC1TestOrder extends Command
{
    private const TEST_PARTY_ID = '1220000';

    private const TEST_ARTICLE_ID = '4765205551649102';

    protected $signature = 'ndi:c1:test
        {--reference= : Unique reference beginning with EASYTIRE-TEST-}
        {--notice-sent : Confirm that NDI was notified before this test}
        {--acknowledge= : Must equal NDI_TEST_ACCOUNT_ONLY}';

    protected $description = 'Send the single NDI-approved C1 test shape with strict test-account guards.';

    public function handle(NdiOrderClient $client): int
    {
        $reference = trim((string) $this->option('reference'));
        $guardsPass = app()->environment(['local', 'staging', 'testing'])
            && config('ndi_api.c1_test_mode', false)
            && hash_equals(self::TEST_PARTY_ID, (string) config('ndi_api.party_id'))
            && (bool) $this->option('notice-sent')
            && hash_equals('NDI_TEST_ACCOUNT_ONLY', (string) $this->option('acknowledge'))
            && str_starts_with($reference, 'EASYTIRE-TEST-')
            && mb_strlen($reference) <= 50;

        if (! $guardsPass) {
            $this->components->error('C1 test guard rejected the request. Check environment, test mode, PartyID, notice and acknowledgement.');

            return self::FAILURE;
        }

        try {
            $result = $client->place(new NdiC1OrderRequest(
                customerReference: $reference,
                lines: [new NdiC1OrderLine('1', self::TEST_ARTICLE_ID, 2)],
            ));
        } catch (Throwable) {
            $this->components->error('C1 test outcome is unknown. Do not retry; reconcile with NDI using the test reference.');

            return self::FAILURE;
        }

        $this->table(
            ['Reference', 'Header error', 'Accepted', 'External reference'],
            [[
                $reference,
                $result->errorCode,
                $result->isSuccessful() ? 'yes' : 'no',
                collect($result->lines)->pluck('externalOrderReference')->filter()->unique()->implode(', ') ?: 'not supplied',
            ]],
        );

        return $result->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
