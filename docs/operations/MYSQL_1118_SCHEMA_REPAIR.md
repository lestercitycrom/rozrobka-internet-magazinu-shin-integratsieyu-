# MySQL 1118 homepage schema repair

## Scope

The homepage composition release can encounter MySQL error `1118` while adding its final localized `TEXT` columns to `storefront_settings`. The table already contains four nullable URL fields defined as `VARCHAR(2048)`, which consume enough of MySQL's maximum logical row-definition budget to prevent the remaining additions.

The forward repair is intentionally narrow:

1. `2026_09_12_000050_widen_storefront_settings_url_columns.php` converts only `hero_primary_url`, `hero_secondary_url`, `map_url`, and `map_embed_url` from nullable `VARCHAR(2048)` to nullable `TEXT`, preserving data, character set, collation, column comments and `DEFAULT NULL` semantics. Generated or otherwise special definitions fail closed, including an already-`TEXT` generated column.
2. `2026_09_12_000100_add_customer_homepage_composition_fields.php` then verifies or adds each column separately. Its hotel-video constraint must reference `media_assets(id)` in the current database with `ON UPDATE RESTRICT`/`NO ACTION` and `ON DELETE SET NULL`. Its partner key must have exactly one visible, full-column, single-part unique `BTREE` index; prefix, expression, composite, invisible and non-`BTREE` variants stop without being replaced.

The application still validates those four URL values at a maximum of 2,048 characters. The storage change does not expand the public or admin input contract.

## Supported starting states

The ordinary Laravel migrator is the only supported writer. It is safe to run this pair from:

- a clean database;
- a database containing the pre-composition schema;
- the known partial state where the first 11 composition additions and hotel-video foreign key exist but the final five Russian fields and the later partner/price changes do not;
- a compatible interruption between later per-column or constraint steps, including an existing video column with its foreign key still missing;
- a partial URL conversion where two of the four target fields are already correct `TEXT` columns;
- an older environment where the complete composition schema genuinely exists and its migration is recorded while the newly introduced prerequisite is still pending.

Existing compatible definitions are preserved. A column, foreign key or unique index with conflicting semantics stops the migration with an explicit error instead of being silently accepted. Migration ledger rows are written only by Laravel after a migration finishes; do not edit the `migrations` table manually.

## Verification

GitHub Actions runs `tests/Integration/mysql-schema-repair-regression.php` against a real MySQL 8 service. The regression:

- reproduces the original error `1118` with the unmodified migration fixture and the complete pre-repair schema;
- proves clean installation and ordinary update paths;
- recovers from the known partial schema, a later interrupted step and a half-completed URL conversion;
- proves that an already-correct `TEXT` column is a no-op and a nonempty `VARCHAR` comment survives conversion;
- verifies idempotent repeat execution and fail-closed generated-column, foreign-key and unique-index conflicts using real MySQL metadata, without silently replacing an incompatible index;
- preserves synthetic `NULL`, empty, 2,048-character, multibyte, settings, release journal, partner, price and audit/outbox data;
- runs the unchanged scoped homepage release twice, an operator edit and guarded rollback, then proves operator-owned and unrelated values remain intact;
- boots the retained pre-change application `f70f358f06af384364973951eaab8d4fac2b2203` against the additive/widened schema and verifies that it can still read existing settings;
- records MySQL version, SQL mode, database character set/collation and final row format in the CI log.

## Deployment and recovery boundary

Before an approved staging attempt, preserve the failed candidate, its database-mutation marker, deployment log, pre-migration backup and the active fallback release. The release-retention limit must not delete those protected artifacts during the next attempt.

The prerequisite migration has an intentionally empty `down()` method. Automatically narrowing `TEXT` back to `VARCHAR(2048)` could fail or truncate values and is not an approved rollback mechanism. The schema additions and the URL widening remain backward-compatible with the prior application release, so an application rollback does not require destructive schema rollback.

Do not retry staging migration, run manual DDL, restore the database, switch `current`, or start workers/schedulers without a separate exact-SHA runtime gate.
