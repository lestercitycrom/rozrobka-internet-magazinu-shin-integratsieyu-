<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_pages')->where('slug', 'dekkhotell')->update(['request_flow' => 'seasonal']);
    }

    public function down(): void
    {
        DB::table('service_pages')->where('slug', 'dekkhotell')->update(['request_flow' => 'generic']);
    }
};
