# LaraUtilX Test Suite

This directory contains comprehensive unit and feature tests for the LaraUtilX package.

## Test Structure

```
tests/
├── TestCase.php                    # Base test case with Laravel setup
├── Unit/                          # Unit tests for individual components
│   ├── Enums/
│   │   └── LogLevelTest.php
│   ├── Rules/
│   │   └── RejectCommonPasswordsTest.php
│   ├── Traits/
│   │   ├── ApiResponseTraitTest.php
│   │   └── FileProcessingTraitTest.php
│   └── Utilities/
│       ├── CachingUtilTest.php
│       ├── ConfigUtilTest.php
│       ├── FeatureToggleUtilTest.php
│       ├── FilteringUtilTest.php
│       ├── LoggingUtilTest.php
│       ├── PaginationUtilTest.php
│       ├── QueryParameterUtilTest.php
│       ├── RateLimiterUtilTest.php
│       └── SchedulerUtilTest.php
└── Feature/                       # Integration tests
    ├── Traits/
    │   └── ApiResponseTraitFeatureTest.php
    └── Utilities/
        └── CachingUtilFeatureTest.php
```

## Running Tests

### Prerequisites

Make sure you have installed the development dependencies:

```bash
composer install --dev
```

### Run All Tests

```bash
./vendor/bin/phpunit
```

### Run Specific Test Suites

```bash
# Run only unit tests
./vendor/bin/phpunit --testsuite Unit

# Run only feature tests
./vendor/bin/phpunit --testsuite Feature
```

### Run Specific Test Classes

```bash
# Run tests for a specific utility
./vendor/bin/phpunit tests/Unit/Utilities/CachingUtilTest.php

# Run tests for a specific trait
./vendor/bin/phpunit tests/Unit/Traits/ApiResponseTraitTest.php
```

### Run Tests with Coverage

```bash
./vendor/bin/phpunit --coverage-html coverage
```

## Test Coverage

The test suite provides comprehensive coverage for:

- **Utilities**: All utility classes with their methods and edge cases
- **Traits**: All traits with their functionality and integration
- **Enums**: All enum values and behaviors
- **Rules**: Validation rules with various input scenarios
- **Feature Tests**: Integration scenarios and performance tests

## Test Categories

### Unit Tests
- Test individual components in isolation
- Mock external dependencies
- Focus on specific functionality
- Fast execution

### Feature Tests
- Test component integration
- Use real Laravel services where appropriate
- Test end-to-end workflows
- Performance and scalability tests

## Writing New Tests

When adding new functionality to LaraUtilX, follow these guidelines:

1. **Create unit tests** for individual methods and classes
2. **Create feature tests** for integration scenarios
3. **Use descriptive test names** that explain what is being tested
4. **Follow the AAA pattern**: Arrange, Act, Assert
5. **Mock external dependencies** in unit tests
6. **Test edge cases** and error conditions
7. **Maintain high test coverage** (aim for 90%+)

## Test Data

- Use factories for creating test data
- Use realistic data structures
- Test with both valid and invalid inputs
- Include edge cases and boundary conditions

## Continuous Integration

The test suite is designed to run in CI environments with:
- SQLite in-memory database
- Array cache driver
- Sync queue driver
- Minimal external dependencies
