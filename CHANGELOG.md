# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Initial release of JSON Schema Tools for Laravel
- JSON data structure analysis
- Database schema generation (MySQL, SQL Server)
- Laravel migration generation
- Laravel model generation with proper fillable and casts
- Artisan commands for CLI usage
- Comprehensive test suite
- PHPUnit configuration
- Example usage script

### Features
- **SchemaAnalyzer**: Analyzes JSON data and determines optimal database column types
- **ColumnStructureDTO**: Type-safe data transfer object for column definitions
- **DatabaseSchemaGenerator**: Generates SQL CREATE TABLE and INSERT statements
- **MigrationGenerator**: Creates Laravel migration files from JSON structure
- **ModelGenerator**: Creates Laravel Eloquent models with proper configuration
- **Artisan Commands**:
  - `schema-tools:analyze` - Analyze JSON file structure
  - `schema-tools:migration` - Generate migration from JSON
  - `schema-tools:model` - Generate model from JSON
  - `schema-tools:generate` - Generate both migration and model

### Technical Details
- PSR-4 autoloading
- Laravel 8+ compatibility
- PHP 8.0+ requirement
- Comprehensive type compatibility system
- Intelligent type promotion (int → float → varchar)
- Support for nested JSON structures
- Configurable naming conventions
- Extensible architecture

## [1.0.0] - 2025-01-11

### Added
- Initial package structure
- Core functionality implementation
- Documentation and examples
