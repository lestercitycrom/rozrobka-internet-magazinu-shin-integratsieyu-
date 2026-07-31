<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('canonical_name')->unique();
            $table->string('slug')->index();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->enum('product_type', ['tire', 'wheel']);
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('model_name');
            $table->string('title');
            $table->string('publication_status', 32)->default('draft');
            $table->timestamp('first_seen_at');
            $table->timestamp('last_catalog_seen_at')->nullable();
            $table->timestamps();
            $table->index(['product_type', 'publication_status']);
            $table->index(['brand_id', 'model_name']);
        });

        Schema::create('supplier_skus', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('external_sku');
            $table->string('ean')->nullable();
            $table->string('supplier_item_id')->nullable();
            $table->text('source_description')->nullable();
            $table->string('catalog_state', 32)->default('catalog');
            $table->timestamps();
            $table->unique(['supplier_id', 'external_sku'], 'supplier_skus_supplier_external_unique');
            $table->index('ean');
            $table->index('supplier_item_id');
            $table->index(['supplier_id', 'catalog_state']);
        });

        Schema::create('tire_specs', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained()->restrictOnDelete();
            $table->decimal('width_mm', 8, 2)->nullable();
            $table->decimal('width_inch', 8, 2)->nullable();
            $table->decimal('aspect_ratio', 8, 2)->nullable();
            $table->decimal('rim_diameter_in', 8, 2);
            $table->string('load_index', 32);
            $table->string('speed_symbol', 16);
            $table->string('construction', 64)->nullable();
            $table->string('season', 32);
            $table->boolean('reinforced')->nullable();
            $table->string('winter_type', 64)->nullable();
            $table->boolean('three_peak_mountain_snowflake')->nullable();
            $table->boolean('ice_grip')->nullable();
            $table->boolean('run_flat')->nullable();
            $table->string('production_year', 32)->nullable();
            $table->timestamps();
            $table->index(['width_mm', 'aspect_ratio', 'rim_diameter_in'], 'tire_specs_metric_size_index');
            $table->index(['width_inch', 'rim_diameter_in'], 'tire_specs_inch_size_index');
            $table->index(['season', 'three_peak_mountain_snowflake']);
        });

        Schema::create('tire_labels', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained()->restrictOnDelete();
            $table->string('rolling_resistance', 16)->nullable();
            $table->string('wet_grip', 16)->nullable();
            $table->unsignedSmallInteger('noise_db')->nullable();
            $table->string('noise_class', 16)->nullable();
            $table->string('eu_tyre_class', 16)->nullable();
            $table->string('eu_label_regulation', 32)->nullable();
            $table->text('eprel_url')->nullable();
            $table->timestamps();
        });

        Schema::create('wheel_specs', function (Blueprint $table): void {
            $table->foreignId('product_id')->primary()->constrained()->restrictOnDelete();
            $table->decimal('width_in', 8, 2);
            $table->decimal('diameter_in', 8, 2);
            $table->unsignedTinyInteger('bolt_holes');
            $table->decimal('pcd_mm', 8, 2);
            $table->decimal('offset_et_mm', 8, 2);
            $table->decimal('center_bore_mm', 8, 2);
            $table->string('bolt_hole_boring')->nullable();
            $table->string('color')->nullable();
            $table->timestamps();
            $table->index(
                ['width_in', 'diameter_in', 'bolt_holes', 'pcd_mm', 'offset_et_mm', 'center_bore_mm'],
                'wheel_specs_fitment_index'
            );
        });

        Schema::create('price_lists', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->char('currency', 3);
            $table->enum('tax_mode', ['gross', 'net']);
            $table->timestamp('valid_from');
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();
            $table->unique(
                ['supplier_id', 'code', 'currency', 'tax_mode', 'valid_from'],
                'price_lists_identity_unique'
            );
            $table->index(['supplier_id', 'valid_to']);
        });

        Schema::create('sku_prices', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_sku_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 4);
            $table->timestamp('observed_at');
            $table->foreignUlid('source_artifact_id')->constrained('source_artifacts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['supplier_sku_id', 'price_list_id'], 'sku_prices_sku_list_unique');
            $table->index(['price_list_id', 'observed_at']);
        });

        Schema::create('sku_price_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_sku_id')->constrained()->restrictOnDelete();
            $table->foreignId('price_list_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 4);
            $table->timestamp('observed_at');
            $table->foreignUlid('source_artifact_id')->constrained('source_artifacts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['source_artifact_id', 'supplier_sku_id', 'price_list_id'],
                'sku_price_history_artifact_sku_list_unique'
            );
            $table->index(['supplier_sku_id', 'observed_at']);
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('code', 64);
            $table->string('name');
            $table->char('country_code', 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['supplier_id', 'code']);
        });

        Schema::create('stock_snapshots', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_sku_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('observed_at');
            $table->foreignUlid('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->foreignUlid('source_artifact_id')->constrained('source_artifacts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(
                ['source_artifact_id', 'supplier_sku_id', 'warehouse_id'],
                'stock_snapshots_artifact_sku_warehouse_unique'
            );
            $table->index(['supplier_sku_id', 'observed_at']);
            $table->index(['warehouse_id', 'observed_at']);
            $table->index(['import_run_id', 'observed_at']);
        });

        Schema::create('current_stock', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('supplier_sku_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('observed_at');
            $table->foreignUlid('source_artifact_id')->constrained('source_artifacts')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['supplier_sku_id', 'warehouse_id'], 'current_stock_sku_warehouse_unique');
            $table->index(['warehouse_id', 'quantity']);
        });

        Schema::create('entity_changes', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUlid('import_run_id')->constrained('import_runs')->restrictOnDelete();
            $table->string('entity_type', 64);
            $table->string('entity_id', 64);
            $table->string('operation', 32);
            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->json('field_diff_json')->nullable();
            $table->foreignUlid('source_artifact_id')->constrained('source_artifacts')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['import_run_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_changes');
        Schema::dropIfExists('current_stock');
        Schema::dropIfExists('stock_snapshots');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('sku_price_history');
        Schema::dropIfExists('sku_prices');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('wheel_specs');
        Schema::dropIfExists('tire_labels');
        Schema::dropIfExists('tire_specs');
        Schema::dropIfExists('supplier_skus');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
    }
};
