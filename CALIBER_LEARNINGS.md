# Caliber Learnings

Accumulated patterns and anti-patterns from development sessions.
Auto-managed by [caliber](https://github.com/caliber-ai-org/ai-setup) — do not edit manually.

- **[gotcha]** `TracyExceptionProcessor` exception file-reuse tests must use the **same exception instance** for both invocations. The hash is derived from `(string) $exception`, which includes the stack trace — two `new RuntimeException('same-message')` calls at different call sites produce different hashes and will not reuse the same file. Assign the exception to a variable before the first `$processor(...)` call and pass that same variable to both.
- **[pattern]** After writing each PHP source file, run `php -l <file>` immediately to catch syntax errors before running the full test suite. This catches issues in seconds rather than after the slower `vendor/bin/phpunit` cycle.
- **[pattern]** PHPUnit is invoked as `vendor/bin/phpunit` using `phpunit.xml.dist` in the project root. No extra flags are needed for a default run; `--testdox` is useful for human-readable output.
- **[gotcha]** When writing a Nette DI integration test using `ContainerLoader`, you must manually pre-register `Tracy\BlueScreen` as a service named `tracy.blueScreen` in the test container config. `MonologExtension` references it via `'@Tracy\BlueScreen'` for the `TracyExceptionProcessor` constructor and the container will fail to compile without it.
- **[gotcha]** When writing multiple Nette DI integration tests using `ContainerLoader`, pass a unique cache key per test (e.g., `__METHOD__ . sha1(serialize($uniqueParam))`) to prevent stale compiled containers being loaded from cache when tests share a cache directory.
- **[env]** Caliber is not on PATH in this project directory. Use the full path `/home/my/.nvm/versions/node/v24.15.0/bin/caliber refresh`. It produces no stdout/stderr output on a successful refresh — an empty result is normal, not a failure.
- **[gotcha]** PHPUnit cache files (`.phpunit.cache/`, `.phpunit.result.cache`) should be added to `.gitignore` — they are generated at test runtime and are not part of the source tree.
