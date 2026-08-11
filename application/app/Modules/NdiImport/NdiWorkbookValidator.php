<?php

namespace App\Modules\NdiImport;

use App\Modules\NdiImport\Data\ValidatedWorkbook;
use App\Modules\NdiImport\Data\WorkbookRejection;
use App\Modules\NdiImport\Data\WorkbookRow;
use App\Modules\NdiImport\Enums\DatasetRole;
use App\Modules\NdiImport\Exceptions\RowValidationException;

final class NdiWorkbookValidator
{
    public function __construct(private readonly NdiWorkbookParser $parser) {}

    public function validate(string $path): ValidatedWorkbook
    {
        $schema = $this->parser->inspect($path);
        $stagingPath = tempnam(sys_get_temp_dir(), 'ndi-validated-');

        if ($stagingPath === false) {
            throw new \RuntimeException('Unable to create the NDI validation staging stream.');
        }

        $handle = fopen($stagingPath, 'wb');

        if ($handle === false) {
            throw new \RuntimeException('Unable to open the NDI validation staging stream.');
        }

        $candidates = 0;
        $rejections = [];
        /** @var array<string, int> $seen */
        $seen = [];

        try {
            foreach ($this->parser->rows($path, $schema) as $row) {
                try {
                    $normalized = $this->normalize($schema->role, $row);
                    $externalSku = $normalized['external_sku'];

                    if (isset($seen[$externalSku])) {
                        $rejections[] = new WorkbookRejection(
                            $row->rowNumber,
                            $externalSku,
                            $this->skuField($schema->role),
                            "duplicate_external_sku:first_row={$seen[$externalSku]}",
                            $row->values,
                        );

                        continue;
                    }

                    $seen[$externalSku] = $row->rowNumber;
                    fwrite($handle, json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)."\n");
                    $candidates++;
                } catch (RowValidationException $exception) {
                    $rejections[] = new WorkbookRejection(
                        $row->rowNumber,
                        $this->rawSku($schema->role, $row),
                        $exception->fieldName,
                        $exception->getMessage(),
                        $row->values,
                    );
                }
            }
        } finally {
            fclose($handle);
        }

        return new ValidatedWorkbook(
            $schema,
            $rejections === [] ? $candidates : 0,
            $rejections,
            $stagingPath,
        );
    }

    /** @return array<string, mixed> */
    private function normalize(DatasetRole $role, WorkbookRow $row): array
    {
        return match ($role) {
            DatasetRole::Tires => $this->normalizeTire($row),
            DatasetRole::Wheels => $this->normalizeWheel($row),
            DatasetRole::Stock => $this->normalizeStock($row),
        };
    }

    /** @return array<string, mixed> */
    private function normalizeTire(WorkbookRow $row): array
    {
        $values = $row->values;
        $sku = $this->identifier($values['ItemID'], 'ItemID');
        $gross = $this->decimal($values['Gross_Price'], 'Gross_Price');
        $net = $this->decimal($values['Net_Price'], 'Net_Price');
        $rawMediaUrl = $this->text($values['PlainUrl']);
        $mediaUrl = NdiMediaUrl::canonicalize($rawMediaUrl);

        if ($rawMediaUrl !== null && $mediaUrl === null && ! NdiMediaUrl::isExplicitlyEmpty($rawMediaUrl)) {
            throw new RowValidationException('PlainUrl', 'unrecognized_non_empty_ndi_media_url');
        }

        $this->nonNegative($gross, 'Gross_Price');
        $this->nonNegative($net, 'Net_Price');

        if ($this->text($values['Width_mm']) === null && $this->text($values['Widht_inches']) === null) {
            throw new RowValidationException('Width_mm', 'width_mm_or_inches_is_required');
        }

        return [
            'role' => DatasetRole::Tires->value,
            'row_number' => $row->rowNumber,
            'external_sku' => $sku,
            'source_description' => $this->requiredText($values['ItemDesc'], 'ItemDesc'),
            'brand' => $this->requiredText($values['Brand'], 'Brand'),
            'model' => $this->requiredText($values['Mønster'], 'Mønster'),
            'ean' => $this->text($values['External_EAN']),
            'supplier_item_id' => $this->text($values['Supplier_itemID']),
            'prices' => ['gross' => $gross, 'net' => $net],
            'spec' => [
                'width_mm' => $this->optionalDecimalText($values['Width_mm'], 'Width_mm'),
                'width_inch' => $this->optionalDecimalText($values['Widht_inches'], 'Widht_inches'),
                'aspect_ratio' => $this->optionalDecimalText($values['Profile'], 'Profile'),
                'rim_diameter_in' => $this->requiredDecimalText($values['Rim_width'], 'Rim_width'),
                'load_index' => $this->requiredText($values['Load_index'], 'Load_index'),
                'speed_symbol' => $this->requiredText($values['Speed_index'], 'Speed_index'),
                'reinforced' => $this->text($values['Reinforced']),
                'construction' => $this->text($values['Construction']),
                'season' => $this->requiredText($values['SEASON'], 'SEASON'),
                'winter_type' => $this->text($values['WINTERTYRE']),
                'three_peak_mountain_snowflake' => $this->text($values['3PMSF']),
                'ice_grip' => $this->text($values['Ice_grip']),
                'run_flat' => $this->text($values['Run_flat']),
                'production_year' => $this->text($values['Production_year']),
            ],
            'label' => [
                'rolling_resistance' => $this->text($values['Rolling_resistance']),
                'wet_grip' => $this->text($values['Wet_grip']),
                'noise_db' => $this->noiseDb($values['Sound']),
                'noise_class' => $this->text($values['Sound_class']),
                'eu_tyre_class' => $this->text($values['EU_tyreclass']),
                'eu_label_regulation' => $this->text($values['EU_label_regulative']),
                'eprel_url' => $this->text($values['Eprel_link']),
            ],
            // The catalog uses PlainUrl for the supplier image endpoint. It is
            // normalized here but downloaded only by the separate media command.
            'media_url' => $mediaUrl,
            'source_fields' => $values,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeWheel(WorkbookRow $row): array
    {
        $values = $row->values;
        $gross = $this->decimal($values['Brutto'], 'Brutto');
        $net = $this->decimal($values['Netto'], 'Netto');
        $mediaField = array_key_exists('PlainUrl', $values) ? 'PlainUrl' : 'PlainURL';
        $rawMediaUrl = $this->text($values[$mediaField] ?? null);
        $mediaUrl = NdiMediaUrl::canonicalize($rawMediaUrl);

        if ($rawMediaUrl !== null && $mediaUrl === null && ! NdiMediaUrl::isExplicitlyEmpty($rawMediaUrl)) {
            throw new RowValidationException($mediaField, 'unrecognized_non_empty_ndi_media_url');
        }

        $this->nonNegative($gross, 'Brutto');
        $this->nonNegative($net, 'Netto');

        return [
            'role' => DatasetRole::Wheels->value,
            'row_number' => $row->rowNumber,
            'external_sku' => $this->identifier($values['Varenummer'], 'Varenummer'),
            'source_description' => $this->requiredText($values['Beskrivelse'], 'Beskrivelse'),
            'brand' => $this->requiredText($values['Fabrikat'], 'Fabrikat'),
            'model' => $this->requiredText($values['Modell'], 'Modell'),
            'ean' => null,
            'supplier_item_id' => null,
            'prices' => ['gross' => $gross, 'net' => $net],
            'spec' => [
                'width_in' => $this->requiredDecimalText($values['Bredde'], 'Bredde'),
                'diameter_in' => $this->requiredDecimalText($values['Diameter'], 'Diameter'),
                'bolt_holes' => $this->integer($values['Bolthull'], 'Bolthull'),
                'pcd_mm' => $this->requiredDecimalText($values['Boltsirkel'], 'Boltsirkel'),
                'offset_et_mm' => $this->requiredDecimalText($values['ET'], 'ET', allowNegative: true),
                'center_bore_mm' => $this->requiredDecimalText($values['Navåpning'], 'Navåpning'),
                'bolt_hole_boring' => $this->text($values['Boring bolthull']),
                'color' => $this->text($values['GFARVEPOL']),
            ],
            'media_url' => $mediaUrl,
            'source_fields' => $values,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizeStock(WorkbookRow $row): array
    {
        $values = $row->values;
        $locations = [
            'norway' => $this->integer($values['InStockNorway'], 'InStockNorway'),
            'vestby' => $this->integer($values['InStockVestby'], 'InStockVestby'),
            'kristiansand' => $this->integer($values['InStockKristiansand'], 'InStockKristiansand'),
            'trondheim' => $this->integer($values['InStockTrondheim'], 'InStockTrondheim'),
            'tromso' => $this->integer($values['InStockTromso'], 'InStockTromso'),
            'bergen' => $this->integer($values['InStockBergen'], 'InStockBergen'),
            'kopervik' => $this->integer($values['InStockKopervik'], 'InStockKopervik'),
        ];

        foreach ($locations as $field => $quantity) {
            if ($quantity < 0) {
                $sourceField = $field === 'norway' ? 'InStockNorway' : 'InStock'.ucfirst($field);
                throw new RowValidationException($sourceField, 'quantity_must_be_non_negative');
            }
        }

        $locationTotal = array_sum(array_diff_key($locations, ['norway' => true]));
        $nationalCap = (int) config('ndi.stock_national_cap', 40);

        if ($nationalCap < 1) {
            throw new \RuntimeException('NDI_STOCK_NATIONAL_CAP must be a positive integer.');
        }

        if ($locations['norway'] !== min($locationTotal, $nationalCap)) {
            throw new RowValidationException('InStockNorway', 'national_availability_does_not_match_capped_location_total');
        }

        return [
            'role' => DatasetRole::Stock->value,
            'row_number' => $row->rowNumber,
            'external_sku' => $this->identifier($values['ItemID'], 'ItemID'),
            'locations' => $locations,
            'source_fields' => $values,
        ];
    }

    private function identifier(mixed $value, string $field): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new RowValidationException($field, 'identifier_must_be_a_non_empty_text_cell');
        }

        return trim($value);
    }

    private function requiredText(mixed $value, string $field): string
    {
        $text = $this->text($value);

        if ($text === null) {
            throw new RowValidationException($field, 'text_value_is_required');
        }

        return $text;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function decimal(mixed $value, string $field): string
    {
        if (is_int($value) || is_float($value)) {
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        if (is_string($value)) {
            $normalized = str_replace(',', '.', trim($value));

            if (preg_match('/^-?\d+(?:\.\d+)?$/D', $normalized) === 1) {
                return $normalized;
            }
        }

        throw new RowValidationException($field, 'value_must_be_a_decimal');
    }

    private function nonNegative(string $value, string $field): void
    {
        if (str_starts_with($value, '-')) {
            throw new RowValidationException($field, 'value_must_be_non_negative');
        }
    }

    private function optionalDecimalText(mixed $value, string $field): ?string
    {
        if ($this->text($value) === null) {
            return null;
        }

        return $this->requiredDecimalText($value, $field);
    }

    private function requiredDecimalText(mixed $value, string $field, bool $allowNegative = false): string
    {
        $decimal = $this->decimal($value, $field);

        if (! $allowNegative) {
            $this->nonNegative($decimal, $field);
        }

        return $decimal;
    }

    private function integer(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/D', trim($value)) === 1) {
            return (int) trim($value);
        }

        throw new RowValidationException($field, 'value_must_be_an_integer');
    }

    private function noiseDb(mixed $value): ?int
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        if (preg_match('/^(\d{1,3})(?:\s*dB)?$/i', $text, $matches) !== 1) {
            throw new RowValidationException('Sound', 'sound_must_contain_decibels');
        }

        return (int) $matches[1];
    }

    private function skuField(DatasetRole $role): string
    {
        return $role === DatasetRole::Wheels ? 'Varenummer' : 'ItemID';
    }

    private function rawSku(DatasetRole $role, WorkbookRow $row): ?string
    {
        $value = $row->values[$this->skuField($role)] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
