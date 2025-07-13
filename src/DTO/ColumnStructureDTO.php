<?php

namespace DVRTech\SchemaTools\DTO;

class ColumnStructureDTO
{
    public string $type;
    public ?int $length;
    public ?int $precision;
    public ?string $columnName;

    public function __construct(string $type, ?int $length = null, ?int $precision = null, ?string $columnName = null)
    {
        $this->type = $type;
        $this->length = $length;
        $this->precision = $precision;
        $this->columnName = $columnName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'length' => $this->length,
            'precision' => $this->precision,
            'columnName' => $this->columnName
        ];
    }

    public function getSqlDefinition(): string
    {
        $definition = strtoupper($this->type);

        switch ($this->type) {
            case 'varchar':
                $definition .= '(' . ($this->length ?? 255) . ')';
                break;
            case 'float':
                $definition .= '(' . ($this->precision ?? 2) . ')';
                break;
            case 'decimal':
                $definition .= '(10,' . ($this->precision ?? 2) . ')';
                break;
            case 'int':
                $definition = 'INT';
                break;
            case 'text':
                $definition = 'TEXT';
                break;
            case 'json':
                $definition = 'JSON';
                break;
            case 'date':
                $definition = 'DATE';
                break;
        }

        return $definition;
    }

    public function getLaravelMigrationDefinition(): string
    {
        switch ($this->type) {
            case 'varchar':
                return "string('{$this->columnName}'" . ($this->length ? ", {$this->length}" : '') . ")";
            case 'text':
                return "text('{$this->columnName}')";
            case 'int':
                return "integer('{$this->columnName}')";
            case 'float':
                return "float('{$this->columnName}'" . ($this->precision ? ", 8, {$this->precision}" : '') . ")";
            case 'decimal':
                return "decimal('{$this->columnName}', 10, " . ($this->precision ?? 2) . ")";
            case 'date':
                return "date('{$this->columnName}')";
            case 'json':
                return "json('{$this->columnName}')";
            default:
                return "string('{$this->columnName}')";
        }
    }

    public function getEloquentCast(): ?string
    {
        switch ($this->type) {
            case 'int':
                return 'integer';
            case 'float':
            case 'decimal':
                return 'decimal:' . ($this->precision ?? 2);
            case 'date':
                return 'date';
            case 'json':
                return 'array';
            default:
                return null;
        }
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, ['int', 'float', 'decimal']);
    }

    public function isTextual(): bool
    {
        return in_array($this->type, ['varchar', 'text']);
    }

    public function canAcceptType(string $type): bool
    {
        $typeHierarchy = [
            'json' => 10,
            'text' => 9,
            'varchar' => 8,
            'date' => 7,
            'float' => 6,
            'int' => 5
        ];

        return ($typeHierarchy[$this->type] ?? 0) >= ($typeHierarchy[$type] ?? 0);
    }
}
