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
     */
    public function generateMsSqlCreateTable(string $tableName, array $structure): string
    {
        $sql = "CREATE TABLE [{$tableName}] (\n";
        $columns = [];

        foreach ($structure as $columnName => $columnDto) {
            $definition = $this->getMsSqlTypeDefinition($columnDto);
            $columns[] = "  [{$columnName}] {$definition}";
        }

        $sql .= implode(",\n", $columns);
        $sql .= "\n);";

        return $sql;
    }

    protected function getMsSqlTypeDefinition(ColumnStructureDTO $columnDto): string
    {
        switch ($columnDto->type) {
            case 'varchar':
                return "NVARCHAR(" . ($columnDto->length ?? 255) . ")";
            case 'text':
                return "NTEXT";
            case 'int':
                return "INT";
            case 'float':
                return "FLOAT";
            case 'decimal':
                return "DECIMAL(10," . ($columnDto->precision ?? 2) . ")";
            case 'date':
                return "DATE";
            case 'json':
                return "NVARCHAR(MAX)"; // SQL Server doesn't have native JSON type in older versions
            default:
                return "NVARCHAR(255)";
        }
    }
}
