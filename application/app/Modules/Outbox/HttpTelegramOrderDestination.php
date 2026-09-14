<?php

namespace App\Modules\Outbox;

use App\Modules\Outbox\Contracts\TelegramOrderDestination;
use App\Modules\ServiceBooking\ServiceRequestVehiclePayload;
use Illuminate\Http\Client\Factory as HttpFactory;

final class HttpTelegramOrderDestination implements TelegramOrderDestination
{
    public function __construct(private readonly HttpFactory $http) {}

    public function deliver(array $payload, string $idempotencyKey): void
    {
        $token = (string) config('outbox.telegram.bot_token');
        $chatId = (string) config('outbox.telegram.chat_id');
        if ($token === '' || $chatId === '') {
            throw new \LogicException('Telegram destination is not fully configured.');
        }

        $isServiceRequest = ($payload['event'] ?? null) === 'service_request_created';
        $fallback = $isServiceRequest ? 'service-request' : 'paid-order';
        $reference = trim((string) ($payload['reference'] ?? $fallback)) ?: $fallback;
        if ($isServiceRequest) {
            $service = trim((string) ($payload['service_slug'] ?? 'ukjent tjeneste')) ?: 'ukjent tjeneste';
            $fulfilment = ($payload['fulfilment'] ?? null) === 'mobile' ? 'hjemme hos kunden' : 'verksted';
            $date = trim((string) ($payload['preferred_date'] ?? 'ikke valgt')) ?: 'ikke valgt';
            $cars = max(1, min(ServiceRequestVehiclePayload::MAX_VEHICLES, (int) ($payload['car_count'] ?? 1)));
            $storage = ($payload['dekkhotell'] ?? false) === true ? 'ja' : 'nei';
            $minimum = is_int($payload['quote_min_minor'] ?? null) ? $payload['quote_min_minor'] : null;
            $maximum = is_int($payload['quote_max_minor'] ?? null) ? $payload['quote_max_minor'] : $minimum;
            $quote = $minimum === null ? 'ikke beregnet' : ($minimum === $maximum ? number_format($minimum / 100, 2, '.', '') : number_format($minimum / 100, 2, '.', '').'–'.number_format($maximum / 100, 2, '.', '')).' NOK';
            $text = sprintf("EasyTire: ny tjenesteforespørsel %s\nTjeneste: %s\nSted: %s\nØnsket dato: %s\nBiler: %d\nDekkhotell: %s\nBeregnet pris: %s\nLeveringsnøkkel: %s", $reference, $service, $fulfilment, $date, $cars, $storage, $quote, $idempotencyKey);
        } else {
            $amount = is_int($payload['amount_minor'] ?? null) ? $payload['amount_minor'] : 0;
            $currency = preg_match('/^[A-Z]{3}$/', (string) ($payload['currency'] ?? '')) === 1 ? $payload['currency'] : '';
            [$productLines, $serviceLines, $lineCount] = self::lineCounts($payload);
            $text = sprintf("EasyTire: betalt ordre %s\nTotal: %s %s\nVarelinjer: %d\nTjenestelinjer: %d\nLinjer totalt: %d\nLeveringsnøkkel: %s", $reference, number_format($amount / 100, 2, '.', ''), $currency, $productLines, $serviceLines, $lineCount, $idempotencyKey);
        }

        $response = $this->http->acceptJson()
            ->connectTimeout((int) config('outbox.timeout', 5))
            ->timeout((int) config('outbox.timeout', 5))
            ->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ])->throw();

        if ($response->json('ok') !== true) {
            throw new \RuntimeException('Telegram did not acknowledge the delivery.');
        }
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
}
