<?php

namespace DVRTech\SchemaTools\Services;

use DVRTech\SchemaTools\DTO\ColumnStructureDTO;

/**
 * Infers column types from JSON arrays and CSV files.
 *
 * CSV files are streamed. Per-column stats stay in memory; the rows do not.
 */
class SchemaAnalyzer
{
    private int $skippedRowCount = 0;

    private const INT_MAX = '2147483647';
    private const INT_MIN_ABS = '2147483648';
    private const BIGINT_MAX = '9223372036854775807';
    private const BIGINT_MIN_ABS = '9223372036854775808';

    public function getSkippedRowCount(): int
    {
        return $this->skippedRowCount;
    }

    /**
     * @return 'date'|'datetime'|'time'|null
     */
    private function temporalKind(string $value): ?string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches) === 1) {
            return $this->validDate((int) $matches[1], (int) $matches[2], (int) $matches[3]) ? 'date' : null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})(?:\.(\d{1,7}))?$/', $value, $matches) === 1) {
            if (!$this->validDate((int) $matches[1], (int) $matches[2], (int) $matches[3])
                || !$this->validTime((int) $matches[4], (int) $matches[5], (int) $matches[6])) {
                return null;
            }

            return 'datetime';
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $matches) === 1) {
            return $this->validDate((int) $matches[3], (int) $matches[1], (int) $matches[2]) ? 'date' : null;
        }

        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $matches) === 1) {
            return $this->validDate((int) $matches[3], (int) $matches[1], (int) $matches[2]) ? 'date' : null;
        }

        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $value, $matches) === 1) {
            return $this->validDate((int) $matches[1], (int) $matches[2], (int) $matches[3]) ? 'date' : null;
        }

        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $value, $matches) === 1) {
            if (!$this->validDate((int) $matches[1], (int) $matches[2], (int) $matches[3])
                || !$this->validTime((int) $matches[4], (int) $matches[5], (int) $matches[6])) {
                return null;
            }

            return 'datetime';
        }

        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $value, $matches) === 1) {
            return $this->validTime((int) $matches[1], (int) $matches[2], (int) $matches[3]) ? 'time' : null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $matches) === 1) {
            return $this->validTime((int) $matches[1], (int) $matches[2], 0) ? 'time' : null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z$/', $value, $matches) === 1) {
            if (!$this->validDate((int) $matches[1], (int) $matches[2], (int) $matches[3])
                || !$this->validTime((int) $matches[4], (int) $matches[5], (int) $matches[6])) {
                return null;
            }

            return 'datetime';
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})\.(\d+)Z$/', $value, $matches) === 1) {
            if (!$this->validDate((int) $matches[1], (int) $matches[2], (int) $matches[3])
                || !$this->validTime((int) $matches[4], (int) $matches[5], (int) $matches[6])) {
                return null;
            }

            return 'datetime';
        }

        return null;
    }

    private function validDate(int $year, int $month, int $day): bool
    {
        return $year >= 1 && checkdate($month, $day, $year);
    }

    private function validTime(int $hour, int $minute, int $second): bool
    {
        return $hour >= 0 && $hour <= 23 && $minute >= 0 && $minute <= 59 && $second >= 0 && $second <= 59;
    }

    /**
     * @param mixed $value
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function determineElementType($value, ?string $columnName = null, array $options = []): array
    {
        $stats = $this->newStats($columnName ?? 'value', $options);
        $this->observe($stats, $value, $options);
        $dto = $this->resolve($stats, $options);

        return [
            'type' => $dto->type,
            'length' => $dto->observedLength ?? $dto->length,
            'precision' => $dto->precision,
            'integerDigits' => $dto->integerDigits,
        ];
    }

    /**
     * @param array<mixed> $dataArray
     * @param array<string, mixed> $options
     */
    public function determineMostCompatibleType(array $dataArray, ?string $columnName = null, array $options = []): ColumnStructureDTO
    {
        $stats = $this->newStats($columnName ?? 'value', $options);

        foreach ($dataArray as $row) {
            $value = $columnName !== null ? (is_array($row) || is_object($row) ? ((array) $row)[$columnName] ?? null : null) : $row;
            $this->observe($stats, $value, $options);
        }

        return $this->resolve($stats, $options);
    }

    /**
     * @param array<mixed> $dataArray
     * @param array<string, mixed> $options
     * @return array<string, ColumnStructureDTO>
     */
    public function analyzeDataStructure(array $dataArray, array $options = []): array
    {
        if ($dataArray === []) {
            return [];
        }

        if (!(isset($dataArray[0]) && is_array($dataArray[0]))) {
            $dataArray = [$dataArray];
        }

        /** @var array<string, ColumnStats> $stats */
        $stats = [];
        $order = [];
        $rowCount = 0;

        foreach ($dataArray as $row) {
            $rowCount++;
            if (!is_array($row) && !is_object($row)) {
                $row = ['data' => $row];
            }
            $row = (array) $row;
            $seen = [];

            foreach ($row as $column => $value) {
                $name = trim((string) $column);
                if ($name === '') {
                    throw new \InvalidArgumentException('A column header is empty.');
                }
                $key = $this->canonical($name);
                if (!isset($stats[$key])) {
                    $stats[$key] = $this->newStats($name, $options);
                    if ($rowCount > 1) {
                        $stats[$key]->nullSeen = true;
                    }
                    $order[] = $key;
                }
                $seen[$key] = true;
                $this->observe($stats[$key], $value, $options);
            }

            foreach ($stats as $key => $columnStats) {
                if (!isset($seen[$key])) {
                    $columnStats->nullSeen = true;
                }
            }
        }

        return $this->resolveAll($stats, $order, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, ColumnStructureDTO>
     */
    public function analyzeJsonFile(string $filePath, array $options = []): array
    {
        $this->skippedRowCount = 0;

        if (!is_file($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $jsonContent = file_get_contents($filePath);
        if ($jsonContent === false) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON: expected an array or object.');
        }

        return $this->analyzeDataStructure($data, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, ColumnStructureDTO>
     */
    public function analyzeCSVFile(string $filePath, string $delimiter = ',', array $options = []): array
    {
        return $this->analyzeFiles([$filePath], $options, $delimiter);
    }

    /**
     * @param array<int, string> $paths
     * @param array<string, mixed> $options
     * @return array<string, ColumnStructureDTO>
     */
    public function analyzeFiles(array $paths, array $options = [], string $delimiter = ','): array
    {
        $this->skippedRowCount = 0;

        if ($paths === []) {
            throw new \InvalidArgumentException('No input files were provided.');
        }

        /** @var array<string, ColumnStats> $stats */
        $stats = [];
        $order = [];
        $filesProcessed = 0;

        foreach ($paths as $path) {
            if (!is_file($path)) {
                throw new \InvalidArgumentException("File not found: {$path}");
            }

            $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
            if ($extension === 'csv') {
                $this->accumulateCsv($path, $delimiter, $options, $stats, $order, $filesProcessed);
            } elseif ($extension === 'json') {
                $this->accumulateJson($path, $options, $stats, $order, $filesProcessed);
            } else {
                throw new \InvalidArgumentException("Unsupported file type: {$path}");
            }

            $filesProcessed++;
        }

        return $this->resolveAll($stats, $order, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, ColumnStats> $stats
     * @param array<int, string> $order
     */
    private function accumulateCsv(
        string $filePath,
        string $delimiter,
        array $options,
        array &$stats,
        array &$order,
        int $filesProcessed
    ): void {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $header = fgetcsv($handle, 0, $delimiter, '"', '');
        if ($header === false) {
            fclose($handle);
            return;
        }

        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
        }

        $names = [];
        foreach ($header as $cell) {
            $names[] = trim((string) $cell);
        }

        $this->registerHeaders($filePath, $names, $options, $stats, $order, $filesProcessed);
        $canonicals = array_map(fn (string $name): string => $this->canonical($name), $names);

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            if ($this->isBlankRow($row)) {
                continue;
            }

            if (count($row) !== count($names)) {
                $this->skippedRowCount++;
                continue;
            }

            foreach ($names as $index => $name) {
                $this->observe($stats[$canonicals[$index]], $row[$index], $options);
            }
        }

        fclose($handle);
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, ColumnStats> $stats
     * @param array<int, string> $order
     */
    private function accumulateJson(
        string $filePath,
        array $options,
        array &$stats,
        array &$order,
        int $filesProcessed
    ): void {
        $structure = $this->readJsonRows($filePath);
        if ($structure === []) {
            return;
        }

        if (!(isset($structure[0]) && is_array($structure[0]))) {
            $structure = [$structure];
        }

        $headers = [];
        foreach ($structure as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (array_keys($row) as $column) {
                $name = trim((string) $column);
                $key = $this->canonical($name);
                if (!isset($headers[$key])) {
                    $headers[$key] = $name;
                }
            }
        }

        $this->registerHeaders($filePath, array_values($headers), $options, $stats, $order, $filesProcessed);

        foreach ($structure as $row) {
            if (!is_array($row)) {
                continue;
            }
            $seen = [];
            foreach ($row as $column => $value) {
                $name = trim((string) $column);
                $key = $this->canonical($name);
                $seen[$key] = true;
                if (isset($stats[$key])) {
                    $this->observe($stats[$key], $value, $options);
                }
            }
            foreach ($headers as $key => $name) {
                if (!isset($seen[$key]) && isset($stats[$key])) {
                    $stats[$key]->nullSeen = true;
                }
            }
        }
    }

    /**
     * @return array<mixed>
     */
    private function readJsonRows(string $filePath): array
    {
        $jsonContent = file_get_contents($filePath);
        if ($jsonContent === false) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * @param array<int, string> $names
     * @param array<string, mixed> $options
     * @param array<string, ColumnStats> $stats
     * @param array<int, string> $order
     */
    private function registerHeaders(
        string $filePath,
        array $names,
        array $options,
        array &$stats,
        array &$order,
        int $filesProcessed
    ): void {
        $seen = [];
        foreach ($names as $name) {
            if ($name === '') {
                throw new \InvalidArgumentException("A column header in {$filePath} is empty.");
            }
            $key = $this->canonical($name);
            if (isset($seen[$key])) {
                throw new \InvalidArgumentException("Duplicate column [{$name}] in {$filePath}.");
            }
            $seen[$key] = true;
        }

        foreach ($stats as $key => $columnStats) {
            if (!isset($seen[$key])) {
                $columnStats->missingFromASource = true;
                $columnStats->nullSeen = true;
            }
        }

        foreach ($names as $name) {
            $key = $this->canonical($name);
            if (!isset($stats[$key])) {
                $stats[$key] = $this->newStats($name, $options);
                if ($filesProcessed > 0) {
                    $stats[$key]->missingFromASource = true;
                    $stats[$key]->nullSeen = true;
                }
                $order[] = $key;
            }
        }
    }

    /**
     * @param array<int, string|null> $row
     */
    private function isBlankRow(array $row): bool
    {
        if (count($row) !== 1) {
            return false;
        }

        $cell = $row[0];

        return $cell === null || trim((string) $cell) === '';
    }

    /**
     * @param array<string, mixed> $options
     */
    private function newStats(string $name, array $options): ColumnStats
    {
        $stats = new ColumnStats($name);
        $stats->forcedString = $this->isListed($name, $options['string_columns'] ?? []);
        $stats->exactNumeric = !$stats->forcedString && $this->wantsExactNumeric($name, $options);

        return $stats;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function observe(ColumnStats $stats, mixed $value, array $options): void
    {
        if ($value === null) {
            $stats->nullSeen = true;
            return;
        }

        if (is_string($value) && trim($value) === '') {
            $stats->nullSeen = true;
            $stats->maxLength = max($stats->maxLength, strlen($value));
            return;
        }

        if ($stats->forcedString) {
            $stats->valueSeen = true;
            $stats->sawNonScientific = true;
            $stats->string = true;
            $stats->maxLength = max($stats->maxLength, strlen((string) $value));
            if (strlen((string) $value) > $this->textThreshold($options)) {
                $stats->text = true;
            }
            return;
        }

        if (is_array($value) || is_object($value)) {
            $stats->json = true;
            $stats->valueSeen = true;
            $stats->sawNonScientific = true;
            return;
        }

        if (is_bool($value)) {
            $stats->int = true;
            $stats->valueSeen = true;
            $stats->sawNonScientific = true;
            $stats->maxIntegerDigits = max($stats->maxIntegerDigits, 1);
            return;
        }

        if (is_int($value)) {
            $this->observeWholeNumber($stats, (string) $value);
            return;
        }

        if (is_float($value)) {
            $this->observeFloat($stats, $value);
            return;
        }

        if (is_string($value)) {
            $this->observeString($stats, $value, $options);
            return;
        }

        $stats->string = true;
        $stats->valueSeen = true;
        $stats->sawNonScientific = true;
    }

    private function observeWholeNumber(ColumnStats $stats, string $token): void
    {
        $stats->valueSeen = true;
        $stats->sawNonScientific = true;
        $stats->maxLength = max($stats->maxLength, strlen($token));
        $digits = $this->integerDigitCount(ltrim($token, '+-'));
        $stats->maxIntegerDigits = max($stats->maxIntegerDigits, $digits);

        $kind = $this->wholeNumberKind($token);
        if ($stats->exactNumeric && $kind !== 'string') {
            $stats->decimal = true;
            return;
        }

        if ($kind === 'int') {
            $stats->int = true;
        } elseif ($kind === 'bigint') {
            $stats->bigint = true;
        } else {
            $stats->string = true;
        }
    }

    private function observeFloat(ColumnStats $stats, float $value): void
    {
        $stats->valueSeen = true;
        $stats->sawNonScientific = true;
        $stats->maxLength = max($stats->maxLength, strlen((string) $value));

        if (!is_finite($value)) {
            $stats->string = true;
            return;
        }

        $described = $this->describePhpFloat($value);
        $stats->maxScale = max($stats->maxScale, $described['scale']);
        $stats->maxIntegerDigits = max($stats->maxIntegerDigits, $described['integerDigits']);

        if ($stats->exactNumeric) {
            $stats->decimal = true;
        } else {
            $stats->float = true;
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function observeString(ColumnStats $stats, string $raw, array $options): void
    {
        $stats->maxLength = max($stats->maxLength, strlen($raw));
        $value = trim($raw);
        $stats->valueSeen = true;

        $temporal = $this->temporalKind($value);
        if ($temporal !== null) {
            if ($temporal === 'date') {
                $stats->date = true;
            } elseif ($temporal === 'datetime') {
                $stats->datetime = true;
            } else {
                $stats->time = true;
            }
            $stats->sawNonScientific = true;
            if ($temporal === 'datetime' && preg_match('/\.(\d+)/', $value, $matches) === 1) {
                $stats->maxFractionDigits = max($stats->maxFractionDigits, min(7, strlen($matches[1])));
            }
            return;
        }

        // Times such as 09:01:02 match the leading-zero pattern, so this runs after temporal detection.
        if (preg_match('/^[+-]?0\d/', $value) === 1) {
            $stats->string = true;
            $stats->sawNonScientific = true;
            return;
        }

        if (preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)[eE]([+-]?\d+)$/', $value, $matches) === 1) {
            $stats->sawScientific = true;
            $exponent = ltrim($matches[1], '+-');
            if ($exponent === '' || strlen($exponent) > 3 || (int) $exponent > 308) {
                $stats->scientificOverflow = true;
            }
            return;
        }

        if (preg_match('/^[+-]?\d+$/', $value) === 1) {
            $this->observeWholeNumber($stats, $value);
            return;
        }

        if (preg_match('/^[+-]?(\d*)\.(\d+)$/', $value, $matches) === 1) {
            $integerPart = $matches[1] === '' ? '0' : $matches[1];
            $stats->maxScale = max($stats->maxScale, strlen($matches[2]));
            $stats->maxIntegerDigits = max($stats->maxIntegerDigits, $this->integerDigitCount(ltrim($integerPart, '+-')));
            $stats->sawNonScientific = true;
            if ($stats->exactNumeric) {
                $stats->decimal = true;
            } else {
                $stats->float = true;
            }
            return;
        }

        $stats->string = true;
        $stats->sawNonScientific = true;
        if (strlen($raw) > $this->textThreshold($options)) {
            $stats->text = true;
        }
    }

    /**
     * @param array<string, ColumnStats> $stats
     * @param array<int, string> $order
     * @param array<string, mixed> $options
     * @return array<string, ColumnStructureDTO>
     */
    private function resolveAll(array $stats, array $order, array $options): array
    {
        $structure = [];
        foreach ($order as $key) {
            $column = $this->resolve($stats[$key], $options);
            $structure[$column->columnName ?? $stats[$key]->name] = $column;
        }

        return $structure;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolve(ColumnStats $stats, array $options): ColumnStructureDTO
    {
        $onlyScientific = $stats->sawScientific
            && !$stats->sawNonScientific
            && !$stats->json
            && !$stats->string
            && !$stats->text
            && !$stats->date
            && !$stats->datetime
            && !$stats->time
            && !$stats->int
            && !$stats->bigint
            && !$stats->float
            && !$stats->decimal;

        if ($stats->sawScientific && !$onlyScientific) {
            $stats->string = true;
        } elseif ($onlyScientific) {
            if ($stats->scientificOverflow) {
                $stats->string = true;
            } else {
                $stats->float = true;
            }
        }

        $temporal = [];
        foreach (['date', 'datetime', 'time'] as $kind) {
            if ($stats->{$kind}) {
                $temporal[] = $kind;
            }
        }

        $hasNumeric = $stats->int || $stats->bigint || $stats->float || $stats->decimal;
        $hasString = $stats->string || $stats->text;
        $floatLosesIntegerDigits = $stats->float && ($stats->bigint || $stats->maxIntegerDigits > 15);
        $type = 'varchar';

        if ($temporal !== [] && ($hasNumeric || $hasString || $stats->json)) {
            if ($stats->json) {
                $type = 'json';
            } elseif ($stats->text) {
                $type = 'text';
            } else {
                $type = 'varchar';
            }
        } elseif ($temporal !== []) {
            $type = $this->resolveTemporal($temporal);
        } elseif ($stats->json) {
            $type = 'json';
        } elseif ($stats->text) {
            $type = 'text';
        } elseif ($stats->string) {
            $type = 'varchar';
        } elseif ($stats->decimal || $floatLosesIntegerDigits) {
            $type = 'decimal';
        } elseif ($stats->float) {
            $type = 'float';
        } elseif ($stats->bigint) {
            $type = 'bigint';
        } elseif ($stats->int) {
            $type = 'int';
        }

        if ($type === 'decimal') {
            $scale = $stats->maxScale;
            $precision = $stats->maxIntegerDigits + $scale;
            if ($precision > 38 || $stats->maxIntegerDigits > 38 || $scale > 38) {
                $type = 'varchar';
            }
        }

        if ($type === 'varchar' && $stats->maxLength > $this->textThreshold($options)) {
            $type = 'text';
        }

        $observed = $stats->valueSeen || $stats->maxLength > 0 ? $stats->maxLength : 0;
        $nullable = $stats->nullSeen || !$stats->valueSeen;
        $present = !$stats->missingFromASource;
        $name = $stats->name;

        if ($type === 'varchar') {
            $sqlLength = max($observed, $this->varcharDefault($options));

            return new ColumnStructureDTO('varchar', $sqlLength, null, $name, null, $observed, $nullable, $present);
        }

        if ($type === 'text') {
            return new ColumnStructureDTO('text', null, null, $name, null, $observed, $nullable, $present);
        }

        if ($type === 'decimal') {
            return new ColumnStructureDTO(
                'decimal',
                null,
                $stats->maxScale,
                $name,
                max(1, $stats->maxIntegerDigits),
                $observed,
                $nullable,
                $present
            );
        }

        if ($type === 'float') {
            return new ColumnStructureDTO(
                'float',
                null,
                $stats->maxScale > 0 ? $stats->maxScale : null,
                $name,
                $stats->maxIntegerDigits > 0 ? $stats->maxIntegerDigits : null,
                $observed,
                $nullable,
                $present
            );
        }

        if ($type === 'datetime') {
            return new ColumnStructureDTO(
                'datetime',
                null,
                null,
                $name,
                null,
                $observed,
                $nullable,
                $present,
                $stats->maxFractionDigits
            );
        }

        return new ColumnStructureDTO($type, null, null, $name, null, $observed > 0 ? $observed : null, $nullable, $present);
    }

    /**
     * @param array<int, string> $temporal
     */
    private function resolveTemporal(array $temporal): string
    {
        sort($temporal);
        $key = implode('+', $temporal);

        return match ($key) {
            'date' => 'date',
            'datetime' => 'datetime',
            'time' => 'time',
            'date+datetime' => 'datetime',
            default => 'varchar',
        };
    }

    private function wholeNumberKind(string $token): string
    {
        $negative = str_starts_with($token, '-');
        $digits = ltrim($token, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }

        $intLimit = $negative ? self::INT_MIN_ABS : self::INT_MAX;
        $bigintLimit = $negative ? self::BIGINT_MIN_ABS : self::BIGINT_MAX;

        if ($this->compareDigits($digits, $intLimit) <= 0) {
            return 'int';
        }

        if ($this->compareDigits($digits, $bigintLimit) <= 0) {
            return 'bigint';
        }

        return 'string';
    }

    private function compareDigits(string $left, string $right): int
    {
        $left = ltrim($left, '0');
        $right = ltrim($right, '0');
        $left = $left === '' ? '0' : $left;
        $right = $right === '' ? '0' : $right;

        if (strlen($left) !== strlen($right)) {
            return strlen($left) <=> strlen($right);
        }

        return strcmp($left, $right);
    }

    private function integerDigitCount(string $integerPart): int
    {
        $digits = ltrim($integerPart, '0');
        if ($digits === '') {
            return 1;
        }

        return strlen($digits);
    }

    /**
     * @return array{integerDigits: int, scale: int}
     */
    private function describePhpFloat(float $value): array
    {
        $formatted = rtrim(rtrim(sprintf('%.14F', abs($value)), '0'), '.');
        if (!str_contains($formatted, '.')) {
            return [
                'integerDigits' => $this->integerDigitCount($formatted === '' ? '0' : $formatted),
                'scale' => 0,
            ];
        }

        [$integer, $fraction] = explode('.', $formatted, 2);

        return [
            'integerDigits' => $this->integerDigitCount($integer === '' ? '0' : $integer),
            'scale' => strlen($fraction),
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private function wantsExactNumeric(string $columnName, array $options): bool
    {
        if ($this->isListed($columnName, $options['decimal_columns'] ?? [])) {
            return true;
        }

        $name = strtolower($columnName);
        foreach (['decimal', 'amount', 'total', 'cost', 'price', 'gst', 'pst', 'discount'] as $part) {
            if (str_contains($name, $part)) {
                return true;
            }
        }

        return str_contains($name, 'tax') && !str_contains($name, 'rate');
    }

    /**
     * @param array<int, mixed> $names
     */
    private function isListed(string $columnName, array $names): bool
    {
        $needle = $this->canonical($columnName);
        foreach ($names as $name) {
            if ($this->canonical((string) $name) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function varcharDefault(array $options): int
    {
        $value = (int) ($options['varchar_default'] ?? 255);

        return $value > 0 ? $value : 255;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function textThreshold(array $options): int
    {
        return min(max(255, $this->varcharDefault($options)), 4000);
    }

    private function canonical(string $name): string
    {
        return strtolower(trim($name));
    }
}
