# CLAUDE.md

> **Read and follow ALL rules in `AGENTS.md` first.** This file contains Claude-specific instructions only.
> Do NOT treat this file as standalone — `AGENTS.md` is the source of truth for all rules.

---

## Context Loading Priority

When starting a new session, read files in this order:

1. `AGENTS.md` — mandatory rules (architecture, code style, git workflow, testing)
2. `.roadmap/TODO.md` — current task priorities and what's done
3. `.roadmap/ROADMAP.md` — which development phase we're in
4. `.roadmap/ARCHITECTURE.md` — technical decisions already made
5. The specific files related to the current task

Skip reading entire directories. Target the specific files relevant to the task — use search and grep to locate code efficiently.

---

## Thinking Approach

- **Complex architectural decisions**: Use extended thinking. Analyze existing patterns, contracts, and dependency chains before proposing changes.
- **Simple bug fixes**: Proceed directly. Read the failing code, understand the issue, fix it.
- **New platform implementation**: Study one existing platform (e.g., `src/Platforms/Telegram/`) as a reference before creating new files.
- **Refactoring**: Map out ALL usages of the code being changed before making modifications. Use grep/find to discover callers and dependents.

---

## Tool Usage

- **Prefer grep and find** to locate code — never guess file locations or class names.
- **Read existing code patterns** before writing new code — match the surrounding style.
- **Run tests after every change**, not just at the end — catch issues early.
- **Use `git diff`** to verify your changes before committing — ensure nothing unexpected was modified.
- **Use `git status`** to confirm which files are staged before committing.
- **Read the test file** for a class before modifying it — understand what's already tested.

---

## Session Management

- If a task requires many changes, **outline the plan first** and present it before starting.
- **Commit after each logical step** — don't accumulate a large batch of uncommitted changes.
- If context becomes unclear during a long session, **re-read `AGENTS.md`** to reset.
- When switching between different parts of the codebase, **re-orient** by reading the relevant module's contracts/interfaces first.

---

## Git & Commit Rules (Reiterated)

1. **NEVER** commit or push directly to `main`. Create a branch first.
2. **Test before EVERY commit** — `./vendor/bin/phpunit`, only commit if all pass.
3. **Atomic commits** — one logical change per commit, independently valid.
4. **Conventional messages** — `fix:`, `feat:`, `refactor:`, `docs:`, `test:`, `chore:`.
5. **Multiple changes = multiple commits** — never bundle unrelated changes.
6. Push the branch and inform the developer it's ready for PR.
7. **NEVER merge into `main`**. The developer handles merging and releases.
8. **Do NOT update the roadmap** based on completed tasks.
