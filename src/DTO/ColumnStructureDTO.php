<?php

namespace DVRTech\SchemaTools\DTO;

class ColumnStructureDTO
{
    public string $type;
    public ?int $length;
    public ?int $precision;
    public ?string $columnName;
    public ?int $integerDigits;
    public ?int $observedLength;
    public bool $nullable;
    public bool $presentInEverySource;
    public int $fractionalSeconds;

    public function __construct(
        string $type,
        ?int $length = null,
        ?int $precision = null,
        ?string $columnName = null,
        ?int $integerDigits = null,
        ?int $observedLength = null,
        bool $nullable = false,
        bool $presentInEverySource = true,
        int $fractionalSeconds = 0
    ) {
        $this->type = $type;
        $this->length = $length;
        $this->precision = $precision;
        $this->columnName = $columnName;
        $this->integerDigits = $integerDigits;
        $this->observedLength = $observedLength;
        $this->nullable = $nullable;
        $this->presentInEverySource = $presentInEverySource;
        $this->fractionalSeconds = $fractionalSeconds;
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
            'columnName' => $this->columnName,
            'integerDigits' => $this->integerDigits,
            'observedLength' => $this->observedLength,
            'nullable' => $this->nullable,
            'presentInEverySource' => $this->presentInEverySource,
            'fractionalSeconds' => $this->fractionalSeconds,
        ];
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function decimalPrecisionAndScale(): array
    {
        $scale = $this->precision ?? 2;
        if ($scale < 0) {
            $scale = 0;
        }

        if ($this->integerDigits !== null) {
            $precision = $this->integerDigits + $scale;
        } else {
            $precision = max(10, $scale);
        }

        if ($precision < $scale) {
            $precision = $scale;
        }

        return [$precision, $scale];
    }

    public function getSqlDefinition(): string
    {
        switch ($this->type) {
            case 'varchar':
                return 'VARCHAR(' . ($this->length ?? 255) . ')';
            case 'float':
                return 'DOUBLE';
            case 'decimal':
                [$precision, $scale] = $this->decimalPrecisionAndScale();
                return 'DECIMAL(' . $precision . ',' . $scale . ')';
            case 'int':
                return 'INT';
            case 'bigint':
                return 'BIGINT';
            case 'text':
                return 'TEXT';
            case 'json':
                return 'JSON';
            case 'date':
                return 'DATE';
            case 'datetime':
                $fraction = min(6, max(0, $this->fractionalSeconds));
                return $fraction > 0 ? 'DATETIME(' . $fraction . ')' : 'DATETIME';
            case 'time':
                return 'TIME';
            default:
                return strtoupper($this->type);
        }
    }

    public function getMsSqlTypeDefinition(?string $collation = null): string
    {
        switch ($this->type) {
            case 'varchar':
                $definition = 'NVARCHAR(' . ($this->length ?? 255) . ')';
                return $this->withCollation($definition, $collation);
            case 'text':
            case 'json':
                return $this->withCollation('NVARCHAR(MAX)', $collation);
            case 'int':
                return 'INT';
            case 'bigint':
                return 'BIGINT';
            case 'float':
                return 'FLOAT';
            case 'decimal':
                [$precision, $scale] = $this->decimalPrecisionAndScale();
                return 'DECIMAL(' . $precision . ',' . $scale . ')';
            case 'date':
                return 'DATE';
            case 'datetime':
                return 'DATETIME2';
            case 'time':
                return 'TIME';
            default:
                return $this->withCollation('NVARCHAR(255)', $collation);
        }
    }

    public function getPostgresTypeDefinition(): string
    {
        switch ($this->type) {
            case 'varchar':
                return 'VARCHAR(' . ($this->length ?? 255) . ')';
            case 'text':
                return 'TEXT';
            case 'int':
                return 'INTEGER';
            case 'bigint':
                return 'BIGINT';
            case 'float':
                return 'DOUBLE PRECISION';
            case 'decimal':
                [$precision, $scale] = $this->decimalPrecisionAndScale();
                return 'DECIMAL(' . $precision . ',' . $scale . ')';
            case 'date':
                return 'DATE';
            case 'datetime':
                $fraction = min(6, max(0, $this->fractionalSeconds));
                return $fraction > 0 ? 'TIMESTAMP(' . $fraction . ')' : 'TIMESTAMP';
            case 'time':
                return 'TIME';
            case 'json':
                return 'JSONB';
            default:
                return 'VARCHAR(255)';
        }
    }

    public function getLaravelMigrationDefinition(): string
    {
        $name = $this->columnName ?? 'column';

        switch ($this->type) {
            case 'varchar':
                return "string('{$name}'" . ($this->length ? ", {$this->length}" : '') . ")";
            case 'text':
                return "text('{$name}')";
            case 'int':
                return "integer('{$name}')";
            case 'bigint':
                return "bigInteger('{$name}')";
            case 'float':
                return "double('{$name}')";
            case 'decimal':
                [$precision, $scale] = $this->decimalPrecisionAndScale();
                return "decimal('{$name}', {$precision}, {$scale})";
            case 'date':
                return "date('{$name}')";
            case 'datetime':
                $fraction = min(6, max(0, $this->fractionalSeconds));
                if ($fraction > 0) {
                    return "dateTime('{$name}', {$fraction})";
                }
                return "dateTime('{$name}')";
            case 'time':
                return "time('{$name}')";
            case 'json':
                return "json('{$name}')";
            default:
                return "string('{$name}')";
        }
    }

    public function getEloquentCast(): ?string
    {
        switch ($this->type) {
            case 'int':
            case 'bigint':
                return 'integer';
            case 'float':
                return 'float';
            case 'decimal':
                return 'decimal:' . ($this->precision ?? 2);
            case 'date':
                return 'date';
            case 'datetime':
                return 'datetime';
            case 'time':
                return null;
            case 'json':
                return 'array';
            default:
                return null;
        }
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, ['int', 'bigint', 'float', 'decimal'], true);
    }

    public function isTextual(): bool
    {
        return in_array($this->type, ['varchar', 'text'], true);
    }

    public function canAcceptType(string $type): bool
    {
        if ($this->type === $type) {
            return true;
        }

        if ($this->type === 'datetime' && $type === 'date') {
            return true;
        }

        if ($this->type === 'json') {
            return true;
        }

        if ($this->type === 'text' && $type !== 'json') {
            return true;
        }

        if ($this->type === 'varchar' && !in_array($type, ['json', 'text'], true)) {
            return true;
        }

        if ($this->type === 'decimal' && in_array($type, ['float', 'bigint', 'int'], true)) {
            return true;
        }

        if ($this->type === 'float' && $type === 'int') {
            return true;
        }

        if ($this->type === 'bigint' && $type === 'int') {
            return true;
        }

        return false;
    }

    private function withCollation(string $definition, ?string $collation): string
    {
        if ($collation === null || $collation === '') {
            return $definition;
        }

        return $definition . ' COLLATE ' . $collation;
    }
}
