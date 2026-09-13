<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These fields keep their 2,048-character application validation limit.
     * TEXT is used only to release MySQL's inline row-definition budget.
     *
     * @var list<string>
     */
    private const URL_COLUMNS = [
        'hero_primary_url',
        'hero_secondary_url',
        'map_url',
        'map_embed_url',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('storefront_settings')) {
            throw new RuntimeException('Cannot widen storefront URL columns: storefront_settings does not exist.');
        }

        foreach (self::URL_COLUMNS as $column) {
            $this->widenColumn($column);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: narrowing TEXT back to VARCHAR could fail
        // or truncate values written after this migration was applied.
    }

    private function widenColumn(string $column): void
    {
        $definition = $this->columnDefinition($column);

        if ($definition === null) {
            throw new RuntimeException("Cannot widen storefront_settings.{$column}: column does not exist.");
        }

        $driver = Schema::getConnection()->getDriverName();
        $type = strtolower((string) $definition['type']);
        $typeName = strtolower((string) $definition['type_name']);

        if ($typeName === 'text') {
            $this->assertNullableWithoutDefault($column, $definition);

            return;
        }

        if ($driver === 'mysql') {
            if ($type !== 'varchar(2048)') {
                throw new RuntimeException(
                    "Cannot widen storefront_settings.{$column}: expected varchar(2048), got {$type}."
                );
            }

            $this->assertNullableWithoutDefault($column, $definition);
            $metadata = DB::selectOne(
                <<<'SQL'
                    SELECT CHARACTER_SET_NAME AS character_set_name, COLLATION_NAME AS collation_name
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'storefront_settings'
                      AND COLUMN_NAME = ?
                    SQL,
                [$column],
            );

            $characterSet = (string) ($metadata->character_set_name ?? '');
            $collation = (string) ($metadata->collation_name ?? '');

            if (
                preg_match('/^[a-zA-Z0-9_]+$/D', $characterSet) !== 1
                || preg_match('/^[a-zA-Z0-9_]+$/D', $collation) !== 1
            ) {
                throw new RuntimeException(
                    "Cannot widen storefront_settings.{$column}: invalid character metadata."
                );
            }

            DB::statement(sprintf(
                'ALTER TABLE `storefront_settings` MODIFY `%s` TEXT CHARACTER SET `%s` COLLATE `%s` NULL DEFAULT NULL',
                $column,
                $characterSet,
                $collation,
            ));
        } else {
            if (! in_array($typeName, ['varchar', 'string'], true)) {
                throw new RuntimeException(
                    "Cannot widen storefront_settings.{$column}: expected a string column, got {$type}."
                );
            }

            $this->assertNullableWithoutDefault($column, $definition);

            Schema::table('storefront_settings', function (Blueprint $table) use ($column): void {
                $table->text($column)->nullable()->change();
            });
        }

        $updated = $this->columnDefinition($column);

        if ($updated === null || strtolower((string) $updated['type_name']) !== 'text') {
            $actual = $updated === null ? 'missing' : (string) $updated['type'];

            throw new RuntimeException(
                "Failed to widen storefront_settings.{$column}: expected text, got {$actual}."
            );
        }

        $this->assertNullableWithoutDefault($column, $updated);
    }

    /**
     * @return array{name: string, type: string, type_name: string, collation: string|null, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null}|null
     */
    private function columnDefinition(string $column): ?array
    {
        foreach (Schema::getColumns('storefront_settings') as $definition) {
            if (strtolower($definition['name']) === strtolower($column)) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @param  array{nullable: bool, default: mixed}  $definition
     */
    private function assertNullableWithoutDefault(string $column, array $definition): void
    {
        if (! $definition['nullable'] || $definition['default'] !== null) {
            throw new RuntimeException(
                "Cannot widen storefront_settings.{$column}: nullable/default semantics do not match the expected nullable DEFAULT NULL definition."
            );
        }
    }
};
