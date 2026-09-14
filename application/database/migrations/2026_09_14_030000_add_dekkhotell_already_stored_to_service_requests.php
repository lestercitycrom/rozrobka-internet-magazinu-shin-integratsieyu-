<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->boolean('dekkhotell_already_stored')->nullable()->after('dekkhotell');
        });
    }

    public function down(): void
    {
        // Additive customer intake data is deliberately retained during code rollback.
    }
};
