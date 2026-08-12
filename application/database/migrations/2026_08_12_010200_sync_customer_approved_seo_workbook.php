<?php

use App\Modules\Content\CustomerSeoWorkbook;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (CustomerSeoWorkbook::overrides() as $slug => $content) {
            DB::table('service_pages')->where('slug', $slug)->update([
                ...$content,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Customer-approved content is not destructively rolled back.
    }
};
