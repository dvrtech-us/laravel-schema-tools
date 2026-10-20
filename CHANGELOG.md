# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-10-20

Inference changes below alter the types produced for some existing files. Callers that stored every timestamp as `date`, every fraction as `FLOAT(n)`, or every short string as `varchar(50)` need to regenerate DDL.

### Added
- `datetime` and `time` column types, with `date` reserved for a calendar date.
- Fractional seconds on `YYYY-MM-DD HH:MM:SS`, up to 7 digits (`2026-10-20 09:34:14.51185`).
- `schema-tools:create-table` prints guarded SQL Server DDL. `--execute` runs it on a `sqlsrv` connection and refuses when the table already exists.
- `--driver=mysql|sqlsrv` on `schema-tools:analyze`. The default is the default connection's driver.
- `--schema`, `--primary-key`, `--timestamps`, and `--collation` for SQL Server DDL.
- `--string-columns`, `--decimal-columns`, and `--varchar-default` on the analyze, create-table, generate, migration, and model commands. The same keys exist in config.
- Multi-file analysis. `schema-tools:analyze` accepts several paths, a glob, or a comma-separated list, and merges per-column stats.
- Streamed CSV reading. Rows whose column count does not match the header are skipped and counted.
- `SchemaDiff` reports columns missing from an existing table and type mismatches.

### Changed
- A date mixed with a datetime promotes to `datetime`. Any other temporal mix promotes to `varchar`. A pure time such as `17:26:31` is `time`, not `date`.
- MySQL approximate numbers are `DOUBLE`. SQL Server approximate numbers are `FLOAT` (8-byte). `FLOAT(n)` is no longer emitted.
- `DECIMAL(p,s)` uses the maximum integer digits plus the maximum scale. `p` above 38 becomes `varchar`. A hand-built decimal with no integer-digit count still uses precision `max(10, scale)`.
- A column containing a leading zero (`007`, `0123`, `0123.45`) stays `varchar`. `0` and `0.50` stay numeric.
- Whole numbers outside signed 32-bit range become `bigint`. Digit strings outside signed bigint become `varchar`.
- `1e5` is numeric only when every non-empty value in the column is scientific notation. That column is `float`, not `decimal`.
- A bigint mixed with a fraction, or a fraction with more than 15 integer digits, becomes `decimal`.
- Names containing `amount`, `price`, `total`, `cost`, `gst`, `pst`, `discount`, or `decimal`, and `tax` without `rate`, infer as `decimal`. `--decimal-columns` does the same.
- Varchar SQL length is `max(observed length, varchar default)`. The default is 255, replacing the old 50 floor. The analyze Length column shows the observed length.
- Values longer than `min(max(255, varchar default), 4000)` infer as `text`. SQL Server text and json use `NVARCHAR(MAX)`, not `NTEXT`.
- CSV parsing uses an empty escape character, so a backslash inside a field is preserved.
- Eloquent float casts are `float`. Migrations use `double()`, `bigInteger()`, `dateTime()`, and `time()`.
- ISO timestamps such as `2024-03-15T14:30:00.000000Z` infer as `datetime`.
- `schema-tools:generate` treats `.JSON` and `.CSV` the same as lowercase extensions.
- Larastan `^2.9` is allowed so the PHP 8.1 jobs can install Larastan 2. The PHP 8.3 PHPStan job resolves Larastan 3.
- CI resolves dependencies with `composer update` instead of a lock file, and does not let Composer block Laravel 9–11 releases that carry security advisories. The security audit still runs against the resolved tree.

## [1.0.0] - 2025-01-11

### Added
- Initial package structure
- Core functionality implementation
- Documentation and examples
