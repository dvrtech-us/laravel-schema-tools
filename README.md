# Schema Tools for Laravel

[![Latest Stable Version](https://poser.pugx.org/dvrtech/schema-tools/v/stable)](https://packagist.org/packages/dvrtech/schema-tools)
[![Total Downloads](https://poser.pugx.org/dvrtech/schema-tools/downloads)](https://packagist.org/packages/dvrtech/schema-tools)
[![License](https://poser.pugx.org/dvrtech/schema-tools/license)](https://packagist.org/packages/dvrtech/schema-tools)

A comprehensive Laravel package for analyzing JSON/CSV data structures and automatically generating database schemas, migrations, and Eloquent models. Streamline your development workflow by converting raw data into production-ready Laravel components.

## 🚀 Features

- **Intelligent Data Analysis**: Automatically analyze JSON and CSV data to determine optimal database column types
- **Smart Type Detection**: Context-aware type inference with intelligent type hierarchy (`json` > `text` > `varchar` > `date` > `decimal` > `float` > `int`)
- **Database Structure Generation**: Generate CREATE TABLE statements for MySQL and SQL Server
- **Laravel Integration**: Generate Laravel migrations and Eloquent models from raw data
- **Type Compatibility**: Intelligent type promotion and compatibility checking
- **Azure Environment Support**: Bidirectional conversion between Azure settings JSON and .env files
- **Flexible Output**: Support for various database types and output formats
- **Production Ready**: Comprehensive test coverage and static analysis

## 📋 Requirements

- PHP 8.1 or higher
- Laravel 9.0, 10.0, 11.0, or 12.0

## 📦 Installation

Install the package via Composer:

```bash
composer require dvrtech/schema-tools
```

The package will automatically register its service provider through Laravel's package auto-discovery.

## 🎯 Quick Start

### Basic Usage

```bash
# Analyze data structure
php artisan schema-tools:analyze data.json

# Generate complete Laravel resources (migration + model)
php artisan schema-tools:generate data.json users User
```

### Artisan Commands

The package provides several Artisan commands for different use cases:

#### Schema Analysis
```bash
# Analyze JSON file and display structure
php artisan schema-tools:analyze data.json

# Analyze CSV file with headers
php artisan schema-tools:analyze customers.csv
```

#### Migration Generation
```bash
# Generate migration from JSON data
php artisan schema-tools:migration data.json users

# Generate migration from CSV data
php artisan schema-tools:migration customers.csv customers
```

#### Model Generation
```bash
# Generate Eloquent model from JSON
php artisan schema-tools:model data.json User

# Generate model with custom table name
php artisan schema-tools:model data.json User --table=custom_users
```

#### Complete Resource Generation
```bash
# Generate both migration and model
php artisan schema-tools:generate data.json users User

# Generate with custom namespace
php artisan schema-tools:generate data.json users User --namespace=App\\Models\\Custom
```

### Azure Environment Conversion

Convert between Azure settings JSON and Laravel .env files:

```bash
# Convert Azure settings JSON to .env file
php artisan azure-env:convert azure-to-env --azure-file=azure-settings.json --env-file=.env

# Convert .env file to Azure settings JSON
php artisan azure-env:convert env-to-azure --env-file=.env --azure-file=azure-settings.json
```




## ⚙️ Configuration

Publish the configuration file to customize default settings:

```bash
php artisan vendor:publish --provider="DVRTech\SchemaTools\SchemaToolsServiceProvider"
```

The configuration file (`config/schema-tools.php`) allows you to customize:

- **Export Paths**: Default locations for generated migrations and models
- **Type Mappings**: Custom database type mappings
- **Naming Conventions**: File and class naming patterns

### Default Configuration

```php
return [
    'export_paths' => [
        'migrations' => 'database/migrations',
        'models' => 'app/Models',
    ],
];
```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
composer test

# Run tests with coverage
./vendor/bin/phpunit --coverage-html coverage-report

# Run static analysis
./vendor/bin/phpstan
```

## 🔧 Advanced Usage

### Programmatic API

#### Basic Schema Analysis

```php
use DVRTech\SchemaTools\Services\SchemaAnalyzer;

$analyzer = new SchemaAnalyzer();

// Analyze JSON data
$jsonData = json_decode(file_get_contents('data.json'), true);
$structure = $analyzer->analyzeDataStructure($jsonData);

// Inspect column information
foreach ($structure as $columnName => $columnDto) {
    echo "Column: {$columnName}\n";
    echo "Type: {$columnDto->type}\n";
    echo "SQL Definition: {$columnDto->getSqlDefinition()}\n";
    echo "Laravel Definition: {$columnDto->getLaravelMigrationDefinition()}\n\n";
}
```

#### Generate Raw SQL Schema

```php
use DVRTech\SchemaTools\Services\DatabaseSchemaGenerator;

$generator = new DatabaseSchemaGenerator();

// Generate CREATE TABLE SQL
$createSQL = $generator->generateCreateTableSQL('users', $structure);
echo $createSQL;

// Output example:
// CREATE TABLE users (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     name VARCHAR(255) NOT NULL,
//     email VARCHAR(255) NOT NULL,
//     age INT,
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// );
```

#### Generate Laravel Migration

```php
use DVRTech\SchemaTools\Generators\MigrationGenerator;

$migrationGenerator = new MigrationGenerator();
$migration = $migrationGenerator->generateMigration('users', $structure);

// Save to migration file
$filename = date('Y_m_d_His') . '_create_users_table.php';
file_put_contents(
    database_path('migrations/' . $filename),
    $migration
);
```

#### Generate Eloquent Model

```php
use DVRTech\SchemaTools\Generators\ModelGenerator;

$modelGenerator = new ModelGenerator();
$model = $modelGenerator->generateModel('User', 'users', $structure);

// Save to model file
file_put_contents(app_path('Models/User.php'), $model);
```

### Type Detection Logic

The package uses intelligent type detection with the following hierarchy:

1. **JSON/Arrays**: Complex data structures → `json` column type
2. **Text**: Long strings (>255 chars) → `text` column type  
3. **VARCHAR**: Short strings with dynamic length calculation → `varchar(n)`
4. **Dates**: Pattern-matched date strings → `date`/`datetime`
5. **Decimal**: Context-aware numeric detection (price, amount) → `decimal(8,2)`
6. **Float**: Decimal numbers → `float`
7. **Integer**: Whole numbers → `int`

### Context-Aware Type Detection

The analyzer uses column name context to make intelligent type decisions:

```php
// These column names will automatically use decimal type
$priceColumns = ['price', 'amount', 'cost', 'fee', 'total', 'subtotal'];

// These will use appropriate string lengths
$emailColumns = ['email']; // varchar(255)
$nameColumns = ['name', 'title']; // varchar(255)
```

## 📊 Data Format Support

### JSON Files

Supports both single objects and arrays:

```json
// Single object
{
    "name": "John Doe",
    "email": "john@example.com",
    "age": 30
}

// Array of objects
[
    {"name": "John", "email": "john@example.com", "age": 30},
    {"name": "Jane", "email": "jane@example.com", "age": 25}
]
```

### CSV Files

Automatically detects headers and analyzes data types:

```csv
name,email,age,registration_date
John Doe,john@example.com,30,2024-01-15
Jane Smith,jane@example.com,25,2024-01-16
```

## 🔄 Azure Integration

### Environment File Conversion

Convert between Azure App Service configuration and Laravel .env files:

```bash
# Azure settings JSON format
{
    "DATABASE_URL": "mysql://user:pass@host:3306/db",
    "APP_ENV": "production",
    "APP_DEBUG": "false"
}

# Converts to .env format
DATABASE_URL=mysql://user:pass@host:3306/db
APP_ENV=production
APP_DEBUG=false
```

## 🏗️ Architecture Overview

The package follows a clean architecture pattern:

- **Services Layer**: Core business logic (`SchemaAnalyzer`, `DatabaseSchemaGenerator`)
- **Generators Layer**: Code generation (`MigrationGenerator`, `ModelGenerator`)  
- **DTO Pattern**: Structured data representation (`ColumnStructureDTO`)
- **Console Commands**: Artisan command interface
- **Template System**: File-based code generation templates

## 🤝 Contributing

We welcome contributions! Please follow these guidelines:

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/amazing-feature`
3. **Follow coding standards**: Use PSR-12 coding standards
4. **Add tests**: Ensure your changes are covered by tests
5. **Run quality checks**:
   ```bash
   ./vendor/bin/phpunit
   ./vendor/bin/phpstan
   ```
6. **Commit your changes**: Use conventional commit messages
7. **Push to the branch**: `git push origin feature/amazing-feature`
8. **Open a Pull Request**

### Development Setup

```bash
# Clone the repository
git clone https://github.com/dvrtech-us/laravel-schema-tools.git
cd laravel-schema-tools

# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Run static analysis
./vendor/bin/phpstan
```

## 📝 Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## 🛡️ Security

If you discover any security-related issues, please email dev-info@dvrtech.us instead of using the issue tracker.

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE) for more information.

## 💼 About DVRTech

This package is developed and maintained by [DVRTech LLC](https://dvrtech.us).

## 🙏 Credits

- **DVRTech LLC** - Initial development and maintenance
- **Laravel Community** - Inspiration and framework
- **All Contributors** - Thank you for your contributions!

---

<p align="center">
  <strong>Made with ❤️ by DVRTech</strong>
</p>
