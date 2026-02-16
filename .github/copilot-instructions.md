# GitHub Copilot Agent Instructions

> **Read and follow ALL rules in `AGENTS.md` first.** This file contains Copilot-specific instructions only.

---

## Copilot-Specific Behavior

- **Always check existing patterns** before generating code — read a similar class in the same module first.
- **Match surrounding code style exactly** — indentation, naming, type hints, docblock style.
- **Read existing tests** before writing new ones — match the assertion style and mocking patterns used in the project.
- **Prefer reading over guessing** — if unsure about a class name, interface method, or directory structure, search the codebase.

---

## Quick Reference

| Item | Value |
|------|-------|
| Namespace | `Owlstack\Core\` maps to `src/` |
| Tests | `./vendor/bin/phpunit` — all must pass before commit |
| PHP | 8.1+ with `declare(strict_types=1)` |
| Style | PSR-12, fully typed, no `mixed` unless necessary |
| Platforms | 11 — see `src/Platforms/` |
| Interfaces | `src/Platforms/Contracts/PlatformInterface.php`, `src/Formatting/Contracts/FormatterInterface.php` |

---

## Git & Commit Rules (Reiterated)

1. **NEVER** push to `main`. Create a branch: `fix/`, `feature/`, `refactor/`, `docs/`, `test/`, `chore/`.
2. **Test before EVERY commit** — `./vendor/bin/phpunit`, only commit if all pass.
3. **Atomic commits** — one logical change per commit, passes tests independently.
4. **Conventional messages** — `fix:`, `feat:`, `refactor:`, `docs:`, `test:`, `chore:`.
5. **Multiple changes = multiple commits.**
6. Push branch and inform developer it's ready for PR.
7. **Do NOT merge into `main`.**

---

## Roadmap

- Read `.roadmap/` for project context before tasks.
- **Never modify** `.roadmap/` files — read-only reference.
- Flag conflicts between tasks and roadmap priorities.

See `AGENTS.md` for complete rules.
