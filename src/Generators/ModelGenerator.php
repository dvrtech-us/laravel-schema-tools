<?php

namespace DVRTech\SchemaTools\Generators;

use DVRTech\SchemaTools\DTO\ColumnStructureDTO;

class ModelGenerator
{
    /**
     * @param array<string, ColumnStructureDTO> $structure
     */
    public function generateModel(string $modelName, array $structure, ?string $tableName = null): string
    {
        $tableName = $tableName ?? strtolower($modelName) . 's';
        $fillable = $this->generateFillable($structure);
        $casts = $this->generateCasts($structure);
        $classDoc = $this->generateClassDocumentation($structure);

        $template = $this->loadTemplate();

        $template = str_replace('MODEL_NAME_PLACEHOLDER', $modelName, $template);
        $template = str_replace('TABLE_NAME_PLACEHOLDER', $tableName, $template);
        $template = str_replace('FILLABLE_PLACEHOLDER', $fillable, $template);
        $template = str_replace('CASTS_PLACEHOLDER', $casts, $template);
        $template = str_replace('CLASS_DOC_PLACEHOLDER', $classDoc, $template);

        return $template;
    }

    /**
     * @param array<string, ColumnStructureDTO> $structure
     */
    protected function generateFillable(array $structure): string
    {
        $fillable = [];

        foreach ($structure as $columnName => $columnDto) {
            $fillable[] = "        '{$columnName}',";
        }

        return implode("\n", $fillable);
    }

    /**
     * @param array<string, ColumnStructureDTO> $structure
     */
    protected function generateCasts(array $structure): string
    {
        $casts = [];

        foreach ($structure as $columnName => $columnDto) {
            $cast = $columnDto->getEloquentCast();
            if ($cast) {
                $casts[] = "        '{$columnName}' => '{$cast}',";
            }
        }

        return implode("\n", $casts);
    }

    /**
     * Load the model template from file.
     */
    protected function loadTemplate(): string
    {
        $templatePath = __DIR__ . '/../Templates/model-template.php.template';

        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }

        return file_get_contents($templatePath);
    }

    /**
     * Generate class documentation based on column structure.
     *
     * @param array<string, ColumnStructureDTO> $structure
     */
    protected function generateClassDocumentation(array $structure): string
    {
        $docs = [];

        foreach ($structure as $columnName => $columnDto) {
            $type = $this->getPhpTypeFromColumnType($columnDto->type);
            $docs[] = " * @property {$type} \${$columnName}";
        }

        if (empty($docs)) {
            return '';
        }

        return " *\n" . implode("\n", $docs) . "\n *";
    }

    /**
     * Map database column types to PHP types.
     */
    protected function getPhpTypeFromColumnType(string $columnType): string
    {
        return match ($columnType) {
            'int' => 'int',
            'float', 'decimal' => 'float',
            'date' => 'string',
            'json' => 'array',
            'varchar', 'text' => 'string',
            default => 'mixed'
        };
    }
}
