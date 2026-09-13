<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertTableExists('storefront_settings');
        $this->assertTableExists('homepage_business_partners');
        $this->assertTableExists('public_service_prices');

        $this->ensureColumn(
            'storefront_settings',
            'hotel_video_media_asset_id',
            'ulid',
            static fn (Blueprint $table) => $table->char('hotel_video_media_asset_id', 26)->nullable(),
        );
        $this->ensureHotelVideoForeignKey();

        $this->ensureColumn(
            'storefront_settings',
            'customer_homepage_composition_manifest',
            'json',
            static fn (Blueprint $table) => $table->json('customer_homepage_composition_manifest')->nullable(),
        );

        foreach (['nb', 'ru'] as $locale) {
            foreach (['mobile_details_title', 'mobile_details_body_1', 'mobile_details_body_2', 'process_cta', 'business_cta', 'business_partners_heading', 'contact_map_note'] as $field) {
                $column = "{$field}_{$locale}";
                $this->ensureColumn(
                    'storefront_settings',
                    $column,
                    'text',
                    static fn (Blueprint $table) => $table->text($column)->nullable(),
                );
            }
        }

        $this->ensureColumn(
            'homepage_business_partners',
            'content_key',
            'string:160',
            static fn (Blueprint $table) => $table->string('content_key', 160)->nullable(),
        );
        $this->ensureContentKeyUniqueIndex();
        $this->ensureColumn(
            'homepage_business_partners',
            'manifest_hash',
            'char:64',
            static fn (Blueprint $table) => $table->char('manifest_hash', 64)->nullable(),
        );
        $this->ensureColumn(
            'homepage_business_partners',
            'deleted_at',
            'timestamp',
            static fn (Blueprint $table) => $table->softDeletes(),
        );

        $this->ensureColumn(
            'public_service_prices',
            'manifest_hash',
            'char:64',
            static fn (Blueprint $table) => $table->char('manifest_hash', 64)->nullable(),
        );
        $this->ensureColumn(
            'public_service_prices',
            'customer_homepage_composition_manifest',
            'json',
            static fn (Blueprint $table) => $table->json('customer_homepage_composition_manifest')->nullable(),
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('public_service_prices')) {
            $this->dropExistingColumns('public_service_prices', [
                'manifest_hash',
                'customer_homepage_composition_manifest',
            ]);
        }

        if (Schema::hasTable('homepage_business_partners')) {
            if (Schema::hasIndex('homepage_business_partners', ['content_key'], 'unique')) {
                Schema::table('homepage_business_partners', function (Blueprint $table): void {
                    $table->dropUnique(['content_key']);
                });
            }

            $this->dropExistingColumns('homepage_business_partners', [
                'content_key',
                'manifest_hash',
                'deleted_at',
            ]);
        }

        if (Schema::hasTable('storefront_settings')) {
            if (Schema::hasForeignKey('storefront_settings', ['hotel_video_media_asset_id'])) {
                Schema::table('storefront_settings', function (Blueprint $table): void {
                    $table->dropForeign(['hotel_video_media_asset_id']);
                });
            }

            $this->dropExistingColumns('storefront_settings', [
                'hotel_video_media_asset_id',
                'customer_homepage_composition_manifest',
                'mobile_details_title_nb', 'mobile_details_body_1_nb', 'mobile_details_body_2_nb', 'process_cta_nb', 'business_cta_nb', 'business_partners_heading_nb', 'contact_map_note_nb',
                'mobile_details_title_ru', 'mobile_details_body_1_ru', 'mobile_details_body_2_ru', 'process_cta_ru', 'business_cta_ru', 'business_partners_heading_ru', 'contact_map_note_ru',
            ]);
        }
    }

    private function assertTableExists(string $table): void
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("Cannot apply homepage composition schema: {$table} does not exist.");
        }
    }

    /**
     * @param  callable(Blueprint): mixed  $add
     */
    private function ensureColumn(string $table, string $column, string $expected, callable $add): void
    {
        if (! Schema::hasColumn($table, $column)) {
            Schema::table($table, function (Blueprint $blueprint) use ($add): void {
                $add($blueprint);
            });
        }

        $definition = $this->columnDefinition($table, $column);

        if ($definition === null || ! $this->isCompatibleType($definition, $expected)) {
            $actual = $definition === null
                ? 'missing'
                : sprintf(
                    '%s, nullable=%s, default=%s',
                    $definition['type'],
                    $definition['nullable'] ? 'yes' : 'no',
                    var_export($definition['default'], true),
                );

            throw new RuntimeException(
                "Incompatible schema for {$table}.{$column}: expected {$expected} nullable DEFAULT NULL; got {$actual}."
            );
        }
    }

    /**
     * @param  array{name: string, type: string, type_name: string, collation: string|null, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null}  $definition
     */
    private function isCompatibleType(array $definition, string $expected): bool
    {
        if (
            ! $definition['nullable']
            || $definition['default'] !== null
            || $definition['auto_increment']
            || $definition['generation'] !== null
        ) {
            return false;
        }

        $driver = Schema::getConnection()->getDriverName();
        $type = strtolower((string) $definition['type']);
        $typeName = strtolower((string) $definition['type_name']);

        if ($driver === 'mysql') {
            return match ($expected) {
                'ulid' => $type === 'char(26)',
                'json' => $typeName === 'json',
                'text' => $typeName === 'text',
                'string:160' => $type === 'varchar(160)',
                'char:64' => $type === 'char(64)',
                'timestamp' => $typeName === 'timestamp',
                default => false,
            };
        }

        return match ($expected) {
            'ulid', 'string:160', 'char:64' => in_array($typeName, ['char', 'varchar', 'string'], true),
            'json' => in_array($typeName, ['json', 'text'], true),
            'text' => $typeName === 'text',
            'timestamp' => in_array($typeName, ['datetime', 'timestamp'], true),
            default => false,
        };
    }

    private function ensureHotelVideoForeignKey(): void
    {
        $foreignKeys = array_values(array_filter(
            Schema::getForeignKeys('storefront_settings'),
            static fn (array $foreignKey): bool => $foreignKey['columns'] === ['hotel_video_media_asset_id'],
        ));

        if ($foreignKeys === []) {
            Schema::table('storefront_settings', function (Blueprint $table): void {
                $table->foreign('hotel_video_media_asset_id')
                    ->references('id')
                    ->on('media_assets')
                    ->nullOnDelete();
            });

            $foreignKeys = array_values(array_filter(
                Schema::getForeignKeys('storefront_settings'),
                static fn (array $foreignKey): bool => $foreignKey['columns'] === ['hotel_video_media_asset_id'],
            ));
        }

        $compatible = array_filter($foreignKeys, static fn (array $foreignKey): bool => $foreignKey['foreign_table'] === 'media_assets'
            && $foreignKey['foreign_columns'] === ['id']
            && strtolower((string) $foreignKey['on_delete']) === 'set null'
        );

        if (count($foreignKeys) !== 1 || count($compatible) !== 1) {
            throw new RuntimeException(
                'Incompatible foreign key for storefront_settings.hotel_video_media_asset_id: expected media_assets(id) ON DELETE SET NULL.'
            );
        }
    }

    private function ensureContentKeyUniqueIndex(): void
    {
        $indexes = array_values(array_filter(
            Schema::getIndexes('homepage_business_partners'),
            static fn (array $index): bool => $index['columns'] === ['content_key'],
        ));
        $uniqueIndexes = array_values(array_filter(
            $indexes,
            static fn (array $index): bool => $index['unique'] && ! $index['primary'],
        ));

        if ($uniqueIndexes === []) {
            Schema::table('homepage_business_partners', function (Blueprint $table): void {
                $table->unique('content_key');
            });

            $uniqueIndexes = array_values(array_filter(
                Schema::getIndexes('homepage_business_partners'),
                static fn (array $index): bool => $index['columns'] === ['content_key']
                    && $index['unique']
                    && ! $index['primary'],
            ));
        }

        if (count($uniqueIndexes) !== 1) {
            throw new RuntimeException(
                'Incompatible index for homepage_business_partners.content_key: expected exactly one single-column unique index.'
            );
        }
    }

    /**
     * @return array{name: string, type: string, type_name: string, collation: string|null, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null}|null
     */
    private function columnDefinition(string $table, string $column): ?array
    {
        foreach (Schema::getColumns($table) as $definition) {
            if (strtolower($definition['name']) === strtolower($column)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropExistingColumns(string $table, array $columns): void
    {
        $existing = array_values(array_filter(
            $columns,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
        ));

        if ($existing !== []) {
            Schema::table($table, function (Blueprint $blueprint) use ($existing): void {
                $blueprint->dropColumn($existing);
            });
        }
    }
};
