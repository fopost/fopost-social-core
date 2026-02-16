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

## ⚠️ MANDATORY: Git Branching Workflow

**NEVER commit or push directly to the `main` branch. This is the most important rule in this repository.**

Before making ANY code changes, you MUST follow this workflow:

### Step 1: Create a Branch

Always create a new branch from `main` using the appropriate naming convention:

| Prefix | Use Case | Example |
|--------|----------|---------|
| `fix/` | Bug fixes | `fix/telegram-message-parsing` |
| `feature/` | New features | `feature/youtube-platform` |
| `refactor/` | Code refactoring | `refactor/http-client` |
| `docs/` | Documentation changes | `docs/api-reference` |
| `test/` | Adding/updating tests | `test/publisher-unit-tests` |
| `chore/` | Maintenance tasks | `chore/update-dependencies` |

```bash
git checkout main
git pull origin main
git checkout -b fix/short-description   # or feature/, refactor/, docs/, test/, chore/
```

### Step 2: Make Changes on the Branch

All code changes, commits, and pushes happen ONLY on the feature/fix branch. Never on `main`.

### Step 3: Commit with Conventional Commit Messages

Use [Conventional Commits](https://www.conventionalcommits.org/) format:

- `fix: resolve Telegram message parsing issue`
- `feat: add YouTube platform support`
- `refactor: extract HTTP retry logic`
- `docs: update API reference for Publisher`
- `test: add unit tests for WhatsApp formatter`
- `chore: update phpunit to v11`

Rules:
- Use imperative mood (e.g., "Add" not "Added").
- One logical change per commit.
- Reference issue numbers when applicable (e.g., `fix: resolve token refresh (#42)`).

### Step 4: Push the Branch

```bash
git push origin fix/short-description
```

### Step 5: Inform the Developer

After pushing, inform the user that:
- The branch is ready for review.
- A Pull Request should be created to merge into `main`.
- **Do NOT run `git merge` into `main` yourself.**

### Complete Example

```bash
git checkout main
git pull origin main
git checkout -b fix/telegram-send-photo
# ... make changes ...
./vendor/bin/phpunit                          # ensure tests pass
git add .
git commit -m "fix: resolve Telegram sendPhoto media type detection"
git push origin fix/telegram-send-photo
# Done — inform the developer the branch is ready for PR
```

## Release Process

- Releases and version bumping are handled by the human developer ONLY.
- Do NOT modify version numbers unless explicitly asked.
- Do NOT create git tags.
- Do NOT merge branches into `main`.

## Do Not

- Add framework-specific code or dependencies.
- Use static methods or global state.
- Hardcode API URLs — use configuration.
- Commit real API tokens, secrets, or credentials.
- Suppress errors with the `@` operator.
- Use `var_dump`, `print_r`, or `dd()`.
