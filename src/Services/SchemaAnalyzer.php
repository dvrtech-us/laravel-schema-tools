<?php

namespace DVRTech\SchemaTools\Services;

use DVRTech\SchemaTools\DTO\ColumnStructureDTO;

class SchemaAnalyzer
{
    /**        $mostCompatibleType = 'boolean';
        $maxLength = 0;
        $maxPrecision = 0;
        $processedValues = 0;  // Track how many non-null values we processed

        foreach ($dataArray as $dataItem) { * Check if a string is a valid date string
     * More restrictive than strtotime to avoid false positives with version strings
     */
    private function isDateString(string $value): bool
    {
        // Check for common date formats
        $datePatterns = [
            '/^\d{4}-\d{2}-\d{2}$/',                    // YYYY-MM-DD
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',  // YYYY-MM-DD HH:MM:SS
            '/^\d{2}\/\d{2}\/\d{4}$/',                  // MM/DD/YYYY
            '/^\d{2}-\d{2}-\d{4}$/',                    // MM-DD-YYYY
            '/^\d{4}\/\d{2}\/\d{2}$/',                  // YYYY/MM/DD
            '/^\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}$/',  // YYYY/MM/DD HH:MM:SS
            '/^\d{1,2}:\d{2}:\d{2}$/',            // HH:MM:SS
            '/^\d{1,2}:\d{2}$/',                    // HH:MM
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', // ISO 8601 format
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', // ISO 8601 with microseconds
        ];

        foreach ($datePatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                // Additional validation with strtotime
                return strtotime($value) !== false;
            }
        }

        return false;
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    public function determineElementType($value, ?string $columnName = null): array
    {
        $length = null;
        $precision = null;

        // 1. Check if object, assume JSON
        // 2. Check if numeric
        // 3. Inner if Check if float
        // 4. IF YES FLOAT check precision
        // 5. If NO assume INT
        // 6. Check if date
        // 7. Check if over 255 characters if so TEXT
        // 8. Final case, VARCHAR 255

        if (is_array($value)) {
            $type = 'json';
        } elseif (is_object($value)) {
            $type = 'json';
        } elseif (is_bool($value)) {
            $type = 'int';  // Treat booleans as integers (0/1)
        } elseif (is_integer($value)) {
            $type = 'int';
        } elseif (is_float($value)) {
            // Check if this should be decimal based on column name or monetary fields
            if ($columnName && (
                stripos($columnName, 'decimal') !== false ||
                stripos($columnName, 'amount') !== false ||
                stripos($columnName, 'total') !== false ||
                stripos($columnName, 'cost') !== false ||
                stripos($columnName, 'price') !== false ||
                stripos($columnName, 'gst') !== false ||
                stripos($columnName, 'pst') !== false ||
                (stripos($columnName, 'tax') !== false && stripos($columnName, 'rate') === false) ||
                stripos($columnName, 'discount') !== false
            )) {
                $type = 'decimal';
            } else {
                $type = 'float';
            }
            $precision = strlen(substr(strrchr((string)$value, "."), 1));
            // always set precision to 2 for now unless its longer than that
            if ($precision < 2) {
                $precision = 2;
            }
        } elseif (is_string($value) && $this->isDateString($value)) {
            $type = 'date';
        } elseif (is_string($value)) {
            $length = strlen($value);

            if ($length > 255) {
                $type = 'text';
            } else {
                $type = 'varchar';
                $length = 255;
            }
        } else {
            $type = 'varchar';
            $length = 255;
        }

        return ['type' => $type, 'length' => $length, 'precision' => $precision];
    }

    /**
     * Determine the most compatible type for a column based on the data array
     * 
     * @param array<mixed> $dataArray
     */
    public function determineMostCompatibleType(array $dataArray, ?string $columnName = null): ColumnStructureDTO
    {
        if (empty($dataArray)) {
            return new ColumnStructureDTO('varchar', 10, null, $columnName);
        }

        $typeHierarchy = [
            'json' => 10,
            'text' => 9,
            'varchar' => 8,
            'date' => 7,
            'decimal' => 6,
            'float' => 5,
            'int' => 4
        ];

        $mostCompatibleType = 'int';  // Start with int as the default instead of boolean
        $maxLength = 0;
        $maxPrecision = 0;
        $processedValues = 0;  // Track how many non-null values we processed

        foreach ($dataArray as $row) {
            $value = $columnName ? ($row[$columnName] ?? null) : $row;

            // Skip null or empty values
            if ($value === null || $value === '') {
                continue;
            }

            $elementType = $this->determineElementType($value, $columnName);
            $currentType = $elementType['type'];
            $processedValues++;  // Increment count of processed values

            // Special handling for numeric strings
            if ($currentType === 'varchar' && is_numeric($value)) {
                // If the string is numeric, determine if it should be int or float
                if (strpos($value, '.') !== false) {
                    $currentType = 'float';
                    $elementType['type'] = 'float';
                    $elementType['precision'] = strlen(substr(strrchr($value, "."), 1));
                    if ($elementType['precision'] < 2) {
                        $elementType['precision'] = 2;
                    }
                } elseif (strpos($value, '.') === false) {
                    $currentType = 'int';
                    $elementType['type'] = 'int';
                }
            }

            // Track maximum length and precision
            if ($elementType['length'] && $elementType['length'] > $maxLength) {
                $maxLength = $elementType['length'];
            }

            if ($elementType['precision'] && $elementType['precision'] > $maxPrecision) {
                $maxPrecision = $elementType['precision'];
            }

            // Determine the most compatible type based on hierarchy
            if ($typeHierarchy[$currentType] > $typeHierarchy[$mostCompatibleType]) {
                $mostCompatibleType = $currentType;
            }

            // Special handling for numeric types
            if ($mostCompatibleType === 'int' && $currentType === 'float') {
                $mostCompatibleType = 'float';
            }

            if ($mostCompatibleType === 'int' && $currentType === 'decimal') {
                $mostCompatibleType = 'decimal';
            }

            if ($mostCompatibleType === 'float' && $currentType === 'decimal') {
                $mostCompatibleType = 'decimal';
            }

            // If we encounter a date while we had numeric types, promote to varchar
            if (($mostCompatibleType === 'int' || $mostCompatibleType === 'float') && $currentType === 'date') {
                $mostCompatibleType = 'varchar';
                $maxLength = max($maxLength, 10);
            }

            // If we encounter varchar while we had date, promote to varchar
            if ($mostCompatibleType === 'date' && $currentType === 'varchar') {
                $mostCompatibleType = 'varchar';
                $maxLength = max($maxLength, 10);
            }
        }

        // If we never processed any values (all were null/empty), default to varchar
        if ($processedValues === 0) {
            $mostCompatibleType = 'varchar';
            $maxLength = 255;
        }

        // Set appropriate length and precision based on final type
        $finalLength = null;
        $finalPrecision = null;

        switch ($mostCompatibleType) {
            case 'varchar':
                $finalLength = max($maxLength, 255);
                break;
            case 'text':
                $finalLength = null; // TEXT doesn't need length specification
                break;
            case 'float':
            case 'decimal':
                $finalPrecision = max($maxPrecision, 2);
                break;
            case 'json':
            case 'date':
            case 'int':
            default:
                // These types don't need length or precision
                break;
        }

        return new ColumnStructureDTO($mostCompatibleType, $finalLength, $finalPrecision, $columnName);
    }

    /**
     * @param array<mixed> $dataArray
     * @return array<string, ColumnStructureDTO>
     */
    public function analyzeDataStructure(array $dataArray): array
    {
        if (empty($dataArray)) {
            return [];
        }

        //check if we have a single row or multiple rows
        if (isset($dataArray[0]) && is_array($dataArray[0])) {
            // Multi-dimensional array - analyze each column
        } else {
            // Single-dimensional array - analyze as single column
            $dataArray = [$dataArray];
        }

        $structure = [];
        $firstRow = reset($dataArray);

        if (is_array($firstRow) || is_object($firstRow)) {
            // Collect all possible column names from all rows
            $allColumns = [];
            foreach ($dataArray as $row) {
                if (is_array($row) || is_object($row)) {
                    $row = (array) $row;
                    $allColumns = array_merge($allColumns, array_keys($row));
                }
            }

            // Get unique column names
            $allColumns = array_unique($allColumns);

            // Multi-dimensional array - analyze each column
            foreach ($allColumns as $columnName) {
                $structure[$columnName] = $this->determineMostCompatibleType($dataArray, $columnName);
            }
        } else {
            // Single-dimensional array - analyze as single column
            $structure['data'] = $this->determineMostCompatibleType($dataArray, 'data');
        }

        return $structure;
    }

    /**
     * @return array<string, ColumnStructureDTO>
     */
    public function analyzeJsonFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $jsonContent = file_get_contents($filePath);
        $data = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Invalid JSON: " . json_last_error_msg());
        }

        return $this->analyzeDataStructure($data);
    }
    /**
     * @return array<string, ColumnStructureDTO>
     */
    public function analyzeCSVFile(string $filePath, string $delimiter = ','): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $data = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }

        if (empty($data)) {
            return [];
        }

        // Convert to associative array using the first row as headers
        $headers = array_shift($data);
        $data = array_map(function ($row) use ($headers) {
            return array_combine($headers, $row);
        }, $data);
        return $this->analyzeDataStructure($data);
    }
}
