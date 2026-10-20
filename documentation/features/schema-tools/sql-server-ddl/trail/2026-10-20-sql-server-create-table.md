# SQL Server CREATE TABLE

- Date: 2026-10-20
- Feature: sql-server-ddl
- Related code: `src/Services/DatabaseSchemaGenerator.php`, `src/Services/SchemaDiff.php`, `src/Services/SqlServerTableInspector.php`, `src/Console/CreateTableCommand.php`, `src/Console/AnalyzeCommand.php`

## Context

`generateCreateTableSQL()` always emitted MySQL backticks, and `schema-tools:analyze` printed that definition. CDK-SYNC targets SQL Server. It needs a schema-qualified table, a nonclustered primary key, nullable audit timestamps, a collation on text, `NVARCHAR(MAX)` instead of deprecated `NTEXT`, and `DATETIME2` / `TIME` for the new temporal types. Re-running the create against a table that is already loaded must not drop it.

## Decisions

- `--driver=sqlsrv|mysql` on analyze defaults to the connection driver and selects `getMsSqlTypeDefinition()` or `getSqlDefinition()`.
- `schema-tools:create-table` always prints SQL Server DDL. `{files}` is one token so it can sit in front of `{table}`. Symfony cannot place `{files*}` before another argument.
- `--schema` produces `[schema].[table]`. Identifiers that go into `OBJECT_ID` stay inside a strict character class.
- `--primary-key` makes those columns `NOT NULL` and adds `CONSTRAINT [PK_<schema>_<table>] PRIMARY KEY NONCLUSTERED`.
- Text, json, nullable, or not-in-every-file key columns throw. Estimated key width above 1700 bytes throws.
- Non-key columns are explicit `NULL`.
- `--timestamps` appends `created_at` and `updated_at` as `DATETIME NULL`. A collision throws. `MigrationGenerator::generateSqlServer()` keeps its own `DATETIME2` audit columns because that helper is not this option.
- Collation is opt-in, restricted to letters, digits, and underscores, and applied to character types only.
- The batch is wrapped in `IF OBJECT_ID(...) IS NULL`.
- `--execute` refuses non-`sqlsrv` connections. An existing table prints `SchemaDiff` output and is left in place. Missing columns and mismatches block. Wider columns and extra columns are informational. `numeric` matches `decimal`. `datetime` does not match `datetime2`. Character length `-1` means `MAX`.
- The column list is read from `INFORMATION_SCHEMA.COLUMNS` filtered by the given schema or by `SCHEMA_NAME()`.

## Alternatives considered

- Always emit MySQL and translate downstream. Rejected. The analyze output was already being read as the target definition.
- `CREATE TABLE` without a guard, and `DROP` on re-run. Rejected. A loaded CDK table must be left in place.
- Clustered primary key. Rejected. The requested constraint is `NONCLUSTERED`.
- Use `DATETIME2` for `--timestamps`. Rejected. Those audit columns stay `DATETIME NULL`. Inferred datetimes stay `DATETIME2`.
- Put `{files*}` before `{table}` on create-table. Rejected. The variadic argument would consume the table name.

## Consequences

- Positive: CDK-SYNC can print or run a SQL Server `CREATE TABLE` that matches the inferred types and can be repeated safely.
- Negative: `generateCreateTableSQL()` without an engine still returns MySQL. Callers that want SQL Server must pass `sqlsrv` or call `generateMsSqlCreateTable()`. An existing table is not migrated forward. The operator has to apply the printed differences.
