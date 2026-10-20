# SQL Server DDL

Builds a guarded `CREATE TABLE` for SQL Server from inferred columns, and can run it when the table is absent.

## User flow

1. Run `php artisan schema-tools:create-table {files} {table}`.
2. `{files}` is one token: a path, a quoted glob, or a comma-separated list. `{table}` is the table name.
3. Optional flags:
   - `--schema=C208384` qualifies the table as `[schema].[table]`.
   - `--primary-key=hostitemid,cora_acct_id` marks those columns `NOT NULL` and adds `CONSTRAINT [PK_<schema>_<table>] PRIMARY KEY NONCLUSTERED (...)`.
   - `--timestamps` appends `created_at` and `updated_at` as `DATETIME NULL`.
   - `--collation=SQL_Latin1_General_CP1_CI_AS` applies to character columns.
   - `--connection=` selects the Laravel connection. The default connection is used when the flag is omitted.
   - `--string-columns`, `--decimal-columns`, and `--varchar-default` are the analysis options.
4. With no `--execute`, the command prints the DDL and exits 0. It does not connect.
5. With `--execute`, the connection driver must be `sqlsrv`. The command checks `OBJECT_ID`. When the table exists it prints differences and exits 1. When it does not, it runs the batch with `DB::unprepared()` and checks `OBJECT_ID` again.

`schema-tools:analyze --driver=sqlsrv` prints each column's `getMsSqlTypeDefinition()`, not the full `CREATE TABLE`.

## Technical flow

1. `CreateTableCommand` expands `{files}` through `InputPaths` and analyzes with `SchemaAnalyzer::analyzeFiles()`.
2. `DatabaseSchemaGenerator::generateMsSqlCreateTable()` validates identifiers, primary-key columns, key width, timestamp collisions, and collation, then renders the batch.
3. On `--execute`, `SqlServerTableInspector::exists()` runs `SELECT OBJECT_ID(?, N'U')` with the bracketed name bound as a parameter.
4. When the object id is non-null, `columns()` reads `INFORMATION_SCHEMA.COLUMNS` for that table. The schema filter is the given schema, or `SCHEMA_NAME()` when `--schema` was omitted.
5. `SchemaDiff::compare()` classifies each inferred column against the live column. The command prints the diff and returns 1. Existence itself blocks the create. Wider and extra columns are informational.
6. When the table is absent, `unprepared($sql)` runs the `IF OBJECT_ID ... BEGIN ... END` batch. A following `exists()` call must be non-null or the command returns 1.

`generateCreateTableSQL($table, $structure, 'sqlsrv')` delegates to `generateMsSqlCreateTable()` with no schema, key, timestamps, or collation. Any other engine value renders MySQL backticks and `getSqlDefinition()`.

`MigrationGenerator::generateSqlServer()` is a separate helper. It does not take schema, primary key, collation, or the object-id guard. It always appends `created_at` and `updated_at` as `DATETIME2 NULL`. CDK table creation uses `schema-tools:create-table`, not that helper.

## Key classes

| Class | Path | Role |
| --- | --- | --- |
| `CreateTableCommand` | `src/Console/CreateTableCommand.php` | Print or execute DDL |
| `DatabaseSchemaGenerator` | `src/Services/DatabaseSchemaGenerator.php` | Render MySQL and SQL Server `CREATE TABLE` |
| `SqlServerTableInspector` | `src/Services/SqlServerTableInspector.php` | `OBJECT_ID` and `INFORMATION_SCHEMA.COLUMNS` |
| `SchemaDiff` | `src/Services/SchemaDiff.php` | Missing, mismatch, wider, and extra columns |
| `ColumnStructureDTO` | `src/DTO/ColumnStructureDTO.php` | Per-column SQL Server type text |

## Integration points

- Analysis is the schema-analysis feature. DDL does not re-infer types.
- Execution uses `Illuminate\Support\Facades\DB` and the named connection.
- The batch is one `IF`/`BEGIN`/`END` script, so it is sent with `unprepared`, not a prepared single statement.

There is no HTTP route.

## DDL shape

```sql
IF OBJECT_ID(N'[schema].[table]', N'U') IS NULL
BEGIN
    CREATE TABLE [schema].[table] (
        [column] TYPE NULL,
        [created_at] DATETIME NULL,
        [updated_at] DATETIME NULL,
        CONSTRAINT [PK_schema_table] PRIMARY KEY NONCLUSTERED ([column])
    );
END
```

Without `--schema`, the object name is `[table]` and the constraint is `PK_{table}`.

| Piece | Rule |
| --- | --- |
| Schema and table | `/^[A-Za-z_][A-Za-z0-9_]*$/` and length at most 128 |
| Column names | Length 1–128. `]`, `[`, quotes, semicolon, and control characters are rejected. Spaces are allowed |
| Collation | Optional. `/^[A-Za-z0-9_]+$/`. Applied only to varchar, text, and json |
| Non-key columns | Explicit `NULL` |
| Key columns | `NOT NULL`, using the spelling from the analyzed structure |
| Timestamps | `DATETIME NULL`, not `DATETIME2`. Names `created_at` and `updated_at` |
| Text and json | `NVARCHAR(MAX)` |
| Datetime and time | `DATETIME2` and `TIME` |
| Float | `FLOAT` |
| Constraint name | `PK_{schema}_{table}` or `PK_{table}`. Longer than 128 characters is cut to 119 characters plus `_` and 8 hex characters of the SHA-1 |

Primary key resolution is case-insensitive. The constraint lists the analyzed spelling. A key column that is missing, `text`, `json`, nullable, or absent from any input file throws before SQL is printed. Estimated key width above 1700 bytes throws.

Width estimate, in bytes:

| Type | Bytes |
| --- | --- |
| varchar | SQL length × 2 |
| int | 4 |
| bigint | 8 |
| float | 8 |
| decimal | 17 |
| date | 3 |
| datetime | 8 |
| time | 5 |
| anything else | 1701, which fails the check |

A timestamp flag throws when the inferred columns already contain `created_at` or `updated_at`, case-insensitive.

The rendered statement is wrapped so a second run of the same SQL is a no-op on the server. `--execute` still refuses before sending SQL when the table is already present, and prints the diff instead.

## Diff rules

Names are compared case-insensitively.

| Inferred | Existing | Result |
| --- | --- | --- |
| Column not on the table | | missing, blocks |
| `varchar` vs `varchar` or `nvarchar` of equal length | | ok |
| Character length `-1` (`MAX`) against a bounded varchar | | wider, informational |
| Shorter character length, or a non-character type | | mismatch, blocks |
| Longer character length | | wider, informational |
| `text` or `json` vs `nvarchar(max)` or `varchar(max)` | | ok |
| `text` or `json` vs `ntext` or `text` | | mismatch |
| `int`, `bigint`, `float`, `date`, `time` | same type name | ok |
| `decimal` | `decimal` or `numeric` with at least the inferred precision and scale | ok. Smaller is a mismatch. Larger is wider |
| `datetime` | `datetime2` with scale at least the needed fraction | ok |
| `datetime` | legacy `datetime` | mismatch |
| `float` | `real` | mismatch |
| Column only on the table | | extra, informational |

`SchemaDiff` does not connect. `SqlServerTableInspector` uppercases driver keys before reading `COLUMN_NAME`, `DATA_TYPE`, `CHARACTER_MAXIMUM_LENGTH`, `NUMERIC_PRECISION`, `NUMERIC_SCALE`, and `DATETIME_PRECISION`. Character length `-1` stays `-1`.

## Database schema

The command creates the requested table only. It does not alter an existing table. Audit columns from `--timestamps` are nullable `DATETIME` and are not part of the inferred structure.

Example output, from the sample values in the tests, is in [sql/create-table.example.sql](sql/create-table.example.sql).

## Failure behavior

| Condition | Result |
| --- | --- |
| Bad path, bad table or schema, bad collation | error printed, exit 1, no SQL executed |
| Primary key missing, nullable, absent from a file, text, json, or wider than 1700 bytes | exception printed, exit 1 |
| Timestamp name collision | exception printed, exit 1 |
| `--execute` on a non-`sqlsrv` driver | error printed, exit 1, no DDL |
| Table already exists | diff printed, exit 1, no DDL |
| Statement ran but `OBJECT_ID` is still null | error printed, exit 1 |
| Default, no `--execute` | SQL printed, exit 0, no connection |
