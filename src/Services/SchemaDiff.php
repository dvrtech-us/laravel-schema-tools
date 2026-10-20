<?php

namespace DVRTech\SchemaTools\Services;

use DVRTech\SchemaTools\DTO\ColumnStructureDTO;

class SchemaDiff
{
    /**
     * @param array<string, ColumnStructureDTO> $inferred
     * @param array<int, array{name: string, data_type: string, char_length: int|null, numeric_precision: int|null, numeric_scale: int|null, datetime_precision: int|null}> $existing
     * @return array{missing: array<int, array{name: string, expected: string}>, mismatches: array<int, array{name: string, actual: string, expected: string}>, wider: array<int, array{name: string, actual: string, expected: string}>, extra: array<int, string>}
     */
    public function compare(array $inferred, array $existing): array
    {
        $existingByName = [];
        foreach ($existing as $column) {
            $existingByName[$this->canonical($column['name'])] = $column;
        }

        $missing = [];
        $mismatches = [];
        $wider = [];

        foreach ($inferred as $name => $dto) {
            $key = $this->canonical($name);
            if (!isset($existingByName[$key])) {
                $missing[] = [
                    'name' => $name,
                    'expected' => $dto->getMsSqlTypeDefinition(),
                ];
                continue;
            }

            $actual = $existingByName[$key];
            $kind = $this->classify($dto, $actual);
            if ($kind === 'mismatch') {
                $mismatches[] = [
                    'name' => $name,
                    'actual' => $this->describeActual($actual),
                    'expected' => $dto->getMsSqlTypeDefinition(),
                ];
            } elseif ($kind === 'wider') {
                $wider[] = [
                    'name' => $name,
                    'actual' => $this->describeActual($actual),
                    'expected' => $dto->getMsSqlTypeDefinition(),
                ];
            }

            unset($existingByName[$key]);
        }

        $extra = [];
        foreach ($existingByName as $column) {
            $extra[] = $column['name'];
        }

        return [
            'missing' => $missing,
            'mismatches' => $mismatches,
            'wider' => $wider,
            'extra' => $extra,
        ];
    }

    /**
     * @param array{name: string, data_type: string, char_length: int|null, numeric_precision: int|null, numeric_scale: int|null, datetime_precision: int|null} $actual
     */
    private function classify(ColumnStructureDTO $dto, array $actual): string
    {
        $actualType = strtolower($actual['data_type']);

        switch ($dto->type) {
            case 'varchar':
                if (!in_array($actualType, ['nvarchar', 'varchar'], true)) {
                    return 'mismatch';
                }
                $length = $actual['char_length'];
                if ($length === null) {
                    return 'mismatch';
                }
                if ($length === -1) {
                    return 'wider';
                }
                $needed = $dto->length ?? 255;
                if ($length < $needed) {
                    return 'mismatch';
                }
                if ($length > $needed) {
                    return 'wider';
                }
                return 'ok';

            case 'text':
            case 'json':
                if (in_array($actualType, ['ntext', 'text'], true)) {
                    return 'mismatch';
                }
                if (in_array($actualType, ['nvarchar', 'varchar'], true) && $actual['char_length'] === -1) {
                    return 'ok';
                }
                return 'mismatch';

            case 'int':
                return $actualType === 'int' ? 'ok' : 'mismatch';

            case 'bigint':
                return $actualType === 'bigint' ? 'ok' : 'mismatch';

            case 'float':
                return $actualType === 'float' ? 'ok' : 'mismatch';

            case 'decimal':
                if (!in_array($actualType, ['decimal', 'numeric'], true)) {
                    return 'mismatch';
                }
                [$precision, $scale] = $dto->decimalPrecisionAndScale();
                $actualPrecision = (int) $actual['numeric_precision'];
                $actualScale = (int) $actual['numeric_scale'];
                if ($actualScale < $scale || $actualPrecision < $precision) {
                    return 'mismatch';
                }
                if ($actualScale > $scale || $actualPrecision > $precision) {
                    return 'wider';
                }
                return 'ok';

            case 'date':
                return $actualType === 'date' ? 'ok' : 'mismatch';

            case 'datetime':
                if ($actualType !== 'datetime2') {
                    return 'mismatch';
                }
                $neededFraction = min(7, max(0, $dto->fractionalSeconds));
                if ($actual['datetime_precision'] !== null && (int) $actual['datetime_precision'] < $neededFraction) {
                    return 'mismatch';
                }
                return 'ok';

            case 'time':
                return $actualType === 'time' ? 'ok' : 'mismatch';

            default:
                return 'mismatch';
        }
    }

    /**
     * @param array{name: string, data_type: string, char_length: int|null, numeric_precision: int|null, numeric_scale: int|null, datetime_precision: int|null} $actual
     */
    private function describeActual(array $actual): string
    {
        $type = strtolower($actual['data_type']);
        if (in_array($type, ['nvarchar', 'varchar'], true)) {
            if ($actual['char_length'] === -1) {
                return strtoupper($type) . '(MAX)';
            }
            if ($actual['char_length'] !== null) {
                return strtoupper($type) . '(' . $actual['char_length'] . ')';
            }
        }

        if (in_array($type, ['decimal', 'numeric'], true) && $actual['numeric_precision'] !== null) {
            return strtoupper($type) . '(' . $actual['numeric_precision'] . ',' . (int) $actual['numeric_scale'] . ')';
        }

        if ($type === 'datetime2' && $actual['datetime_precision'] !== null) {
            return 'datetime2(' . $actual['datetime_precision'] . ')';
        }

        return $type;
    }

    private function canonical(string $name): string
    {
        return strtolower(trim($name));
    }
}
