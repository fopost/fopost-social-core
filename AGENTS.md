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

## ⚠️ MANDATORY: Project Roadmap Reference

The private project roadmap is available at `.roadmap/` in the project root (symlinked to `owlstack-roadmap` repository).

### Roadmap Rules

1. **READ the roadmap before starting any task** to understand project priorities, planned features, and architecture decisions.
2. **Consult these roadmap files** for context:
   - `.roadmap/ROADMAP.md` — Phased development plan and milestones
   - `.roadmap/TODO.md` — Current task priorities and status
   - `.roadmap/ARCHITECTURE.md` — Technical architecture, repo structure, database schema
   - `.roadmap/STRATEGY.md` — Business model (Free SDK + Paid Cloud)
   - `.roadmap/REVENUE.md` — Pricing tiers and financial projections
   - `.roadmap/DESIGNER-ROADMAP.md` — Design system and brand guidelines
   - `.roadmap/USER-JOURNEY.md` — User journey maps and conversion funnels
3. **Ask questions** if a task conflicts with or is unclear in relation to the roadmap.
4. **NEVER modify** any file inside `.roadmap/`. It is **read-only** reference material.
5. **NEVER commit** anything from `.roadmap/` — it is gitignored and symlinked.
6. **Align your work** with the roadmap's priorities, milestones, and architecture decisions.
7. **If a requested task contradicts the roadmap**, inform the user about the conflict before proceeding.
8. **Do NOT update the roadmap** based on tasks you complete. The developer manages the roadmap separately.

---

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

### Step 3: Test Before Every Commit

**You MUST run the test suite and confirm all tests pass BEFORE every commit.** This is non-negotiable.

```bash
./vendor/bin/phpunit
```

- If tests fail, **fix the issue first** — do NOT commit failing code.
- If you added new code, **add or update tests** for it before committing.
- A pre-commit git hook is configured to enforce this automatically.

### Step 4: Atomic Commits with Conventional Messages

**Every commit must be one self-contained logical change.** This is called an "atomic commit."

#### What is an Atomic Commit?

- Each commit represents **exactly one logical change** (one fix, one feature, one refactor).
- Each commit **passes all tests independently** — the codebase is never broken at any commit.
- Each commit can be **reverted, cherry-picked, or reviewed on its own** without side effects.

#### What is NOT an Atomic Commit?

- Mixing a bug fix with an unrelated refactor in one commit.
- Committing half-finished work that breaks tests.
- A single giant commit with multiple unrelated changes.
- Commit messages like "WIP", "misc changes", "updates".

#### Conventional Commit Format

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

#### Multiple Changes = Multiple Commits

If a task involves several changes, split them into separate atomic commits:

```bash
# Good: three atomic commits
git commit -m "fix: correct Telegram entity offset calculation"
git commit -m "test: add edge case tests for Telegram entities"
git commit -m "docs: update Telegram platform usage examples"

# Bad: one giant commit
git commit -m "fix Telegram stuff and update docs and tests"  # ❌ NEVER
```

### Step 5: Push the Branch

```bash
git push origin fix/short-description
```

### Step 6: Inform the Developer

After pushing, inform the user that:
- The branch is ready for review.
- A Pull Request should be created to merge into `main`.
- **Do NOT run `git merge` into `main` yourself.**

### Complete Example

```bash
git checkout main
git pull origin main
git checkout -b fix/telegram-send-photo

# Make first logical change...
./vendor/bin/phpunit                          # ✅ tests pass
git add src/Platforms/Telegram/TelegramPlatform.php
git commit -m "fix: resolve Telegram sendPhoto media type detection"

# Make second logical change (related tests)...
./vendor/bin/phpunit                          # ✅ tests pass
git add tests/Unit/Platforms/Telegram/
git commit -m "test: add sendPhoto media type edge case tests"

# Push
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
