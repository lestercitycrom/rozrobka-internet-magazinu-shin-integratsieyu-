<?php

namespace App\Modules\Content\Enums;

enum ContentPageStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
