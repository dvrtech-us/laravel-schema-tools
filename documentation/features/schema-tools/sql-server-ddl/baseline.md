# SQL Server DDL baseline

## Pipeline invariants

- `schema-tools:create-table` prints SQL Server DDL on every successful analysis, including when `--execute` is absent.
- Without `--execute` the command does not open a database connection.
- `--execute` refuses a connection whose driver is not `sqlsrv`.
- An existing table is never altered or recreated. The command exits 1 and prints the diff.
- The rendered batch is guarded by `IF OBJECT_ID(N'[schema].[table]', N'U') IS NULL`.
- Schema and table names match `/^[A-Za-z_][A-Za-z0-9_]*$/` and are at most 128 characters. They are the only names interpolated into `OBJECT_ID`.
- Column names of 1–128 characters are bracketed. `]`, `[`, quotes, semicolon, and control characters are rejected.
- Non-key columns are explicit `NULL`. Key columns are `NOT NULL`.
- `--primary-key` adds `PRIMARY KEY NONCLUSTERED` named `PK_{schema}_{table}` or `PK_{table}`, truncated to 128 characters with an 8-character SHA-1 suffix when needed.
- A key column that is nullable, missing from a file, `text`, `json`, unknown, or part of a key wider than 1700 estimated bytes throws before SQL is returned.
- `--timestamps` appends `[created_at] DATETIME NULL` and `[updated_at] DATETIME NULL`. A name collision throws.
- `--collation` matches `/^[A-Za-z0-9_]+$/` and is applied only to varchar, text, and json.
- Text and json render as `NVARCHAR(MAX)`. Datetime renders as `DATETIME2`. Time renders as `TIME`. Float renders as `FLOAT`.
- `MigrationGenerator::generateSqlServer()` still appends `DATETIME2` audit columns and does not emit the object-id guard. That helper is not the CDK create path.
- After a successful execute, `OBJECT_ID` must be non-null.
- Diff names are case-insensitive. `numeric` matches `decimal`. `datetime` does not match `datetime2`. `real` does not match `float`. `ntext` does not match `nvarchar(max)`.
- A shorter varchar or decimal is a mismatch. A longer one is informational. Character length `-1` is `MAX`.
- Missing inferred columns and type mismatches are printed as warnings. Wider columns and extra table columns are informational. Any existing table blocks execute.

## Flag rules

| Condition | Severity | Blocks |
| --- | --- | --- |
| Invalid schema, table, column, or collation | error | yes |
| Primary key cannot be indexed or contains nulls | error | yes |
| Timestamp collision | error | yes |
| Non-sqlsrv `--execute` | error | yes |
| Table already exists | error | yes, no DDL is sent |
| Inferred column missing on the table | warning | yes, because the table exists |
| Type mismatch | warning | yes, because the table exists |
| Wider existing column | info | yes, because the table exists |
| Extra column on the table only | info | yes, because the table exists |
| `OBJECT_ID` still null after execute | error | the command reports failure |

## Side effects

1. Analysis reads the input files.
2. The DDL text is written to the console.
3. On `--execute`, when the table is absent, `unprepared` sends the batch.
4. A second `OBJECT_ID` read confirms the table.
5. No drop, alter, or insert is issued.

## Configuration defaults

DDL flags have no config defaults. Analysis flags use the schema-analysis defaults: `string_columns` empty, `decimal_columns` empty, `varchar_default` 255.

`--connection` defaults to `config('database.default')`. `--driver` is not a flag on this command. The SQL is always SQL Server.

## Access

Artisan only. `--execute` uses the credentials of the selected Laravel connection. The package does not grant database permissions.
