<?php

namespace DVRTech\SchemaTools\Support;

use Illuminate\Console\Command;

class AnalysisOptions
{
    /**
     * @return array{string_columns: array<int, string>, decimal_columns: array<int, string>, varchar_default: int}
     */
    public static function fromCommand(Command $command): array
    {
        return [
            'string_columns' => self::names($command, 'string-columns', 'string_columns'),
            'decimal_columns' => self::names($command, 'decimal-columns', 'decimal_columns'),
            'varchar_default' => self::varcharDefault($command),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function names(Command $command, string $option, string $configKey): array
    {
        $value = $command->option($option);
        if (is_string($value) && trim($value) !== '') {
            return self::cleanList(explode(',', $value));
        }

        $configured = config('schema-tools-tools.' . $configKey, config('schema-tools.' . $configKey, []));
        if (!is_array($configured)) {
            return [];
        }

        return self::cleanList($configured);
    }

    private static function varcharDefault(Command $command): int
    {
        $value = $command->option('varchar-default');
        if ($value === null || $value === '') {
            $value = config('schema-tools-tools.varchar_default', config('schema-tools.varchar_default', 255));
        }

        $default = (int) $value;
        if ($default < 1) {
            throw new \InvalidArgumentException('varchar-default must be a positive integer.');
        }

        return $default;
    }

    /**
     * @param array<int, mixed> $names
     * @return array<int, string>
     */
    private static function cleanList(array $names): array
    {
        $clean = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $clean[] = $name;
            }
        }

        return $clean;
    }
}
