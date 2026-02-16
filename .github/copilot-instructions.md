# GitHub Copilot Agent Instructions

Instructions for GitHub Copilot (Chat, Edits, Agent mode) working in this repository.

## ⚠️ MANDATORY: Git Branching Workflow

**NEVER commit or push directly to the `main` branch.**

Before making ANY code changes, you MUST:

1. **Create a branch** from `main`:
   ```bash
   git checkout main && git pull origin main
   git checkout -b <prefix>/<short-description>
   ```
   Prefixes: `fix/`, `feature/`, `refactor/`, `docs/`, `test/`, `chore/`

2. **Make all changes on that branch** — never on `main`.

3. **Use conventional commit messages**:
   - `fix: description` for bug fixes
   - `feat: description` for new features
   - `refactor: description` for refactoring
   - `docs: description` for documentation
   - `test: description` for tests
   - `chore: description` for maintenance

4. **Run tests before committing**:
   ```bash
   ./vendor/bin/phpunit
   ```

5. **Push the branch** to remote.

6. **Do NOT merge into `main`** — the developer will review and merge via Pull Request.

See `AGENTS.md` in the project root for the complete set of rules.

## ⚠️ MANDATORY: Roadmap Reference

- Read `.roadmap/` before starting any task for project context and priorities.
- Key files: `ROADMAP.md`, `TODO.md`, `ARCHITECTURE.md`, `STRATEGY.md`, `REVENUE.md`.
- **NEVER modify** roadmap files. They are read-only.
- **NEVER commit** anything from `.roadmap/`.
- Align work with roadmap priorities. Flag conflicts before proceeding.
- **Do NOT update the roadmap** based on completed tasks.

## Project Context

- **Package:** owlstack-core (framework-agnostic PHP 8.1+ social media publishing library)
- **Namespace:** `Owlstack\Core\`
- **Dependencies:** Zero framework deps (only `ext-curl`, `ext-json`)
- **Code style:** PSR-12, strict types, fully typed parameters and returns
- **Tests:** PHPUnit — all tests must pass before committing

## Key Rules

- No framework-specific code (Laravel, Symfony, WordPress).
- Contracts-first design — use interfaces in `Contracts/` subdirectories.
- Value objects are immutable (`readonly` properties).
- One class per file.
- All exceptions extend `OwlstackException`.
- No static methods or global state.
- No hardcoded API URLs.
- No real API tokens or credentials in code.
