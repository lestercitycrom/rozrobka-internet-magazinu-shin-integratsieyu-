<?php

namespace Database\Seeders;

use App\Modules\Content\Models\HomepageServiceCard;
use App\Modules\Content\Models\MediaAsset;
use App\Modules\Content\Models\StorefrontSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Installs the customer-supplied service photography and repairs the five
 * Russian values that were accidentally saved in Bokmål fields on staging.
 *
 * Every update is guarded by the exact old value/path so later operator edits
 * remain authoritative.
 */
final class CustomerServiceMediaLocaleRepairSeeder extends Seeder
{
    /** @var array<string, array{file: string, alt_nb: string, alt_ru: string, card_key: string, previous_path: string}> */
    private const MEDIA = [
        'oil-change' => [
            'file' => 'customer-oil-change.webp',
            'alt_nb' => 'Oljeskift hos EasyTire i Tromsø',
            'alt_ru' => 'Замена масла в EasyTire в Тромсё',
            'card_key' => 'issue-46-additional-oil-change',
            'previous_path' => 'media/homepage/issue-46/workshop.webp',
        ],
        'interior-cleaning' => [
            'file' => 'customer-interior-cleaning.webp',
            'alt_nb' => 'Nyrenset bilinteriør hos EasyTire',
            'alt_ru' => 'Салон автомобиля после химчистки в EasyTire',
            'card_key' => 'issue-46-additional-interior-cleaning',
            'previous_path' => 'media/homepage/issue-46/exterior.webp',
        ],
    ];

    /** @var array<string, array{from: string, to: string}> */
    private const BOKMAL_REPAIRS = [
        'hero_kicker_nb' => ['from' => 'Смена колёс в Тромсё', 'to' => 'Dekkskift i Tromsø'],
        'hero_title_nb' => ['from' => 'Меняем колёса там, где стоит автомобиль.', 'to' => 'Vi skifter hjul der bilen står.'],
        'hero_primary_label_nb' => ['from' => 'Заказать смену колёс', 'to' => 'Bestill dekkskift'],
        'store_heading_nb' => ['from' => 'Шины и диски для вашего автомобиля', 'to' => 'Dekk og felger til bilen din'],
        'store_lead_nb' => [
            'from' => 'Найдите подходящие шины, диски и колёсные комплекты. Используйте регистрационный номер или выберите известный размер вручную.',
            'to' => 'Finn riktige dekk, felger og komplette hjul til bilen din. Søk med registreringsnummer eller velg kjent dimensjon, sammenlign produkter og bestill enkelt online.',
        ],
    ];

    public function run(): void
    {
        $this->repairBokmalCopy();

        foreach (self::MEDIA as $definition) {
            $asset = $this->installMedia($definition);
            $this->attachMedia($definition, $asset);
        }
    }

    private function repairBokmalCopy(): void
    {
        $settings = StorefrontSettings::query()->find(1);
        if ($settings === null) {
            return;
        }

        foreach (self::BOKMAL_REPAIRS as $field => $repair) {
            if ($settings->getAttribute($field) === $repair['from']) {
                $settings->setAttribute($field, $repair['to']);
            }
        }

        if ($settings->isDirty()) {
            $settings->save();
        }
    }

    /** @param array{file: string, alt_nb: string, alt_ru: string, card_key: string, previous_path: string} $definition */
    private function installMedia(array $definition): MediaAsset
    {
        $sourcePath = public_path('brand/'.$definition['file']);
        $bytes = is_file($sourcePath) ? file_get_contents($sourcePath) : false;
        $dimensions = $bytes === false ? false : getimagesizefromstring($bytes);
        if ($bytes === false || $dimensions === false) {
            throw new LogicException('Customer service media is unavailable: '.$definition['file']);
        }

        $storagePath = 'media/homepage/issue-46/'.$definition['file'];
        $sha256 = hash('sha256', $bytes);
        $asset = MediaAsset::query()->firstOrNew(['path' => $storagePath]);

        if ($asset->exists && $asset->sha256 !== $sha256) {
            throw new LogicException('Customer service media path already contains different bytes: '.$storagePath);
        }

        if (! $asset->exists) {
            $asset->fill([
                'disk' => 'public',
                'original_name' => $definition['file'],
                'mime' => 'image/webp',
                'byte_size' => strlen($bytes),
                'width' => $dimensions[0],
                'height' => $dimensions[1],
                'sha256' => $sha256,
                'alt_nb' => $definition['alt_nb'],
                'alt_ru' => $definition['alt_ru'],
                'uploader_id' => null,
                'is_active' => true,
            ])->save();
        }

        if (! Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->put($storagePath, $bytes);
        }

        return $asset;
    }

    /** @param array{file: string, alt_nb: string, alt_ru: string, card_key: string, previous_path: string} $definition */
    private function attachMedia(array $definition, MediaAsset $asset): void
    {
        $card = HomepageServiceCard::query()->withTrashed()->where('content_key', $definition['card_key'])->first();
        if ($card === null || $card->trashed() || $card->media_asset_id === $asset->id) {
            return;
        }

        $currentPath = $card->media_asset_id === null
            ? null
            : MediaAsset::query()->whereKey($card->media_asset_id)->value('path');

        if ($currentPath === null || $currentPath === $definition['previous_path']) {
            $card->update(['media_asset_id' => $asset->id]);
        }
    }
}
