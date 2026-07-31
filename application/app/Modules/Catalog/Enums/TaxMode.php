<?php

namespace App\Modules\Catalog\Enums;

enum TaxMode: string
{
    case Gross = 'gross';
    case Net = 'net';
}
