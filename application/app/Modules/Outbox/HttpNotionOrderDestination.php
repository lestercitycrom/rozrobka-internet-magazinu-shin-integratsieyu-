<?php

namespace App\Modules\Outbox;

use App\Modules\Outbox\Contracts\NotionOrderDestination;
use Illuminate\Http\Client\Factory as HttpFactory;

final class HttpNotionOrderDestination implements NotionOrderDestination
{
    public function __construct(private readonly HttpFactory $http) {}

    public function deliver(array $payload, string $idempotencyKey): void
    {
        $token = (string) config('outbox.notion.token');
        $databaseId = (string) config('outbox.notion.database_id');
        $titleProperty = (string) config('outbox.notion.title_property', 'Name');
        $apiVersion = (string) config('outbox.notion.api_version', '2022-06-28');

        if ($token === '' || $databaseId === '' || $titleProperty === '' || $apiVersion === '') {
            throw new \LogicException('Notion destination is not fully configured.');
        }

        $reference = self::reference($payload);
        $this->http->acceptJson()
            ->withToken($token)
            ->withHeaders(['Notion-Version' => $apiVersion])
            ->connectTimeout((int) config('outbox.timeout', 5))
            ->timeout((int) config('outbox.timeout', 5))
            ->post('https://api.notion.com/v1/pages', [
                'parent' => ['database_id' => $databaseId],
                'properties' => [
                    $titleProperty => [
                        'title' => [[
                            'type' => 'text',
                            'text' => ['content' => 'EasyTire '.$reference],
                        ]],
                    ],
                ],
                'children' => [[
                    'object' => 'block',
                    'type' => 'paragraph',
                    'paragraph' => [
                        'rich_text' => [[
                            'type' => 'text',
                            'text' => ['content' => self::snapshot($payload, $idempotencyKey)],
                        ]],
                    ],
                ]],
            ])->throw();
    }

    /** @param array<string, mixed> $payload */
    private static function reference(array $payload): string
    {
        $fallback = ($payload['event'] ?? null) === 'service_request_created' ? 'service-request' : 'paid-order';

        return trim((string) ($payload['reference'] ?? $fallback)) ?: $fallback;
    }

    /** @param array<string, mixed> $payload */
    private static function snapshot(array $payload, string $idempotencyKey): string
    {
        if (($payload['event'] ?? null) === 'service_request_created') {
            $quote = self::quote($payload);
            $service = trim((string) ($payload['service_slug'] ?? 'ukjent tjeneste')) ?: 'ukjent tjeneste';
            $fulfilment = ($payload['fulfilment'] ?? null) === 'mobile' ? 'hjemme hos kunden' : 'verksted';
            $date = trim((string) ($payload['preferred_date'] ?? 'ikke valgt')) ?: 'ikke valgt';
            $cars = max(1, min(2, (int) ($payload['car_count'] ?? 1)));
            $storage = ($payload['dekkhotell'] ?? false) === true ? 'ja' : 'nei';

            return sprintf('Ny tjenesteforespørsel %s. Tjeneste: %s. Sted: %s. Ønsket dato: %s. Biler: %d. Dekkhotell: %s. Beregnet pris: %s. Leveringsnøkkel: %s.', self::reference($payload), $service, $fulfilment, $date, $cars, $storage, $quote, $idempotencyKey);
        }

        $amount = is_int($payload['amount_minor'] ?? null) ? $payload['amount_minor'] : 0;
        $currency = preg_match('/^[A-Z]{3}$/', (string) ($payload['currency'] ?? '')) === 1 ? $payload['currency'] : '';
        [$productLines, $serviceLines, $lineCount] = self::lineCounts($payload);

        return sprintf('Betalt ordre %s. Total: %s %s. Varelinjer: %d. Tjenestelinjer: %d. Linjer totalt: %d. Leveringsnøkkel: %s.', self::reference($payload), number_format($amount / 100, 2, '.', ''), $currency, $productLines, $serviceLines, $lineCount, $idempotencyKey);
    }

    /** @param array<string, mixed> $payload @return array{int, int, int} */
    private static function lineCounts(array $payload): array
    {
        $fallbackProducts = is_array($payload['items'] ?? null) ? count($payload['items']) : 0;
        $productLines = self::safeCount($payload['product_line_count'] ?? null, $fallbackProducts);
        $serviceLines = self::safeCount($payload['service_line_count'] ?? null, 0);
        $sum = $productLines + $serviceLines;
        $total = self::safeCount($payload['line_count'] ?? null, $sum);

        return [$productLines, $serviceLines, $total === $sum ? $total : $sum];
    }

    private static function safeCount(mixed $value, int $fallback): int
    {
        return is_int($value) && $value >= 0 && $value <= 100000 ? $value : $fallback;
    }

    /** @param array<string, mixed> $payload */
    private static function quote(array $payload): string
    {
        $minimum = is_int($payload['quote_min_minor'] ?? null) ? $payload['quote_min_minor'] : null;
        $maximum = is_int($payload['quote_max_minor'] ?? null) ? $payload['quote_max_minor'] : $minimum;
        if ($minimum === null || $maximum === null) {
            return 'ikke beregnet';
        }

        return $minimum === $maximum
            ? number_format($minimum / 100, 2, '.', '').' NOK'
            : number_format($minimum / 100, 2, '.', '').'–'.number_format($maximum / 100, 2, '.', '').' NOK';
    }
}
