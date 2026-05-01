# Tracy-Monolog Adapter

PHP library bridging [Tracy](https://tracy.nette.org/) debugger with [Monolog](https://github.com/Seldaek/monolog) logging. Optional [Nette DI](https://doc.nette.org/en/dependency-injection) integration via `src/Bridges/NetteDI/`.

## Install

```bash
composer install
composer require detain/tracy-monolog-adapter
```

## Architecture

**Core classes:**
- `src/TracyMonoLogger.php` — primary `Tracy\ILogger` impl (`Detain\TracyHasMono` namespace); maps Tracy priorities via `PRIORITY_MAP` to Monolog `Level` enum values, forwards to `Monolog\Logger::log()`
- `src/Logger.php` — legacy impl (`Nextras\TracyMonologAdapter` namespace); nearly identical to `TracyMonoLogger`; keep for BC
- `src/Processors/TracyExceptionProcessor.php` — Monolog processor; intercepts `Throwable` in record context, renders BlueScreen HTML to log dir via `Tracy\BlueScreen::render()`
- `src/Bridges/NetteDI/MonologExtension.php` — `Nette\DI\CompilerExtension`; wires `RotatingFileHandler`, `TracyExceptionProcessor`, `MonologLogger`, and `TracyMonoLogger` into the DI container; replaces `tracy.logger` service

**Namespaces:**
- `Detain\TracyHasMono\` → `src/` (active namespace for `TracyMonoLogger`, `Processors\`, `Bridges\`)
- `Nextras\TracyMonologAdapter\` → `src/Logger.php` only (legacy)

## Priority Mapping

| Tracy constant | Monolog level |
|---|---|
| `DEBUG` | `Level::Debug` |
| `INFO` | `Level::Info` |
| `WARNING` | `Level::Warning` |
| `ERROR` | `Level::Error` |
| `EXCEPTION` | `Level::Critical` |
| `CRITICAL` | `Level::Critical` |

Defined in `TracyMonoLogger::PRIORITY_MAP` and mirrored in `Logger::PRIORITY_MAP`. Unknown priorities fall back to `Level::Error`.

## Conventions

- Processors must be callable (`__invoke(LogRecord $record): LogRecord`) — see `TracyExceptionProcessor::__invoke()`
- Exception HTML files named `exception--Y-m-d--H-i--{hash}.html`, written to Tracy `$logDirectory`
- `MonologExtension` checks for `config['monolog']` override before building default handler stack
- `afterCompile()` in `MonologExtension` registers logger via `\Tracy\Debugger::setLogger()`
- License: BSD-3-Clause (`license.md`)

## Development Commands

```bash
composer test
composer dump-autoload
```

<!-- caliber:managed:pre-commit -->
## Before Committing

**IMPORTANT:** Before every git commit, you MUST ensure Caliber syncs agent configs with the latest code changes.

First, check if the pre-commit hook is already installed:
```bash
grep -q "caliber" .git/hooks/pre-commit 2>/dev/null && echo "hook-active" || echo "no-hook"
```

- If **hook-active**: the hook handles sync automatically — just commit normally. Tell the user: "Caliber will sync your agent configs automatically via the pre-commit hook."
- If **no-hook**: run Caliber manually before committing:
  1. Tell the user: "Caliber: Syncing agent configs with your latest changes..."
  2. Run: `caliber refresh && git add CLAUDE.md .claude/ .cursor/ .cursorrules .github/copilot-instructions.md .github/instructions/ AGENTS.md CALIBER_LEARNINGS.md 2>/dev/null`
  3. After it completes, briefly tell the user what Caliber updated. Then proceed with the commit.

If `caliber` is not found, tell the user: "This project uses Caliber for agent config sync. Run /setup-caliber to get set up."
<!-- /caliber:managed:pre-commit -->

<!-- caliber:managed:learnings -->
## Session Learnings

Read `CALIBER_LEARNINGS.md` for patterns and anti-patterns learned from previous sessions.
These are auto-extracted from real tool usage — treat them as project-specific rules.
<!-- /caliber:managed:learnings -->

<!-- caliber:managed:model-config -->
## Model Configuration

Recommended default: `claude-sonnet-4-6` with high effort (stronger reasoning; higher cost and latency than smaller models).
Smaller/faster models trade quality for speed and cost — pick what fits the task.
Pin your choice (`/model` in Claude Code, or `CALIBER_MODEL` when using Caliber with an API provider) so upstream default changes do not silently change behavior.

<!-- /caliber:managed:model-config -->

<!-- caliber:managed:sync -->
## Context Sync

This project uses [Caliber](https://github.com/caliber-ai-org/ai-setup) to keep AI agent configs in sync across Claude Code, Cursor, Copilot, and Codex.
Configs update automatically before each commit via `caliber refresh`.
If the pre-commit hook is not set up, run `/setup-caliber` to configure everything automatically.
<!-- /caliber:managed:sync -->
