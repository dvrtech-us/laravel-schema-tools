# Schema analysis

Infers a column type, observed length, numeric precision, and nullability from JSON rows or CSV extracts. CDK payroll files are the sizing case: one file is about 25,000 rows and a full table is about 2,000,000 rows. Rows are not kept in memory.

## User flow

1. Run `php artisan schema-tools:analyze {files*}`.
2. Pass files, a quoted glob, or a comma-separated list. Example: `schema-tools:analyze "storage/cdk_files/TechPayroll/*.csv"`.
3. Optional flags:
   - `--driver=mysql|sqlsrv` selects the SQL definition column. The default is `config('database.connections.{default}.driver')`. Anything else is rejected.
   - `--string-columns=ronumber,techniciannumber` forces those columns to varchar.
   - `--decimal-columns=hours,rate` forces those columns to `DECIMAL`.
   - `--varchar-default=255` sets the minimum varchar length used in SQL.
4. The command prints one row per column: name, type, observed length, scale, and the driver-specific SQL definition.
5. When CSV rows were skipped, the command prints the count.

`schema-tools:generate` accepts one `.json` or `.csv` file (any extension case) and uses the same three inference options. `schema-tools:migration` and `schema-tools:model` analyze one JSON file with those options.

From PHP, construct `SchemaAnalyzer` and pass options on the call. The analyzer does not read Laravel config.

```php
$structure = $analyzer->analyzeFiles($paths, [
    'string_columns' => ['techniciannumber'],
    'decimal_columns' => ['hours'],
    'varchar_default' => 255,
]);
```

## Technical flow

1. `AnalyzeCommand` expands arguments with `InputPaths::expand()` and builds options with `AnalysisOptions::fromCommand()`.
2. `SchemaAnalyzer::analyzeFiles()` resets the skipped-row count and walks each path.
3. CSV: `accumulateCsv()` opens the file, reads the header with `fgetcsv($handle, 0, $delimiter, '"', '')`, strips a UTF-8 BOM from the first header cell, and trims header names. Each data row is observed and discarded.
4. JSON: `accumulateJson()` decodes the whole document, then observes row by row. JSON objects and arrays are one in-memory document. CSV is the streamed path.
5. `registerHeaders()` records the union of columns. Matching is case-insensitive. The first spelling is kept.
6. `observe()` updates one `ColumnStats` object per column.
7. `resolve()` promotes the flags to one `ColumnStructureDTO`.

`analyzeCSVFile()` and `analyzeJsonFile()` delegate to the same merge. `analyzeDataStructure()` is the in-memory entry used by tests and by JSON.

## Key classes

| Class | Path | Role |
| --- | --- | --- |
| `SchemaAnalyzer` | `src/Services/SchemaAnalyzer.php` | Classifies values and promotes columns |
| `ColumnStats` | `src/Services/ColumnStats.php` | Running flags, lengths, and digit counts |
| `ColumnStructureDTO` | `src/DTO/ColumnStructureDTO.php` | Resolved type plus SQL, migration, and cast text |
| `AnalysisOptions` | `src/Support/AnalysisOptions.php` | Command options, then `schema-tools-tools.*`, then `schema-tools.*` |
| `InputPaths` | `src/Support/InputPaths.php` | Glob, comma-list, and literal path expansion |
| `AnalyzeCommand` | `src/Console/AnalyzeCommand.php` | Artisan entry point |

## Integration points

- `schema-tools:generate`, `schema-tools:migration`, and `schema-tools:model` call the analyzer, then `MigrationGenerator` or `ModelGenerator`.
- `schema-tools:create-table` uses the same analysis before rendering SQL Server DDL. See [sql-server-ddl](../sql-server-ddl/sql-server-ddl.md).
- Published config is `config/schema-tools-tools.php`. `SchemaToolsServiceProvider` also `mergeConfigFrom`s the package file as `schema-tools`. Do not rename the publish path.

There is no HTTP route. The commands are available to whoever can run Artisan.

## Inference rules

Classification uses the trimmed value. Length uses `strlen()` of the raw value, so a trailing space counts. An empty or whitespace-only cell is a null sighting and still updates the length.

Temporal values are checked before the leading-zero rule, so `09:01:02` stays a time. A value matches only when `checkdate()` accepts the calendar date (year at least 1) and the clock is `00:00:00` through `23:59:59`. `2026-02-30`, `0000-00-00`, and `24:00:00` stay varchar. Eight fractional digits stay varchar. `HH:MM` is a time.

| Pattern | Type |
| --- | --- |
| `YYYY-MM-DD`, `MM/DD/YYYY`, `MM-DD-YYYY`, `YYYY/MM/DD` | `date` |
| `YYYY-MM-DD HH:MM:SS` with an optional `.` and 1–7 digits | `datetime` |
| `YYYY/MM/DD HH:MM:SS` | `datetime` |
| `YYYY-MM-DDTHH:MM:SSZ` and the same form with a fractional second | `datetime` |
| `H:MM:SS`, `HH:MM:SS`, `H:MM`, `HH:MM` | `time` |

Numeric rules, applied after temporal detection:

| Pattern | Result |
| --- | --- |
| `/^[+-]?0\d/` such as `007`, `0123`, `0123.45`, `01.5` | varchar |
| `0`, `0.50` | numeric |
| Signed integer inside `-2147483648..2147483647` | `int` |
| Signed integer inside `-9223372036854775808..9223372036854775807` | `bigint` |
| Longer digit string | varchar |
| Fraction | `float`, unless the column is exact |
| `/^[+-]?(?:\d+\.?\d*|\.\d+)[eE][+-]?\d+$/` | scientific |

Ranges are compared as digit strings. Values are not cast to PHP `int` or `float` for the range check. A scientific exponent longer than 3 digits, or an absolute exponent above 308, is overflow and becomes varchar even when the column is entirely scientific.

Exact decimal when the column is not forced to string and any of these hold:

- The name is listed in `decimal_columns`.
- The lower-cased name contains `decimal`, `amount`, `total`, `cost`, `price`, `gst`, `pst`, or `discount`.
- The name contains `tax` and does not contain `rate`.

`money` is not in that list, so `0.50` on a column named `money` stays `float`. Exact columns of whole numbers are `DECIMAL(p, 0)`.

Promotion after every value is seen:

| Flags | Type |
| --- | --- |
| Only `date` | `date` |
| Only `datetime`, or `date` plus `datetime` | `datetime` |
| Only `time` | `time` |
| `date`+`time`, `datetime`+`time`, or all three | varchar |
| Temporal mixed with a number or a string | varchar |
| Temporal mixed with `text` | `text` |
| Temporal mixed with `json` | `json` |
| `json` | `json` |
| `text` | `text` |
| Any string flag | varchar, or `text` when longer than the threshold |
| `decimal`, or `float` with `bigint`, or `float` with more than 15 integer digits | `decimal` |
| `float` | `float` |
| `bigint` | `bigint` |
| `int` | `int` |
| No values | varchar, nullable |

Scientific notation sets `float` only when every non-empty value is scientific and none overflow. Mixed with any other form, those cells force varchar.

`DECIMAL(p,s)` uses `p = max integer digits + max scale`, counted across integers and fractions. `p`, integer digits, or scale above 38 falls back to varchar. A DTO built without `integerDigits` keeps `p = max(10, scale)` so older callers that expected `DECIMAL(10,2)` stay valid.

`canAcceptType()`:

| Receiver | Also accepts |
| --- | --- |
| `datetime` | `date` |
| `json` | every type |
| `text` | everything except `json` |
| `varchar` | everything except `json` and `text` |
| `decimal` | `float`, `bigint`, `int` |
| `float` | `int` only |
| `bigint` | `int` |
| `date`, `time` | only themselves |

## SQL and cast mapping

| Type | MySQL `getSqlDefinition()` | SQL Server `getMsSqlTypeDefinition()` | PostgreSQL | Migration | Eloquent cast |
| --- | --- | --- | --- | --- | --- |
| `varchar` | `VARCHAR(n)` | `NVARCHAR(n)` | `VARCHAR(n)` | `string(name, n)` | none |
| `text` | `TEXT` | `NVARCHAR(MAX)` | `TEXT` | `text()` | none |
| `json` | `JSON` | `NVARCHAR(MAX)` | `JSONB` | `json()` | `array` |
| `int` | `INT` | `INT` | `INTEGER` | `integer()` | `integer` |
| `bigint` | `BIGINT` | `BIGINT` | `BIGINT` | `bigInteger()` | `integer` |
| `float` | `DOUBLE` | `FLOAT` | `DOUBLE PRECISION` | `double()` | `float` |
| `decimal` | `DECIMAL(p,s)` | `DECIMAL(p,s)` | `DECIMAL(p,s)` | `decimal(name, p, s)` | `decimal:{scale}` |
| `date` | `DATE` | `DATE` | `DATE` | `date()` | `date` |
| `datetime` | `DATETIME` or `DATETIME(n)` with `n` capped at 6 | `DATETIME2` | `TIMESTAMP` or `TIMESTAMP(n)` capped at 6 | `dateTime()` or `dateTime(name, n)` capped at 6 | `datetime` |
| `time` | `TIME` | `TIME` | `TIME` | `time()` | none |

`n` for varchar SQL is `max(observedLength, varcharDefault)`. The DTO `length` field holds that SQL length. `observedLength` holds the measured length. Hand-built DTOs keep the length passed to the constructor. `getSqlDefinition()` uses 255 only when `length` is null.

`fractionalSeconds` is the longest fractional part seen, capped at 7 while classifying. MySQL and Laravel cap the emitted precision at 6. SQL Server `DATETIME2` uses its default scale of 7, which holds `.51185`.

Text threshold is `min(max(255, varcharDefault), 4000)`. Over that length the type is `text`. Collation, when requested by the SQL Server generator, is applied to `varchar`, `text`, and `json` only.

`ModelGenerator::getPhpTypeFromColumnType()` maps `bigint` to `int`, and `date`, `datetime`, and `time` to `string`.

## CSV reading

- `fgetcsv` length `0` reads the whole line. Enclosure is `"`. Escape is `''`, so a backslash is data. The PHP default escape would consume it.
- A row whose field count differs from the header increments `getSkippedRowCount()` and is otherwise ignored. `array_combine` is not used.
- A line that is one empty cell is a blank line. It is ignored and not counted as skipped.
- An empty header throws. Two headers in the same file that match case-insensitively throw. The same column in another file is merged, and the first spelling is kept.
- Headers are trimmed. The first header cell loses a leading UTF-8 BOM.
- Columns are merged by case-insensitive name. A column absent from a later or earlier file is `missingFromASource` and `nullSeen`. The result is the union.
- `getSkippedRowCount()` resets at the start of each public file analysis.

UTF-16 files are not detected.

## Multi-file and JSON

`InputPaths::expand()` treats an argument containing `*`, `?`, or `[` as a glob and returns matching files, sorted. A literal existing file is used as itself. Otherwise a comma splits the argument. No matches, a missing file, or an empty expansion throws.

JSON booleans count as `int`. JSON integers use the same int/bigint/varchar range check. Nested arrays and objects set the `json` flag.

## Database schema

This feature does not create tables. It describes columns the DDL feature can create. Nullable is true when a null, an empty cell, or a missing cell was seen, or when the column had no values. `presentInEverySource` is false when any input file lacked the column.

## SQL artifacts

None. Generated SQL lives in [sql-server-ddl](../sql-server-ddl/sql-server-ddl.md).

## Failure behavior

| Condition | Result |
| --- | --- |
| Unsupported extension | `InvalidArgumentException` |
| Missing file, empty glob, empty input | `InvalidArgumentException` |
| Invalid JSON | `InvalidArgumentException` with `json_last_error_msg()` |
| Empty or duplicate header | `InvalidArgumentException` |
| `varchar-default` below 1 on a command | `InvalidArgumentException` |
| Bad CSV row | skipped and counted |
| Unrecognized `--driver` | command prints an error and returns 1 |
