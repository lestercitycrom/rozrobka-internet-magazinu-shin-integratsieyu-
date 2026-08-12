<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->enum('source_tax_mode', ['gross', 'net'])
                ->default('gross')
                ->after('source_price_list_code');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_rules', function (Blueprint $table): void {
            $table->dropColumn('source_tax_mode');
        });
    }
};
