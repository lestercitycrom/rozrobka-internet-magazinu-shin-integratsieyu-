<?php

namespace App\Modules\Catalog\Enums;

enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Hidden = 'hidden';
}
