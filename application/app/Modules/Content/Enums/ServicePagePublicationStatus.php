<?php

namespace App\Modules\Content\Enums;

enum ServicePagePublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Hidden = 'hidden';
}
