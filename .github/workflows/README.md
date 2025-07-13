# GitHub Actions Workflows

This project uses GitHub Actions for continuous integration and testing. The following workflows are configured:

## Workflows

### 1. Tests (`tests.yml`)
**Triggers:** Push to `main`/`develop` branches, Pull Requests to `main`/`develop`

**What it does:**
- **Comprehensive Testing Matrix**: Tests across multiple PHP versions (8.1, 8.2, 8.3, 8.4) and Laravel versions (9.*, 10.*, 11.*, 12.*)
- **Dependency Testing**: Tests with both `prefer-lowest` and `prefer-stable` dependency versions
- **Code Coverage**: Generates code coverage reports and uploads to Codecov
- **PHPStan Analysis**: Runs static analysis with Larastan
- **Security Audit**: Checks for known security vulnerabilities in dependencies

**Matrix excludes:**
- Laravel 11.* and 12.* require PHP ^8.2, so PHP 8.1 is excluded for these versions

### 2. CI (`ci.yml`)
**Triggers:** Pull Requests to `main`/`develop` branches

**What it does:**
- **Quick Feedback**: Fast testing with PHP 8.3 and Laravel 11 for immediate PR feedback
- **PHPStan**: Static analysis to catch potential issues
- **Code Style**: Checks code formatting (if PHP CS Fixer or PHP_CodeSniffer are installed)

## Test Commands

The following test commands are available via Composer:

```bash
# Run all tests
composer test

# Run PHPStan analysis
composer phpstan

# Generate test coverage HTML report
composer test-coverage
```

## Test Coverage

Code coverage reports are automatically generated and uploaded to Codecov on the main test matrix when:
- PHP version is 8.3
- Laravel version is 11.*
- Dependency version is prefer-stable

## Local Development

To run tests locally, ensure you have PHP 8.1+ and run:

```bash
composer install
composer test
```

For static analysis:
```bash
composer phpstan
```

## Workflow Status

You can view the status of all workflows in the "Actions" tab of the GitHub repository. Each PR will show the status of both the quick CI checks and the comprehensive test matrix.
