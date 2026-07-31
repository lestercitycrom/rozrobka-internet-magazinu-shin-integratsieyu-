<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('locale', 8)->default('nb');
            $table->string('service_slug', 120);
            $table->string('fulfilment', 32);
            $table->date('preferred_date')->nullable();
            $table->string('preferred_window', 64)->nullable();
            $table->string('vehicle', 120)->nullable();
            $table->string('registration_number', 32)->nullable();
            $table->string('tire_size', 64)->nullable();
            $table->boolean('dekkhotell')->default(false);
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 64);
            $table->string('address')->nullable();
            $table->text('note')->nullable();
            $table->string('status', 32)->default('new');
            $table->text('admin_note')->nullable();
            $table->timestamp('consent_at');
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['service_slug', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
