# CLAUDE.md

This file provides guidance for Claude, Cursor, and other AI assistants working with the Owlstack Core codebase.

## Project Overview

**Owlstack Core** is a framework-agnostic PHP library for publishing and synchronizing content across social media platforms (Telegram, Twitter/X, Facebook, and more). It is the shared foundation used by all Owlstack framework integrations (Laravel, WordPress, etc.).

- **Repository:** `owlstack/owlstack-core`
- **Language:** PHP 8.1+
- **Dependencies:** Zero framework dependencies (only `ext-curl` and `ext-json`)
- **Namespace:** `Owlstack\Core\`
- **License:** MIT

## Architecture Principles

1. **Zero framework dependencies** — This package must never depend on Laravel, Symfony, WordPress, or any framework. It only uses PHP's standard library and extensions.
2. **Contracts-first design** — Infrastructure concerns (storage, queues, events, HTTP) are defined as interfaces in `Contracts/` subdirectories. Framework packages provide concrete implementations.
3. **Value objects are immutable** — All value objects (`Post`, `Media`, `AccessToken`, `PublishResult`, etc.) use `readonly` constructor properties.
4. **One class per file** — Every class, interface, and enum lives in its own file.
5. **PSR-4 autoloading** — Namespace `Owlstack\Core\` maps to `src/`.

## Directory Structure

```
src/
├── Auth/            # OAuth handler, access tokens, token store contracts
├── Config/          # Configuration objects and validation
├── Content/         # Post, Media, MediaCollection value objects
├── Delivery/        # Delivery status tracking
├── Events/          # Event dispatcher contract and event objects
├── Exceptions/      # Exception hierarchy (all extend OwlstackException)
├── Formatting/      # Platform-specific formatters and text utilities
├── Http/            # cURL HTTP client and HTTP client contract
├── Platforms/       # Platform implementations (Telegram, Twitter, Facebook)
│   ├── Contracts/   # PlatformInterface, PlatformResponseInterface
│   ├── Facebook/
│   ├── Telegram/
│   └── Twitter/
├── Publishing/      # Publisher orchestrator and publish result
└── Support/         # Utility helpers (Arr, Str, Clock)
```

## Coding Standards

- **PHP version:** 8.1+ — use named arguments, enums, readonly properties, constructor promotion, union types, and fibers where appropriate.
- **Strict types:** Every PHP file must start with `declare(strict_types=1);`.
- **Code style:** Follow PSR-12 coding standards.
- **Type hints:** All method parameters and return types must be fully typed. Use `mixed` only when truly necessary.
- **DocBlocks:** Use PHPDoc for complex parameter types (`@param array{key: type}`) and `@throws` annotations. Skip trivial docblocks where the type signature is self-explanatory.
- **Naming conventions:**
  - Classes: `PascalCase`
  - Methods/properties: `camelCase`
  - Constants: `UPPER_SNAKE_CASE`
  - Interfaces: Suffix with `Interface` (e.g., `PlatformInterface`)
  - Exceptions: Suffix with `Exception` (e.g., `RateLimitException`)

## Key Patterns

### Platform Implementation

Each platform consists of two classes:
- **Platform class** (e.g., `TelegramPlatform`) — Implements `PlatformInterface`, handles API communication.
- **Formatter class** (e.g., `TelegramFormatter`) — Implements `FormatterInterface`, transforms `Post` into platform-specific content.

### Exception Hierarchy

All exceptions extend `OwlstackException` (which extends `RuntimeException`):
- `AuthenticationException` — OAuth/token failures
- `PlatformException` — API errors from platforms
- `RateLimitException` — Rate limiting
- `ContentTooLongException` — Content exceeds platform limits
- `MediaValidationException` — Invalid media files

### Event System

The `EventDispatcherInterface` contract allows framework packages to wire events. Core defines event objects (`PostPublished`, `PostFailed`) but never dispatches them directly — the `Publisher` accepts an optional dispatcher.

## Build & Test Commands

```bash
# Install dependencies
composer install

# Run all tests
./vendor/bin/phpunit

# Run only unit tests
./vendor/bin/phpunit --testsuite=Unit

# Run only integration tests
./vendor/bin/phpunit --testsuite=Integration
```

## Common Tasks

### Adding a New Platform

1. Create a directory under `src/Platforms/NewPlatform/`.
2. Create `NewPlatformFormatter` implementing `FormatterInterface`.
3. Create `NewPlatformPlatform` implementing `PlatformInterface`.
4. Register it in the `PlatformRegistry`.

### Adding a New Exception

1. Create the exception class in `src/Exceptions/`.
2. Extend `OwlstackException`.
3. Add any platform-specific context as constructor parameters.

## Things to Avoid

- **Never** add framework-specific code (Laravel facades, WordPress globals, etc.).
- **Never** use static methods or global state.
- **Never** hardcode API URLs — use configuration.
- **Never** commit real API tokens or credentials.
- **Never** suppress errors with `@` operator.
- **Never** use `var_dump`, `print_r`, or `dd()` in production code.

## ⚠️ MANDATORY: Git Branching Workflow

**Read and follow ALL branching rules defined in `AGENTS.md`.**

Key points reiterated for Claude:

1. **NEVER** commit or push directly to `main`. Always create a branch first.
2. Before ANY code change, run:
   ```bash
   git checkout main && git pull origin main
   git checkout -b fix/description   # or feature/, refactor/, docs/, test/, chore/
   ```
3. Use conventional commit messages: `fix:`, `feat:`, `refactor:`, `docs:`, `test:`, `chore:`.
4. Run `./vendor/bin/phpunit` before committing to ensure all tests pass.
5. Push the branch to remote and inform the developer it's ready for PR review.
6. **NEVER** merge into `main`. The developer handles merging and releases.
