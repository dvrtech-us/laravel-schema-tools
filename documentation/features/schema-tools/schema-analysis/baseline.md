# Schema analysis baseline

Known-good inference behavior. Update this file only when the behavior is meant to change.

## Pipeline invariants

- CSV rows are streamed. The analyzer retains one `ColumnStats` object per column, not the rows.
- CSV fields are read with `fgetcsv($handle, 0, $delimiter, '"', '')`.
- A UTF-8 BOM is removed from the first header cell only.
- Headers are trimmed. Column identity is case-insensitive. The first spelling is the emitted name.
- An empty header throws. Two headers in one file that match case-insensitively throw. The same name in another file merges.
- A CSV row with a different field count than the header is skipped and counted. A single empty cell is not counted.
- Length is `strlen()` of the raw value. Classification uses the trimmed value.
- Temporal detection runs before the leading-zero rule.
- A temporal value is accepted only after `checkdate()` and a 00:00:00–23:59:59 clock check. Year 0 is rejected.
- Fractional seconds on `YYYY-MM-DD HH:MM:SS` are 1 to 7 digits. Eight digits stay varchar.
- `2026-10-19 00:00:00.0` and `2026-10-20 09:34:14.51185` are `datetime`. `17:26:31` and `09:01:02` are `time`. `2026-10-19` is `date`.
- Date mixed with datetime promotes to `datetime`. Date mixed with time, or datetime mixed with time, promotes to varchar.
- Temporal mixed with json stays json. Temporal mixed with text stays text. Temporal mixed with a number or a plain string becomes varchar.
- Integer limits are the signed 32-bit and signed 64-bit ranges, compared as digit strings.
- Any value matching `/^[+-]?0\d/` forces the column toward varchar unless a temporal pattern already matched. `0` and `0.50` stay numeric.
- Scientific notation is `float` only when every non-empty value is scientific and the exponent fits. Otherwise those cells force varchar. An all-scientific column is never `decimal`.
- `decimal_columns` and the name heuristic (`amount`, `price`, `total`, `cost`, `gst`, `pst`, `discount`, `decimal`, and `tax` without `rate`) produce `DECIMAL`. `money` does not.
- Decimal precision is max integer digits plus max scale, including whole numbers in the same column. Precision, integer digits, or scale above 38 becomes varchar.
- There is no precision floor of 19.
- Bigint mixed with a fraction, or a fraction with more than 15 integer digits, becomes `decimal`. An int mixed with a small fraction stays `float`.
- `string_columns` forces varchar and wins over the decimal heuristic.
- SQL varchar length is `max(observedLength, varcharDefault)`. Text threshold is `min(max(255, varcharDefault), 4000)`.
- A column with no values is varchar, nullable, with SQL length equal to `varcharDefault`.
- MySQL float SQL is `DOUBLE`. SQL Server float SQL is `FLOAT`. Migrations use `double()`. The Eloquent cast is `float`.
- SQL Server datetime SQL is `DATETIME2`. MySQL datetime SQL is `DATETIME(n)` with `n` capped at 6, or `DATETIME` when no fraction was seen.
- `time` Eloquent cast is null.
- `float` does not accept `bigint` in `canAcceptType()`. `datetime` accepts `date`.
- A hand-built `ColumnStructureDTO('decimal', null, 2)` renders `DECIMAL(10,2)`.
- The analyzer does not call `config()`.
- `getSkippedRowCount()` is reset at the start of each public file analysis.

## Flag rules

| Condition | Severity | Blocks the analysis |
| --- | --- | --- |
| Empty header | error | yes, throws |
| Duplicate header | error | yes, throws |
| Unsupported extension or missing file | error | yes, throws |
| Invalid JSON | error | yes, throws |
| `varchar-default` below 1 | error | yes, throws before analysis |
| Unsupported `--driver` | error | yes, command returns 1 |
| CSV field-count mismatch | warning | no, row skipped |
| Blank one-cell CSV line | none | no, ignored |
| Leading-zero code | none | column stays varchar |
| Decimal precision above 38 | none | column falls back to varchar |
| Scientific overflow | none | column falls back to varchar |

## Side effects

1. Stats objects are allocated per column.
2. Each value updates flags, `maxLength`, digit counts, and fraction length.
3. `resolve()` builds DTOs in first-seen column order.
4. The analyze command prints the table, then the skipped-row warning when the count is above zero.
5. No file, table, or config write happens during analysis.

## Configuration defaults

| Key | Default | Command override |
| --- | --- | --- |
| `string_columns` | `[]` | `--string-columns` |
| `decimal_columns` | `[]` | `--decimal-columns` |
| `varchar_default` | `255` | `--varchar-default` |

Lookup order in `AnalysisOptions`: command option, then `schema-tools-tools.{key}`, then `schema-tools.{key}`, then the default. An empty command option falls through to config.

## Access

Artisan commands only. No authentication layer inside the package.
