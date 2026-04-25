# Fixtures And Verification

`deadcode-laravel` keeps package fixtures under `tests/Fixtures` and command/report payload fixtures in Pest helper functions. These fixtures prove the Laravel package behavior around runtime capture, report rendering, conservative staging, and rollback.

## Package Fixtures

| Fixture | What It Proves |
| --- | --- |
| `tests/Fixtures/Runtime` | the PHP worker bootstrap can execute typed runtime tasks through the supervisor protocol |
| `tests/Fixtures/SpatieLaravelApp` | route/runtime serialization handles Spatie-style DTOs, resources, filters, and model metadata used by the engine contract |
| `tests/Fixtures/PolicyLaravelApp` | policy-related runtime surfaces are captured without widening policy remediation |
| `tests/Fixtures/AuthErrorLaravelApp` | runtime snapshot extraction handles secured routes and controller/resource shapes used by compatibility tests |
| `tests/Fixtures/InertiaLaravelApp` | route snapshot extraction tolerates web/Inertia-style controllers without turning docs-only behavior back on |

## Command Coverage

Run all package checks:

```bash
composer test
```

Focused checks:

```bash
vendor/bin/pest tests/Feature/DoctorCommandTest.php
vendor/bin/pest tests/Feature/DeadCodeRemediationCommandsTest.php
vendor/bin/pest tests/Feature/Runtime/WorkerBootstrapTest.php
vendor/bin/pest tests/Unit/Tasks/AnalyzeProjectTaskHandlerTest.php
```

These are the practical proof boundaries:

- `DoctorCommandTest` proves preflight output and binary resolution behavior.
- `DeadCodeRemediationCommandsTest` proves report rendering, dry-run decisions, conservative staged deletion, and rollback.
- `WorkerBootstrapTest` proves the local PHP runtime worker can execute a typed task.
- `AnalyzeProjectTaskHandlerTest` proves runtime capture, `deadcore` invocation, raw `deadcode.analysis.v1` output writing, and project-root mismatch rejection.

## What Fixtures Do Not Prove

- They do not prove hosted release publishing.
- They do not prove arbitrary dynamic Laravel code is safe to delete.
- They do not prove report-only categories are safe to stage.
- They do not prove a consumer app unless that app is bootstrapped and analyzed directly.

The safe rule stays: if a category is ambiguous, render it first and require explicit review before deletion.
