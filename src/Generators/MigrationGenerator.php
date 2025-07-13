<?php

namespace DVRTech\SchemaTools\Generators;

use DVRTech\SchemaTools\DTO\ColumnStructureDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrationGenerator
{
    /**
     * @param array<string, ColumnStructureDTO> $structure
     */
    public function generateMigration(string $tableName, array $structure, ?string $className = null): string
    {
        $className = $className ?? 'Create' . ucfirst($tableName) . 'Table';


        $template = "<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
          
{$this->generateColumns($structure)}
            \$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};";

        return $template;
    }

    /**
     * @param array<string, ColumnStructureDTO> $structure
     */
    protected function generateColumns(array $structure): string
    {
        $columns = [];

        foreach ($structure as $columnName => $columnDto) {
            $definition = $columnDto->getLaravelMigrationDefinition();
            $columns[] = "            \$table->{$definition};";
        }

        return implode("\n", $columns);
    }

    /**
     * Generate MySQL specific SQL
     * @param array<string, ColumnStructureDTO> $structure
     */
    public function generateMySql(string $tableName, array $structure): string
    {
        $columns = [];

        foreach ($structure as $columnName => $columnDto) {
            $sqlDefinition = $columnDto->getSqlDefinition();
            $columns[] = "`{$columnName}` {$sqlDefinition}";
        }

        $columns[] = "`created_at` TIMESTAMP NULL DEFAULT NULL";
        $columns[] = "`updated_at` TIMESTAMP NULL DEFAULT NULL";

        $columnsString = implode(",\n    ", $columns);

        return "CREATE TABLE `{$tableName}` (\n    {$columnsString}\n);";
    }

    /**
     * Generate SQL Server specific SQL
     * @param array<string, ColumnStructureDTO> $structure
     */
    public function generateSqlServer(string $tableName, array $structure): string
    {
        $columns = [];

        foreach ($structure as $columnName => $columnDto) {
            // Convert MySQL types to SQL Server types
            $sqlDefinition = $this->convertToSqlServerType($columnDto);
            $columns[] = "[{$columnName}] {$sqlDefinition}";
        }

        $columns[] = "[created_at] DATETIME2 NULL";
        $columns[] = "[updated_at] DATETIME2 NULL";

        $columnsString = implode(",\n    ", $columns);

        return "CREATE TABLE [{$tableName}] (\n    {$columnsString}\n);";
    }

    /**
     * Generate PostgreSQL specific SQL
     * @param array<string, ColumnStructureDTO> $structure
     */
    public function generatePostgreSQL(string $tableName, array $structure): string
    {
        $columns = [];

        foreach ($structure as $columnName => $columnDto) {
            // Convert MySQL types to PostgreSQL types
            $sqlDefinition = $this->convertToPostgreSQLType($columnDto);
            $columns[] = "\"{$columnName}\" {$sqlDefinition}";
        }

        $columns[] = "\"created_at\" TIMESTAMP NULL";
        $columns[] = "\"updated_at\" TIMESTAMP NULL";

        $columnsString = implode(",\n    ", $columns);

        return "CREATE TABLE \"{$tableName}\" (\n    {$columnsString}\n);";
    }

    /**
     * Generate SQL for the default database (MySQL for backward compatibility)
     * @param array<string, ColumnStructureDTO> $structure
     */
    public function generateSql(string $tableName, array $structure): string
    {
        return $this->generateMySql($tableName, $structure);
    }

    /**
     * Convert ColumnStructureDTO to SQL Server type
     */
    private function convertToSqlServerType(ColumnStructureDTO $columnDto): string
    {
        switch ($columnDto->type) {
            case 'varchar':
                return 'NVARCHAR(' . ($columnDto->length ?? 255) . ')';
            case 'text':
                return 'NTEXT';
            case 'int':
                return 'INT';
            case 'float':
                return 'FLOAT(' . ($columnDto->precision ?? 2) . ')';
            case 'decimal':
                return 'DECIMAL(10,' . ($columnDto->precision ?? 2) . ')';
            case 'date':
                return 'DATE';
            case 'boolean':
                return 'BIT';
            case 'json':
                return 'NVARCHAR(MAX)'; // SQL Server doesn't have native JSON type in older versions
            default:
                return 'NVARCHAR(255)';
        }
    }

    /**
     * Convert ColumnStructureDTO to PostgreSQL type
     */
    private function convertToPostgreSQLType(ColumnStructureDTO $columnDto): string
    {
        switch ($columnDto->type) {
            case 'varchar':
                return 'VARCHAR(' . ($columnDto->length ?? 255) . ')';
            case 'text':
                return 'TEXT';
            case 'int':
                return 'INTEGER';
            case 'float':
                return 'REAL';
            case 'decimal':
                return 'DECIMAL(10,' . ($columnDto->precision ?? 2) . ')';
            case 'date':
                return 'DATE';
            case 'boolean':
                return 'BOOLEAN';
            case 'json':
                return 'JSONB';
            default:
                return 'VARCHAR(255)';
        }
    }
}
