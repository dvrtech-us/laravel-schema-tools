# SchemaTools - AI Coding Agent Instructions

## Project Overview
SchemaTools is a Laravel package that analyzes JSON/CSV data to automatically generate database schemas, migrations, and Eloquent models. The package operates through a pipeline: **Data Analysis** → **Type Detection** → **Code Generation**.

## Core Architecture

### Service Layer (`src/Services/`)
- **`SchemaAnalyzer`**: Core analysis engine that determines database column types from data
  - Uses intelligent type hierarchy: `json` > `text` > `varchar` > `date` > `decimal` > `float` > `int`
  - Context-aware type detection (e.g., `price`/`amount` columns become `decimal`)
  - Dynamic varchar length calculation (minimum 50, actual max length up to 255, then promotes to TEXT)
- **`DatabaseSchemaGenerator`**: Converts analyzed structure to raw SQL CREATE statements

### Generator Layer (`src/Generators/`)
- **`MigrationGenerator`**: Creates Laravel migration files using Blueprint syntax
- **`ModelGenerator`**: Generates Eloquent models with proper fillable attributes and PHPDoc

### DTO Pattern
- Use DTOs for structured data representation:
  - **`ColumnStructureDTO`**: Central data structure containing `type`, `length`, `precision`, `columnName`
    - Provides both raw SQL (`getSqlDefinition()`) and Laravel (`getLaravelMigrationDefinition()`) output   

## Key Commands & Workflows

### Primary Commands
```bash
# Analyze data structure only
php artisan schema-tools:analyze data.json

# Generate complete Laravel setup (migration + model)
php artisan schema-tools:generate data.json users User

# Azure environment conversion (bidirectional)
php artisan azure-env:convert azure-to-env --azure-file=azure-settings.json
php artisan azure-env:convert env-to-azure --env-file=.env
```

### Testing
```bash
.\vendor\bin\phpunit      # Run PHPUnit tests
.\vendor\bin\phpstan      # Static analysis
.\vendor\bin\phpunit --coverage-html coverage-report # Generate coverage report
```

## Development Patterns

### Type Analysis Logic
When adding new type detection in `SchemaAnalyzer::determineElementType()`:
1. Check specific types first (arrays/objects → JSON)
2. Handle numeric types with context awareness (column name hints for decimal vs float)
3. Use pattern matching for dates (avoid `strtotime()` false positives)
4. String length determines varchar vs text boundary (255 chars)

### Template System
Code generation uses placeholder replacement in `/src/Templates/`:
- `MODEL_NAME_PLACEHOLDER` → Actual model name
- `TABLE_NAME_PLACEHOLDER` → Table name
- `CLASS_DOC_PLACEHOLDER` → Generated PHPDoc comments

### Test Data Structure
`/tests/data/` contains realistic test files:
- Use actual JSON/CSV files for integration tests
- Follow pattern: `test-json-1.json`, `customers-1000.csv`
- Azure env conversion examples: `example-azure-settings.json`, `example-env.env.txt`

## Console Command Patterns
All commands extend Laravel's `Command` and follow this signature pattern:
```php
protected $signature = 'schema-tools:command {file : Description} {table?} {--option=}';
```

Commands use dependency injection for services and include comprehensive error handling for file operations and data validation.

## Critical Integration Points
- Laravel service provider auto-discovery via `composer.json` extra section
- Singleton service registration for consistent analyzer state
- Template files use PHP string interpolation for dynamic content generation
- Support for both JSON arrays and CSV with header detection

This codebase prioritizes intelligent defaults while maintaining flexibility through optional parameters and configuration overrides.
