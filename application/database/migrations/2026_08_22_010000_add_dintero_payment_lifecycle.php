<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->string('provider_session_reference')->nullable()->unique()->after('provider_reference');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('goods_ready_at')->nullable()->after('submitted_at');
            $table->foreignId('goods_ready_by_user_id')->nullable()->after('goods_ready_at')->constrained('users')->nullOnDelete();
            $table->text('goods_ready_note')->nullable()->after('goods_ready_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('goods_ready_by_user_id');
            $table->dropColumn(['goods_ready_at', 'goods_ready_note']);
        });

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropUnique(['provider_session_reference']);
            $table->dropColumn('provider_session_reference');
        });
    }
};
