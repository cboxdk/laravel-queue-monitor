---
title: "Contributing"
description: "Development setup, code standards, and contribution guidelines"
weight: 95
hidden: true
---

# Contributing to Laravel Queue Monitor

Thank you for considering contributing to Laravel Queue Monitor!

## Development Setup

### Prerequisites

- PHP 8.3+
- Composer
- Git

### Clone and Install

```bash
git clone https://github.com/cboxdk/laravel-queue-monitor.git
cd laravel-queue-monitor
composer install
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/pest tests/Unit/Enums/JobStatusTest.php

# Run with coverage
composer test-coverage
```

### Code Quality

```bash
# Format code with Laravel Pint
composer format

# Run PHPStan analysis
composer analyse
```

## Project Structure

```
src/
├── Actions/          # Business logic (Action pattern)
├── Commands/         # Artisan commands
├── DataTransferObjects/  # Type-safe DTOs
├── Enums/           # PHP 8.3 enums
├── Events/          # Package events
├── Exceptions/      # Custom exceptions
├── Http/            # API layer
├── Listeners/       # Event listeners
├── Models/          # Eloquent models
├── Repositories/    # Data access layer
├── Services/        # Domain services
├── Traits/          # Reusable traits
└── Utilities/       # Helper classes
```

## Code Standards

### PHP Standards

**Strict Types**
- Every file must start with `declare(strict_types=1);`

**Type Hints**
- All parameters must have type hints
- All methods must have return types
- Use PHPDoc for array shapes: `@param array<string, mixed>`

**Modern PHP**
- Use PHP 8.3 features (enums, readonly, constructor promotion)
- Use named arguments for clarity
- Use match expressions over switch

**Example**:
```php
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

final readonly class ExampleAction
{
    public function __construct(
        private RepositoryContract $repository,
    ) {}

    /**
     * Execute the action
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(string $id, array $data): bool
    {
        // Implementation
        return true;
    }
}
```

### Design Patterns

**Action Pattern**
- Single responsibility
- Named `{Verb}{Noun}Action`
- Located in `Actions/` directory
- Constructor injection only
- No static methods

**DTO Pattern**
- Readonly properties
- Static `fromArray()` constructor
- Public `toArray()` method
- Located in `DataTransferObjects/`

**Repository Pattern**
- Interface in `Repositories/Contracts/`
- Implementation in `Repositories/Eloquent/`
- Named `{Entity}RepositoryContract`

### Testing Standards

**Test Organization**
- Unit tests in `tests/Unit/`
- Feature tests in `tests/Feature/`
- Use Pest 4 syntax
- Use descriptive test names

**Test Structure**
```php
test('descriptive test name explaining what is being tested', function () {
    // Arrange
    $job = JobMonitor::factory()->create();

    // Act
    $result = QueueMonitor::getJob($job->uuid);

    // Assert
    expect($result)->not->toBeNull();
    expect($result->uuid)->toBe($job->uuid);
});
```

**Use Factories**
- Always use factories over manual creation
- Use factory states for common scenarios
- Create new states for reusable scenarios

## Making Changes

### Adding a New Action

1. Create action class in appropriate directory
2. Add constructor dependencies
3. Implement execute() method
4. Register in `config/queue-monitor.php`
5. Write unit tests
6. Update documentation

**Example**:
```bash
# Create the action
cat > src/Actions/Core/NewAction.php << 'EOF'
<?php

declare(strict_types=1);

namespace Cbox\LaravelQueueMonitor\Actions\Core;

final readonly class NewAction
{
    public function execute(): void
    {
        // Implementation
    }
}
EOF

# Register in config
# Add to 'actions' array in config/queue-monitor.php

# Create test
vendor/bin/pest --init # if needed
cat > tests/Unit/Actions/NewActionTest.php
```

### Adding a New API Endpoint

1. Create controller method
2. Add route in `routes/api.php`
3. Create API resource if needed
4. Add Form Request for validation
5. Write feature test
6. Update API documentation

### Adding a New DTO

1. Create in `DataTransferObjects/`
2. Use readonly properties
3. Add fromArray() and toArray()
4. Add helper methods
5. Write unit tests for serialization

## Pull Request Process

### Before Submitting

1. **Run all checks**:
```bash
composer format
composer analyse
composer test
```

2. **Update documentation**:
- Add/update relevant docs in `/docs`
- Update CHANGELOG.md

3. **Write tests**:
- Unit tests for new classes
- Feature tests for new functionality
- Edge case tests

### PR Guidelines

**Title Format**:
```
[Type] Brief description

Examples:
[Feature] Add batch replay functionality
[Fix] Resolve timezone issue in duration calculation
[Docs] Add troubleshooting guide
[Refactor] Improve query performance
```

**Description Template**:
```markdown
## What
Brief description of changes

## Why
Reason for changes

## How
Technical approach

## Testing
- [ ] Unit tests added
- [ ] Feature tests added
- [ ] Manual testing completed

## Checklist
- [ ] Code formatted with Pint
- [ ] PHPStan Level 9 passes
- [ ] Tests pass
- [ ] Documentation updated
```

## Code Review Criteria

Reviewers will check:

1. **Functionality** - Does it work as intended?
2. **Tests** - Is there adequate test coverage?
3. **Code Quality** - Follows standards and patterns?
4. **Documentation** - Is it documented?
5. **PHPStan** - Level 9 compliant?
6. **Breaking Changes** - Are they necessary and documented?

## Versioning

We follow [Semantic Versioning](https://semver.org/):

- **Major** (1.0.0 → 2.0.0): Breaking changes
- **Minor** (1.0.0 → 1.1.0): New features, backwards compatible
- **Patch** (1.0.0 → 1.0.1): Bug fixes, backwards compatible

## Release Process

1. Update CHANGELOG.md
2. Update version in relevant files
3. Create git tag: `v1.2.3`
4. Push tag to GitHub
5. GitHub Actions publishes to Packagist automatically

## Questions?

- Open an issue for bugs
- Start a discussion for feature ideas
- Check existing issues/PRs first

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
