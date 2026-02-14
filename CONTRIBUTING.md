# Contributing to Owlstack Core

Thank you for your interest in contributing to Owlstack Core! This document provides guidelines and instructions for contributing.

## Code of Conduct

Please be respectful and constructive in all interactions. We are committed to providing a welcoming and inclusive experience for everyone.

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- ext-curl
- ext-json

### Setup

1. Fork the repository on GitHub.
2. Clone your fork locally:

```bash
git clone git@github.com:your-username/owlstack-core.git
cd owlstack-core
```

3. Install dependencies:

```bash
composer install
```

4. Run the test suite to verify your setup:

```bash
./vendor/bin/phpunit
```

## Development Workflow

1. Create a new branch from `main`:

```bash
git checkout -b feature/your-feature-name
```

2. Make your changes following the coding standards below.
3. Write or update tests for your changes.
4. Run the full test suite and ensure all tests pass.
5. Commit your changes with a clear message.
6. Push to your fork and submit a pull request.

## Coding Standards

### PHP Style

- Follow **PSR-12** coding standard.
- Use `declare(strict_types=1);` at the top of every PHP file.
- Fully type all method parameters and return types.
- Use PHP 8.1+ features: readonly properties, constructor promotion, named arguments, enums.

### Architecture Rules

- **No framework dependencies.** This package must remain framework-agnostic. Never import Laravel, Symfony, WordPress, or any framework classes.
- **Contracts-first.** Define interfaces in `Contracts/` subdirectories for infrastructure concerns.
- **Immutable value objects.** Use `readonly` properties; do not add setters.
- **One class per file.** Each class, interface, enum, and trait must have its own file.
- **Exception hierarchy.** All exceptions must extend `OwlstackException`.

### Naming Conventions

| Element | Convention | Example |
|---|---|---|
| Classes | PascalCase | `TelegramPlatform` |
| Interfaces | PascalCase + `Interface` suffix | `PlatformInterface` |
| Exceptions | PascalCase + `Exception` suffix | `RateLimitException` |
| Methods | camelCase | `validateCredentials()` |
| Properties | camelCase | `$externalId` |
| Constants | UPPER_SNAKE_CASE | `MAX_RETRIES` |

### Documentation

- Add PHPDoc blocks for complex parameter types and `@throws` annotations.
- Keep docblocks concise — skip them when the type signature is self-explanatory.
- Update `README.md` if your changes affect the public API.

## Testing

### Running Tests

```bash
# All tests
./vendor/bin/phpunit

# Unit tests only
./vendor/bin/phpunit --testsuite=Unit

# Integration tests only
./vendor/bin/phpunit --testsuite=Integration
```

### Writing Tests

- Place unit tests in `tests/Unit/` mirroring the `src/` directory structure.
- Place integration tests in `tests/Integration/`.
- Extend `Owlstack\Core\Tests\TestCase` for all test classes.
- Name test methods descriptively: `test_it_publishes_to_telegram_successfully()`.
- Use data providers for testing multiple scenarios.

## Commit Messages

- Use the imperative mood: "Add feature" not "Added feature" or "Adds feature".
- Keep the first line under 72 characters.
- Reference issue numbers when applicable: "Fix token refresh loop (#42)".
- One logical change per commit.

### Examples

```
Add Instagram platform support

Implement InstagramPlatform and InstagramFormatter classes with support
for image posts, carousel posts, and story publishing.
```

```
Fix rate limit retry logic in HttpClient

The retry delay was not respecting the Retry-After header value
returned by the Twitter API.

Fixes #15
```

## Pull Requests

- Fill out the PR template completely.
- Reference any related issues.
- Ensure all tests pass and no new warnings are introduced.
- Keep PRs focused — one feature or fix per PR.
- Be responsive to review feedback.

## Reporting Bugs

- Search existing issues before creating a new one.
- Include PHP version, OS, and steps to reproduce.
- Provide the full error message and stack trace if applicable.
- Use a clear, descriptive title.

## Requesting Features

- Describe the use case and problem you're solving.
- Explain why the feature belongs in the core package (vs. a framework package).
- Consider backward compatibility.

## License

By contributing to Owlstack Core, you agree that your contributions will be licensed under the MIT License.
