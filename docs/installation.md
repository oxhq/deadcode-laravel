# Installation

## 1. Require The Package

After the Packagist listing is live:

```bash
composer require deadcode/deadcode-laravel
php artisan vendor:publish --tag=deadcode-config
```

Until then, install from the public GitHub repository:

```bash
composer config repositories.deadcode-laravel vcs https://github.com/garaekz/deadcode-laravel
composer require deadcode/deadcode-laravel:^0.1.5
php artisan vendor:publish --tag=deadcode-config
```

The package still accepts the inherited `oxcribe-config` tag for compatibility, but new installs should use `deadcode-config`.

## 2. Install Or Build The Analysis Engine

Fast path:

```bash
php artisan deadcode:install-binary v0.1.5
php artisan deadcode:install-supervisor v0.1.5
```

That downloads the matching `deadcore` and `deadcode-supervisor` release binaries when release assets are available, verifies the published checksums, and installs them into the app-local binary paths used by the analysis stack.

Local source path:

```bash
php artisan deadcode:install-binary v0.1.5 --source-root=/absolute/path/to/deadcore --prefer-source
```

Environment equivalent:

```env
DEADCORE_SOURCE_ROOT=/absolute/path/to/deadcore
```

When `DEADCORE_SOURCE_ROOT` is configured, `deadcode:install-binary` can fall back to building from source if the tagged release is missing binary assets or checksums.

Manual engine build:

```bash
cargo build --locked --release
cargo test --locked
```

## 3. Configure Binary Paths

```env
DEADCODE_SUPERVISOR_INSTALL_PATH=bin/deadcode-supervisor
DEADCODE_SUPERVISOR_BINARY=/absolute/path/to/deadcode-supervisor
DEADCODE_SUPERVISOR_RELEASE_REPOSITORY=garaekz/go-supervisor
DEADCODE_SUPERVISOR_RELEASE_BASE_URL=https://github.com
DEADCODE_SUPERVISOR_RELEASE_VERSION=v0.1.5
DEADCODE_SUPERVISOR_TIMEOUT=300
DEADCORE_BINARY=/absolute/path/to/deadcore
DEADCORE_SOURCE_ROOT=/absolute/path/to/deadcore
DEADCORE_RELEASE_REPOSITORY=garaekz/deadcore
DEADCORE_WORKING_DIRECTORY=/absolute/path/to/your/laravel/app
DEADCORE_TIMEOUT=120
```

`deadcode:analyze` uses `DEADCODE_SUPERVISOR_BINARY`; when that is unset, the resolver uses `DEADCODE_SUPERVISOR_INSTALL_PATH` and appends `.exe` on Windows when needed. The supervisor owns the runtime task execution path.

For local source development with the sibling Go supervisor checkout:

```bash
cd /absolute/path/to/go-supervisor
go test ./...
go build -o bin/deadcode-supervisor ./cmd/deadcode-supervisor
```

On Windows:

```powershell
cd C:\path\to\go-supervisor
go test ./...
go build -o bin\deadcode-supervisor.exe .\cmd\deadcode-supervisor
```

Then point the Laravel app at the built binary or install/copy it into the app-local supervisor path:

```env
DEADCODE_SUPERVISOR_BINARY=/absolute/path/to/go-supervisor/bin/deadcode-supervisor
```

The supervisor runs the PHP worker from the installed package by default. If your layout is non-standard, set `DEADCODE_WORKER_SCRIPT` to the package worker script and `DEADCODE_WORKER_BOOTSTRAP` to the target Laravel app's `bootstrap/app.php`.

If the `deadcore` binary is already on `PATH`, `DEADCORE_BINARY` can stay as `deadcore`. After `deadcode:install-binary`, an absolute `DEADCORE_BINARY` path is usually unnecessary.

## 4. Run Analysis

```bash
php artisan deadcode:doctor
php artisan deadcode:analyze
```

For a different Laravel app root, the supervisor must bootstrap the PHP worker for that same root:

```bash
php artisan deadcode:analyze /absolute/path/to/laravel-app
```

The worker rejects mismatches between the bootstrapped Laravel app and the requested project path. That keeps the runtime snapshot, manifest root, `deadcore` working directory, and output path aligned.

`deadcode:analyze` prints the generated `deadcode.analysis.v1` payload path. Use that path for report rendering, remediation, and rollback workflows.

## 5. Render Reports

```bash
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=json --write=storage/app/deadcode-report.json --pretty
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=table
```

The JSON report includes engine-provided `reasonSummary`, `reachabilityReasons`, and `evidence` when the analysis payload carries them. The table report includes a compact reason column.

## 6. Review And Stage Removals

Dry run first:

```bash
php artisan deadcode:apply --input=storage/app/deadcode/analysis.json --dry-run
```

Dry-run output includes skipped findings and planner-side reasons for non-stageable findings.

Stage conservative removals:

```bash
php artisan deadcode:apply --input=storage/app/deadcode/analysis.json --stage
```

Rollback the latest staged change set:

```bash
php artisan deadcode:rollback
```

If preflight fails, start with [troubleshooting.md](troubleshooting.md).
