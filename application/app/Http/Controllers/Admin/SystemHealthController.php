<?php

namespace App\Http\Controllers\Admin;

use App\Modules\Operations\SystemHealth;

final class SystemHealthController
{
    public function __invoke(SystemHealth $health)
    {
        return view('admin.health', ['health' => $health->report()]);
    }
}
