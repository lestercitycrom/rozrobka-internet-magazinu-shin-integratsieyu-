<?php

namespace App\Modules\NdiImport;

use App\Modules\NdiImport\Enums\DatasetRole;

final class NdiWorkbookSchemas
{
    /** @var list<string> */
    public const TIRES = [
        'ItemID', 'ItemDesc', 'Brand', 'Mønster', 'Width_mm', 'Widht_inches', 'Profile', 'Rim_width',
        'Load_index', 'Speed_index', 'Reinforced', 'Construction', 'Type', 'Gross_Price', 'Net_Price',
        'Rolling_resistance', 'SEASON', 'Sound', 'Sound_class', 'Wet_grip', 'XINFORM', 'WINTERTYRE',
        'External_EAN', 'Supplier_itemID', 'Weight', 'Enviremental_fee', '3PMSF', 'Ice_grip', 'Eprel_link',
        'EU_tyreclass', 'EU_label_regulative', 'Run_flat', 'PlainUrl', 'Production_year',
    ];

    /** @var list<string> */
    public const WHEELS = [
        'Varenummer', 'Beskrivelse', 'Fabrikat', 'Modell', 'Bredde', 'Diameter', 'Bolthull', 'Boltsirkel',
        'ET', 'Navåpning', 'Boring bolthull', 'GFARVEPOL', 'Brutto', 'Netto',
    ];

    /** @var list<string> */
    public const STOCK = [
        'ItemID', 'InStockNorway', 'InStockVestby', 'InStockKristiansand', 'InStockTrondheim',
        'InStockTromso', 'InStockBergen', 'InStockKopervik',
    ];

    /** @param list<string> $headers */
    public static function detect(array $headers): ?DatasetRole
    {
        return match (true) {
            $headers === self::TIRES => DatasetRole::Tires,
            $headers === self::WHEELS => DatasetRole::Wheels,
            $headers === self::STOCK => DatasetRole::Stock,
            default => null,
        };
    }
}
