# AGENTS.md

Instructions for AI coding agents (OpenAI Codex, GitHub Copilot Workspace, GPT-based tools) working in this repository.

## Identity

This is **owlstack-core**, a framework-agnostic PHP 8.1+ library for publishing content to social media platforms. It is the foundation layer — zero external framework dependencies.

## Setup

```bash
composer install
```

## Testing

```bash
# Run all tests
./vendor/bin/phpunit

# Run unit tests only
./vendor/bin/phpunit --testsuite=Unit

# Run integration tests only
./vendor/bin/phpunit --testsuite=Integration
```

All tests must pass before submitting changes.

## Code Style

- PHP 8.1+ with `declare(strict_types=1);` in every file.
- Follow PSR-12 coding standard.
- PSR-4 autoloading: `Owlstack\Core\` maps to `src/`.
- Fully type all parameters and return types.
- Use readonly constructor promotion for value objects.
- Use named arguments for clarity when constructing objects.

## Architecture Rules

1. **No framework dependencies.** Do not import Laravel, Symfony, WordPress, or any framework classes. This package depends only on `ext-curl` and `ext-json`.
2. **Contracts-first.** All infrastructure concerns (storage, queues, events, HTTP clients) must be defined as interfaces in a `Contracts/` subdirectory. Framework packages (owlstack-laravel, owlstack-wordpress) provide implementations.
3. **Immutable value objects.** `Post`, `Media`, `AccessToken`, `PublishResult`, and similar objects use `readonly` properties and must not have setters.
4. **One class per file.** Each class, interface, enum, and trait lives in its own file.
5. **Exception hierarchy.** All exceptions must extend `Owlstack\Core\Exceptions\OwlstackException`.

## File Organization

| Directory | Purpose |
|---|---|
| `src/Auth/` | OAuth handler, access tokens, token store contracts |
| `src/Config/` | Configuration objects and validation |
| `src/Content/` | Post, Media, MediaCollection value objects |
| `src/Delivery/` | Delivery status tracking |
| `src/Events/` | Event dispatcher contract and event objects |
| `src/Exceptions/` | Exception classes (all extend OwlstackException) |
| `src/Formatting/` | Platform-specific formatters and text utilities |
| `src/Http/` | cURL HTTP client and contract |
| `src/Platforms/` | Platform implementations (Telegram, Twitter/X, Facebook) |
| `src/Publishing/` | Publisher orchestrator and result objects |
| `src/Support/` | Utility helpers (Arr, Str, Clock) |
| `tests/Unit/` | Unit tests |
| `tests/Integration/` | Integration tests |

## Adding a New Platform

1. Create `src/Platforms/{Name}/{Name}Platform.php` implementing `PlatformInterface`.
2. Create `src/Platforms/{Name}/{Name}Formatter.php` implementing `FormatterInterface`.
3. Register the platform in `PlatformRegistry`.
4. Add unit tests under `tests/Unit/Platforms/{Name}/`.

## Commit Guidelines

- Use imperative mood in commit messages (e.g., "Add Twitter platform support").
- One logical change per commit.
- Reference issue numbers when applicable.

## Do Not

- Add framework-specific code or dependencies.
- Use static methods or global state.
- Hardcode API URLs — use configuration.
- Commit real API tokens, secrets, or credentials.
- Suppress errors with the `@` operator.
- Use `var_dump`, `print_r`, or `dd()`.
