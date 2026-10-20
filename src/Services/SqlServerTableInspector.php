<?php

namespace DVRTech\SchemaTools\Services;

use Illuminate\Database\Connection;

class SqlServerTableInspector
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * @phpstan-impure
     */
    public function exists(string $table, ?string $schema): bool
    {
        $qualified = $schema !== null && $schema !== ''
            ? '[' . $schema . '].[' . $table . ']'
            : '[' . $table . ']';

        $row = $this->connection->selectOne("SELECT OBJECT_ID(?, N'U') AS object_id", [$qualified]);
        if ($row === null) {
            return false;
        }

        $id = is_array($row) ? ($row['object_id'] ?? null) : ($row->object_id ?? null);

        return $id !== null;
    }

    /**
     * @return array<int, array{name: string, data_type: string, char_length: int|null, numeric_precision: int|null, numeric_scale: int|null, datetime_precision: int|null}>
     */
    public function columns(string $table, ?string $schema): array
    {
        if ($schema !== null && $schema !== '') {
            $sql = 'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ? AND TABLE_SCHEMA = ?';
            $bindings = [$table, $schema];
        } else {
            $sql = 'SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, NUMERIC_PRECISION, NUMERIC_SCALE, DATETIME_PRECISION
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_NAME = ? AND TABLE_SCHEMA = SCHEMA_NAME()';
            $bindings = [$table];
        }

        $rows = $this->connection->select($sql, $bindings);
        $columns = [];

        foreach ($rows as $row) {
            $record = (array) $row;
            $record = array_change_key_case($record, CASE_UPPER);
            $columns[] = [
                'name' => (string) $record['COLUMN_NAME'],
                'data_type' => (string) $record['DATA_TYPE'],
                'char_length' => $this->nullableInt($record['CHARACTER_MAXIMUM_LENGTH'] ?? null),
                'numeric_precision' => $this->nullableInt($record['NUMERIC_PRECISION'] ?? null),
                'numeric_scale' => $this->nullableInt($record['NUMERIC_SCALE'] ?? null),
                'datetime_precision' => $this->nullableInt($record['DATETIME_PRECISION'] ?? null),
            ];
        }

        return $columns;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
