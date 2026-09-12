<?php

namespace Database\Seeders;

use App\Modules\Content\Models\HomepagePromotion;
use Illuminate\Database\Seeder;
use LogicException;

/** Installs browser-only text edge cases after the normal buyer suite has run. */
final class E2eHomepagePromotionEdgeCasesSeeder extends Seeder
{
    public const LONG_SHORT_NB_END = 'SLUTTEN PÅ DEN LANGE KORTTEKSTEN';

    public const LONG_SHORT_RU_END = 'КОНЕЦ ДЛИННОГО КРАТКОГО ОПИСАНИЯ';

    public const LEGACY_BODY_NB_END = 'SLUTTEN PÅ DET ELDRE TILBUDET';

    public const LEGACY_BODY_RU_END = 'КОНЕЦ СТАРОГО ПРЕДЛОЖЕНИЯ';

    public function run(): void
    {
        if (! app()->environment(['testing', 'local'])) {
            throw new LogicException('E2E homepage edge cases are restricted to testing/local.');
        }

        $longShort = HomepagePromotion::query()
            ->where('content_key', 'issue-46-offer-early-booking')
            ->firstOrFail();
        $longShort->forceFill([
            'short_body_nb' => $this->longText('Denne korte tilbudsteksten skal kunne leses helt uten å dekke handlingen.', self::LONG_SHORT_NB_END),
            'short_body_ru' => $this->longText('Это длинное краткое описание должно читаться полностью и не перекрывать действие.', self::LONG_SHORT_RU_END),
            'manifest_hash' => null,
        ])->save();

        $legacy = HomepagePromotion::query()
            ->where('content_key', 'issue-46-offer-multiple-cars')
            ->firstOrFail();
        $legacy->forceFill([
            'short_body_nb' => null,
            'body_nb' => $this->longText('Hele det eldre tilbudet uten et eget kort sammendrag skal være tilgjengelig.', self::LEGACY_BODY_NB_END, 22),
            'short_body_ru' => null,
            'body_ru' => $this->longText('Полный текст старого предложения без отдельного краткого описания должен быть доступен.', self::LEGACY_BODY_RU_END, 22),
            'manifest_hash' => null,
        ])->save();
    }

    private function longText(string $sentence, string $end, int $repetitions = 12): string
    {
        return trim(str_repeat($sentence.' ', $repetitions).$end);
    }
}
