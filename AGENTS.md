# AGENTS.md

> **This is the single source of truth for all AI coding agents** working in this repository.
> All agents (GitHub Copilot, Claude, ChatGPT, Cursor, Windsurf, Codex, etc.) MUST follow these rules.
> Agent-specific files (`CLAUDE.md`, `.github/copilot-instructions.md`) extend this file — they do NOT replace it.

---

## Project Overview

**OwlStack Core** is a framework-agnostic PHP library for publishing content across social media platforms.

> **Brand name:** "OwlStack" (capital S). The PHP namespace uses `Owlstack\Core\` (lowercase s) — this is intentional and cannot be changed.

| Property | Value |
|----------|-------|
| **Type** | Composer package (library, not application) |
| **Namespace** | `Owlstack\Core\` maps to `src/` |
| **Test Namespace** | `Owlstack\Core\Tests\` maps to `tests/` |
| **PHP Version** | 8.1+ (strict types required) |
| **Dependencies** | Zero framework deps — only `ext-curl` and `ext-json` |
| **License** | MIT |
| **CI** | GitHub Actions — PHP 8.1, 8.2, 8.3, 8.4 matrix |

### Supported Platforms (11)

Discord, Facebook, Instagram, LinkedIn, Pinterest, Reddit, Slack, Telegram, Tumblr, Twitter/X, WhatsApp

---

## Architecture Rules

1. **No framework dependencies.** Never import Laravel, Symfony, WordPress, or any framework classes. This package depends only on `ext-curl` and `ext-json`.
2. **Contracts-first.** Infrastructure concerns (HTTP, events, storage) are defined as interfaces in `Contracts/` subdirectories. Framework packages (owlstack-laravel, owlstack-wordpress) provide implementations.
3. **Immutable value objects.** `Post`, `Media`, `AccessToken`, `PublishResult`, and similar objects use `readonly` properties. No setters.
4. **One class per file.** Every class, interface, enum, and trait lives in its own file.
5. **Exception hierarchy.** All exceptions extend `Owlstack\Core\Exceptions\OwlstackException`.

---

## Directory Structure

```
src/
├── Auth/                    # OAuth handler, access tokens, token store contracts
│   └── Contracts/
├── Config/                  # Configuration objects and validation
├── Content/                 # Post, Media, MediaCollection value objects
├── Delivery/                # Delivery status tracking
├── Events/                  # Event dispatcher contract and event objects
│   └── Contracts/
├── Exceptions/              # Exception hierarchy (all extend OwlstackException)
├── Formatting/              # Text truncation, hashtag extraction
│   └── Contracts/           # FormatterInterface
├── Http/                    # cURL HTTP client
│   └── Contracts/           # HttpClientInterface
├── Platforms/               # Platform implementations (11 platforms)
│   ├── Contracts/           # PlatformInterface, PlatformResponseInterface
│   ├── Discord/
│   ├── Facebook/
│   ├── Instagram/
│   ├── LinkedIn/
│   ├── Pinterest/
│   ├── Reddit/
│   ├── Slack/
│   ├── Telegram/
│   ├── Tumblr/
│   ├── Twitter/
│   ├── WhatsApp/
│   ├── PlatformRegistry.php
│   └── PlatformResponse.php
├── Publishing/              # Publisher orchestrator and PublishResult
└── Support/                 # Utility helpers (Arr, Str, Clock)

tests/
├── TestCase.php             # Base test class (extends PHPUnit\Framework\TestCase)
├── Unit/                    # Mirrors src/ structure
│   ├── Auth/
│   ├── Config/
│   ├── Content/
│   ├── Events/
│   ├── Formatting/
│   ├── Platforms/           # {Platform}PlatformTest.php + {Platform}FormatterTest.php
│   ├── Publishing/
│   └── Support/
└── Integration/             # Real API tests (require credentials)
```

---

## Code Style & Naming

### PHP Standards

- **PSR-12** coding standard.
- **PSR-4** autoloading — namespace mirrors directory structure.
- **Strict types**: Every PHP file starts with `declare(strict_types=1);`.
- **Type hints**: All parameters, return types, and properties must be fully typed. Use `mixed` only when truly necessary.

### Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Class | PascalCase | `TelegramPlatform` |
| Method | camelCase | `publishPost()` |
| Property | camelCase | `$accessToken` |
| Constant | UPPER_SNAKE_CASE | `MAX_MESSAGE_LENGTH` |
| Interface | PascalCase + `Interface` suffix | `PlatformInterface` |
| Exception | PascalCase + `Exception` suffix | `RateLimitException` |
| Test method | `test` prefix + camelCase | `testPublishTextMessage()` |
| Config key | snake_case | `api_key` |

### DocBlocks

- Use PHPDoc for complex parameter types: `@param array{key: type}`.
- Add `@throws` annotations for methods that throw exceptions.
- Skip trivial docblocks where the type signature is self-explanatory.

---

## Key Patterns

### Platform Implementation

Each platform consists of two paired classes:
- **Platform class** (e.g., `TelegramPlatform`) — implements `PlatformInterface` (`src/Platforms/Contracts/`), handles API communication.
- **Formatter class** (e.g., `TelegramFormatter`) — implements `FormatterInterface` (`src/Formatting/Contracts/`), transforms `Post` into platform-specific content.

### Publishing Flow

The `Publisher` class (`src/Publishing/Publisher.php`) orchestrates publishing:
1. Resolves the platform via `PlatformRegistry`
2. Calls `$platform->publish($post, $options)` → returns `PlatformResponseInterface`
3. Wraps response into `PublishResult`
4. Dispatches `PostPublished` event on success, `PostFailed` on failure
5. Catches all `\Throwable`, wraps in a failed `PublishResult` and dispatches `PostFailed`

### Exception Hierarchy

All exceptions extend `OwlstackException` (which extends `RuntimeException`):

| Exception | Use Case |
|-----------|----------|
| `AuthenticationException` | OAuth/token failures |
| `PlatformException` | API errors from platforms |
| `RateLimitException` | Rate limiting (e.g., HTTP 429, includes `retry_after`) |
| `ContentTooLongException` | Content exceeds platform limits |
| `MediaValidationException` | Invalid media files |

### Event System

The `EventDispatcherInterface` contract (`src/Events/Contracts/`) allows framework packages to wire events. Core defines event objects (`PostPublished`, `PostFailed`) — the `Publisher` accepts an optional dispatcher.

---

## Adding a New Platform

When adding a new social media platform, create these 4 files:

### 1. Formatter (`src/Platforms/{Name}/{Name}Formatter.php`)
- Implements `Owlstack\Core\Formatting\Contracts\FormatterInterface`
- Methods: `format(Post $post, array $options = []): string`, `platform(): string`, `maxLength(): int`

### 2. Platform (`src/Platforms/{Name}/{Name}Platform.php`)
- Implements `Owlstack\Core\Platforms\Contracts\PlatformInterface`
- Constructor takes: `PlatformCredentials`, `HttpClientInterface`, `{Name}Formatter`
- Methods: `name()`, `publish()`, `delete()`, `validateCredentials()`, `constraints()`

### 3. Register in `PlatformRegistry` (`src/Platforms/PlatformRegistry.php`)

### 4. Tests (`tests/Unit/Platforms/{Name}/`)
- `{Name}PlatformTest.php` — test all platform methods (publish, delete, validate, constraints, error handling)
- `{Name}FormatterTest.php` — test formatting output, max length, edge cases

---

## Testing Rules

### Commands

```bash
./vendor/bin/phpunit                          # All tests
./vendor/bin/phpunit --testsuite=Unit         # Unit tests only
./vendor/bin/phpunit --testsuite=Integration  # Integration tests only
./vendor/bin/phpunit --filter testMethodName  # Specific test method
./vendor/bin/phpunit --stop-on-failure        # Stop at first failure
```

### Test Conventions

- Tests extend `PHPUnit\Framework\TestCase` (or `Tests\TestCase`)
- Test method naming: `test` prefix + camelCase — `testPublishTextMessage()`
- No `@test` annotations — use the `test` method prefix
- Test directory structure **mirrors** `src/` — e.g., `src/Platforms/Telegram/` → `tests/Unit/Platforms/Telegram/`

### Test Requirements

- Every public method MUST have at least one test
- Test both **happy path** and **error cases** (exceptions, edge cases)
- One assertion concept per test method (multiple related assertions within the same concept are OK)
- **Mock external HTTP calls** — use `$this->createMock(HttpClientInterface::class)` for unit tests
- Never make real API calls in unit tests

### HTTP Mocking Pattern

```php
$this->httpClient = $this->createMock(HttpClientInterface::class);

$this->httpClient
    ->expects($this->once())
    ->method('post')
    ->with(
        $this->stringContains('/sendMessage'),
        $this->callback(function (array $data) {
            return isset($data['chat_id']) && isset($data['text']);
        })
    )
    ->willReturn([
        'status' => 200,
        'headers' => [],
        'body' => json_encode(['ok' => true, 'result' => ['message_id' => 123]]),
    ]);
```

---

## Dependency Management

- This is a **zero-dependency library** for end users — only dev dependencies for testing.
- **Do NOT add new Composer dependencies** without asking the developer first.
- If functionality is needed, check if PHP's standard library covers it first.
- Any new dependency must be justified: why it's needed, alternatives considered, license compatibility.

---

## Decision Making

### When You're Unsure

- **ASK before proceeding** — never guess about architecture, naming, or scope.
- **Show options** — present 2-3 approaches with pros/cons and let the developer choose.
- **Reference the roadmap** — check `.roadmap/` for existing decisions.

### When Tests Fail

1. Read the failure message carefully.
2. Determine if the failure is from your change or pre-existing.
3. If from your change: **fix it before committing** — do NOT commit failing code.
4. If pre-existing: inform the developer, do not fix unrelated failures without asking.
5. **Never** skip tests, mark them incomplete, or suppress assertions to make them pass.

### When You Find a Bug (Not Related to Current Task)

1. Note it but do **NOT** fix it in the current branch.
2. Inform the developer after completing the current task.
3. The developer will decide whether to create a separate fix branch.

### When a Task Is Too Large

1. Break it into smaller atomic steps.
2. Present the plan to the developer before starting.
3. Each step should be one commit that passes all tests.

---

## MANDATORY: Git Workflow

**NEVER commit or push directly to the `main` branch.**

### Step 1: Create a Branch

```bash
git checkout main
git pull origin main
git checkout -b <prefix>/<short-description>
```

| Prefix | Use Case | Example |
|--------|----------|---------|
| `fix/` | Bug fixes | `fix/telegram-message-parsing` |
| `feature/` | New features | `feature/youtube-platform` |
| `refactor/` | Code refactoring | `refactor/http-client` |
| `docs/` | Documentation changes | `docs/api-reference` |
| `test/` | Adding/updating tests | `test/publisher-unit-tests` |
| `chore/` | Maintenance tasks | `chore/update-dependencies` |

### Step 2: Work on the Branch

All changes happen ONLY on the feature/fix branch. Never on `main`.

### Step 3: Test Before Every Commit

**Run the test suite before EVERY commit. Only commit if all tests pass.**

```bash
./vendor/bin/phpunit
```

A pre-commit git hook (`.githooks/pre-commit`) enforces this automatically.

> **Hook system:** This project uses `.githooks/` as the primary hook system (configured via `core.hooksPath`). A `.pre-commit-config.yaml` also exists for optional supplementary linting but requires separate installation.

### Step 4: Atomic Commits with Conventional Messages

**Every commit must be one self-contained logical change.**

- Each commit represents **exactly one logical change** (one fix, one feature, one refactor).
- Each commit **passes all tests independently**.
- Each commit can be **reverted or cherry-picked** without side effects.

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
fix: resolve Telegram message parsing issue
feat: add YouTube platform support
refactor: extract HTTP retry logic
docs: update API reference for Publisher
test: add unit tests for WhatsApp formatter
chore: update phpunit to v11
```

Rules:
- Imperative mood: "Add" not "Added".
- One logical change per commit.
- Reference issues when applicable: `fix: resolve token refresh (#42)`.
- Multiple changes = multiple commits. Never bundle unrelated changes.

### Step 5: Push and Report

```bash
git push origin <prefix>/<short-description>
```

After pushing, inform the developer that the branch is ready for PR review. **Do NOT merge into `main`.**

### Example

```bash
git checkout main && git pull origin main
git checkout -b fix/telegram-send-photo

# First logical change...
./vendor/bin/phpunit                          # pass
git add src/Platforms/Telegram/TelegramPlatform.php
git commit -m "fix: resolve Telegram sendPhoto media type detection"

# Second logical change (tests)...
./vendor/bin/phpunit                          # pass
git add tests/Unit/Platforms/Telegram/
git commit -m "test: add sendPhoto media type edge case tests"

git push origin fix/telegram-send-photo
# Inform developer: branch ready for PR
```

---

## MANDATORY: Release Process

- Releases and version bumping are handled by the **human developer ONLY**.
- Do NOT modify version numbers unless explicitly asked.
- Do NOT create git tags.
- Do NOT merge branches into `main`.

---

## MANDATORY: Project Roadmap Reference

The private project roadmap is available at `.roadmap/` (symlinked to `owlstack-roadmap` repository).

1. **READ** the roadmap before starting any task — understand priorities, planned features, and architecture decisions.
2. **Consult** these files:
   - `.roadmap/ROADMAP.md` — Phased development plan and milestones
   - `.roadmap/TODO.md` — Current task priorities and status
   - `.roadmap/ARCHITECTURE.md` — Technical architecture, repo structure, database schema
   - `.roadmap/STRATEGY.md` — Business model (Free SDK + Paid Cloud)
   - `.roadmap/REVENUE.md` — Pricing tiers and financial projections
   - `.roadmap/DESIGNER-ROADMAP.md` — Design system and brand guidelines
   - `.roadmap/USER-JOURNEY.md` — User journey maps and conversion funnels
3. **Ask questions** if a task conflicts with the roadmap.
4. **NEVER modify** any file inside `.roadmap/`. It is **read-only**.
5. **NEVER commit** anything from `.roadmap/` — it is gitignored.
6. **Align work** with the roadmap's priorities, milestones, and architecture decisions.
7. **If a task contradicts the roadmap**, inform the developer before proceeding.
8. **Do NOT update the roadmap** based on tasks you complete. The developer manages it separately.

---

## Communication Format

### When Starting a Task

Report:
1. Branch name you're creating
2. What you understood the task to be
3. Your plan (list of atomic commits)

### When Completing a Task

Report:
1. Branch name and number of commits
2. Summary of changes (what was added/changed/removed)
3. Test results (X tests, Y assertions, all passing)
4. Any concerns, technical debt, or follow-up items
5. Reminder to create PR

### When Reporting Issues

- Be specific: file path, line number, error message
- Suggest a fix with explanation
- Note if it's blocking or non-blocking

---

## Do Not

- Add framework-specific code or dependencies.
- Use static methods or global state.
- Hardcode API URLs — use configuration.
- Commit real API tokens, secrets, or credentials.
- Suppress errors with the `@` operator.
- Use `var_dump`, `print_r`, or `dd()`.
- Add Composer dependencies without approval.
- Commit code that doesn't pass all tests.
- Bundle unrelated changes into one commit.
- Push directly to `main`.
- Merge branches into `main`.
- Modify files inside `.roadmap/`.
