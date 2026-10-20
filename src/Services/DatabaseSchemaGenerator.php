<?php

namespace DVRTech\SchemaTools\Services;

use DVRTech\SchemaTools\DTO\ColumnStructureDTO;

class DatabaseSchemaGenerator
{
    /**
     * @param array<string, ColumnStructureDTO> $structure
     */
    public function generateCreateTableSQL(string $tableName, array $structure, string $engine = 'mysql'): string
    {
        if ($engine === 'sqlsrv') {
            return $this->generateMsSqlCreateTable($tableName, $structure);
        }

        $sql = "CREATE TABLE `{$tableName}` (\n";
        $columns = [];

        foreach ($structure as $columnName => $columnDto) {
            $columns[] = "  `{$columnName}` " . $columnDto->getSqlDefinition();
        }

        $sql .= implode(",\n", $columns);
        $sql .= "\n);";

        return $sql;
    }

    /**
     * @param array<string, ColumnStructureDTO> $structure
     * @param array<int, string> $primaryKey
     */
    public function generateMsSqlCreateTable(
        string $tableName,
        array $structure,
        ?string $schema = null,
        array $primaryKey = [],
        bool $timestamps = false,
        ?string $collation = null
    ): string {
        $this->assertIdentifier($tableName, 'table');
        if ($schema !== null && $schema !== '') {
            $this->assertIdentifier($schema, 'schema');
        } else {
            $schema = null;
        }

        if ($collation !== null && $collation !== '') {
            if (preg_match('/^[A-Za-z0-9_]+$/', $collation) !== 1) {
                throw new \InvalidArgumentException("Invalid collation [{$collation}].");
            }
        } else {
            $collation = null;
        }

        foreach ($structure as $columnName => $columnDto) {
            $this->assertColumnName((string) $columnName);
            if ($columnDto->columnName !== null) {
                $this->assertColumnName($columnDto->columnName);
            }
        }

        $primaryKeyColumns = $this->resolvePrimaryKey($structure, $primaryKey);
        $this->assertKeyWidth($structure, $primaryKeyColumns);

        if ($timestamps) {
            foreach ($structure as $columnName => $columnDto) {
                $lower = strtolower((string) $columnName);
                if ($lower === 'created_at' || $lower === 'updated_at') {
                    throw new \InvalidArgumentException("Column [{$columnName}] already exists; --timestamps would duplicate it.");
                }
            }
        }

        $qualified = $schema !== null ? "[{$schema}].[{$tableName}]" : "[{$tableName}]";
        $objectName = $schema !== null ? "[{$schema}].[{$tableName}]" : "[{$tableName}]";
        $constraint = $this->constraintName($schema, $tableName);

        $lines = [];
        foreach ($structure as $columnName => $columnDto) {
            $isKey = in_array((string) $columnName, $primaryKeyColumns, true);
            $nullability = $isKey ? 'NOT NULL' : 'NULL';
            $lines[] = '        [' . $columnName . '] ' . $columnDto->getMsSqlTypeDefinition($collation) . ' ' . $nullability;
        }

        if ($timestamps) {
            $lines[] = '        [created_at] DATETIME NULL';
            $lines[] = '        [updated_at] DATETIME NULL';
        }

        if ($primaryKeyColumns !== []) {
            $quoted = array_map(fn (string $column): string => '[' . $column . ']', $primaryKeyColumns);
            $lines[] = '        CONSTRAINT [' . $constraint . '] PRIMARY KEY NONCLUSTERED (' . implode(', ', $quoted) . ')';
        }

        $body = implode(",\n", $lines);

        return "IF OBJECT_ID(N'{$objectName}', N'U') IS NULL\n"
            . "BEGIN\n"
            . "    CREATE TABLE {$qualified} (\n"
            . $body . "\n"
            . "    );\n"
            . "END";
    }

    /**
     * @param array<string, ColumnStructureDTO> $structure
     * @param array<int, string> $primaryKey
     * @return array<int, string>
     */
    private function resolvePrimaryKey(array $structure, array $primaryKey): array
    {
        $byLower = [];
        foreach ($structure as $columnName => $columnDto) {
            $byLower[strtolower((string) $columnName)] = (string) $columnName;
        }

        $resolved = [];
        foreach ($primaryKey as $requested) {
            $requested = trim((string) $requested);
            if ($requested === '') {
                continue;
            }
            $key = strtolower($requested);
            if (!isset($byLower[$key])) {
                throw new \InvalidArgumentException("Primary key column [{$requested}] was not found.");
            }

            $actual = $byLower[$key];
            $dto = $structure[$actual];
            if (in_array($dto->type, ['text', 'json'], true)) {
                throw new \InvalidArgumentException("Primary key column [{$actual}] is {$dto->type} and cannot be indexed.");
            }
            if ($dto->nullable) {
                throw new \InvalidArgumentException("Primary key column [{$actual}] has null or empty values.");
            }
            if (!$dto->presentInEverySource) {
                throw new \InvalidArgumentException("Primary key column [{$actual}] is missing from at least one input file.");
            }

            $resolved[] = $actual;
        }

        return $resolved;
    }

    /**
     * @param array<string, ColumnStructureDTO> $structure
     * @param array<int, string> $primaryKeyColumns
     */
    private function assertKeyWidth(array $structure, array $primaryKeyColumns): void
    {
        $bytes = 0;
        foreach ($primaryKeyColumns as $columnName) {
            $bytes += $this->keyBytes($structure[$columnName]);
        }

        if ($bytes > 1700) {
            throw new \InvalidArgumentException("Primary key is {$bytes} bytes, above SQL Server's 1700 byte index limit.");
        }
    }

    private function keyBytes(ColumnStructureDTO $column): int
    {
        return match ($column->type) {
            'varchar' => ($column->length ?? 255) * 2,
            'int' => 4,
            'bigint' => 8,
            'float' => 8,
            'decimal' => 17,
            'date' => 3,
            'datetime' => 8,
            'time' => 5,
            default => 1701,
        };
    }

    private function constraintName(?string $schema, string $table): string
    {
        $name = $schema !== null ? 'PK_' . $schema . '_' . $table : 'PK_' . $table;
        if (strlen($name) <= 128) {
            return $name;
        }

        $hash = substr(sha1($name), 0, 8);

        return substr($name, 0, 119) . '_' . $hash;
    }

    private function assertIdentifier(string $name, string $label): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1 || strlen($name) > 128) {
            throw new \InvalidArgumentException("Invalid {$label} [{$name}]. Use letters, numbers, and underscores, up to 128 characters.");
        }
    }

    private function assertColumnName(string $name): void
    {
        if ($name === '' || strlen($name) > 128 || preg_match('/[\]\[\'";\x00-\x1F]/', $name) === 1) {
            throw new \InvalidArgumentException("Unsafe column name [{$name}].");
        }
    }
}
