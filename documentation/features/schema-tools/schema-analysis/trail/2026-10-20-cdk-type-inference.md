# CDK type inference

- Date: 2026-10-20
- Feature: schema-analysis
- Related code: `src/Services/SchemaAnalyzer.php`, `src/Services/ColumnStats.php`, `src/DTO/ColumnStructureDTO.php`, `src/Support/AnalysisOptions.php`, `src/Support/InputPaths.php`, `src/Console/AnalyzeCommand.php`

## Context

CDK-SYNC builds SQL Server tables from CDK CSV extracts. `schema-tools:analyze` on a TechPayroll file inferred types that drop or change data: fractional timestamps became varchar, every temporal value became `date`, `FLOAT(2)` rounded amounts, leading-zero codes became `int`, and one file's integer-looking `techniciannumber` hid non-integers in other files. A single extract can be 25,000 rows and a full table about 2,000,000, so the file cannot be loaded as one array.

## Decisions

- Accept an optional `\.\d{1,7}` suffix on `YYYY-MM-DD HH:MM:SS`. Split results into `date`, `datetime`, and `time`. Validate with `checkdate()` and a real clock, not `strtotime()`.
- Date mixed with datetime promotes to datetime. Every other temporal mix promotes to varchar. Date mixed with json stays json.
- Check temporal patterns before the leading-zero rule so `09:01:02` stays `time`.
- MySQL approximate SQL is `DOUBLE`. SQL Server approximate SQL is `FLOAT` (8-byte). Migrations use `double()`. The Eloquent cast is `float`, not `decimal:{scale}`.
- Size `DECIMAL(p,s)` from max integer digits plus max scale across every numeric value in the column. `p` above 38 becomes varchar. Do not add a precision floor of 19.
- Exact decimals come from the existing name heuristic plus `--decimal-columns`. All-scientific columns stay `float`.
- Bigint mixed with a fraction, or more than 15 integer digits in a fraction, promotes to decimal so the integer digits survive.
- A leading zero matching `/^[+-]?0\d/` stays varchar. Integers outside signed 32-bit become bigint. Integers outside signed 64-bit become varchar. Compare digits as strings.
- Scientific notation is numeric only when the whole column uses it, and only as `float`.
- Stream CSV with per-column stats. Use an empty `fgetcsv` escape. Skip short or long rows and report the count. Merge a glob or several files.
- Varchar SQL length is `max(observed, varchar default)`. Default is 255. Observed length is reported separately. Text starts above `min(max(255, varchar default), 4000)`.
- Options are passed per call. The analyzer does not read config and is not given request state. `AnalysisOptions` reads `schema-tools-tools.*` first, then `schema-tools.*`. The publish path stays `schema-tools-tools.php`.
- `schema-tools:analyze {files*}` is the variadic form. A later positional argument is not possible with Symfony's greedy variadic.

## Alternatives considered

- Keep one `date` type and store the clock in the same column. Rejected because a pure time became `DATE` and a datetime was truncated.
- Emit `FLOAT(n)` with `n` equal to the observed scale. Rejected because `FLOAT(n)` selects storage size, and `FLOAT(2)` is a 4-byte real.
- Treat every fraction as `DECIMAL`. Rejected. Approximate values stay `DOUBLE`/`FLOAT` unless the name, `--decimal-columns`, integer width, or a bigint mix requires an exact type.
- Add a decimal precision floor of 19. Rejected. Size comes from the data.
- Turn an all-scientific column into decimal. Rejected. It stays `float`.
- Load the CSV with `file()` or `array_combine` on each row. Rejected because of memory use and because a short row throws `ValueError`.

## Consequences

- Positive: TechPayroll timestamps, codes, and amounts keep their values. Several files contribute one type. Memory stays flat for CSV.
- Negative: Existing callers can see different types than 1.0.x. ISO timestamps that used to be `date` are `datetime`. Short strings that used to be `varchar(50)` can be `varchar(255)`. A large integer that used to be `int` can be `bigint` or varchar. Float SQL text changed from `FLOAT(n)` to `DOUBLE` / `FLOAT`.
