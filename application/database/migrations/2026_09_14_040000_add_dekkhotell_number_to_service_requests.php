<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table): void {
            $table->string('dekkhotell_number', 64)->nullable()->after('dekkhotell_already_stored');
        });
    }

    public function down(): void
    {
        // Additive operational data is deliberately retained during code rollback.
    }
};
