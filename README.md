# deadcode-laravel

Laravel dead code pruning with evidence, staged deletion, and rollback.

`deadcode-laravel` boots the host Laravel app, captures runtime truth, calls the Rust `deadcore` engine, renders the `deadcode.analysis.v1` report, and stages only the conservative removals that the current policy allows.

## Command Flow

```bash
php artisan deadcode:doctor
php artisan deadcode:analyze
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=json
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=table
php artisan deadcode:apply --input=storage/app/deadcode/analysis.json --dry-run
php artisan deadcode:apply --input=storage/app/deadcode/analysis.json --stage
php artisan deadcode:rollback
```

`deadcode:analyze {projectPath?}` is supervisor-backed. The command sends an analysis task to `deadcode-supervisor`, streams task progress, and prints the generated `deadcode.analysis.v1` payload path.

`deadcode:report` does not analyze a project. It only renders an existing analysis payload from `--input`.

`deadcode:apply --dry-run` explains what would be staged and which findings were skipped. `deadcode:apply --stage` writes conservative edits and stores rollback data for the latest staged change set.

## Installation

Require the package from Packagist and publish configuration:

```bash
composer require deadcode/deadcode-laravel
php artisan vendor:publish --tag=deadcode-config
```

Install or build the `deadcore` binary:

```bash
php artisan deadcode:install-binary v0.1.5
php artisan deadcode:install-supervisor v0.1.5
```

For local development against a source checkout:

```bash
php artisan deadcode:install-binary v0.1.5 --source-root=/absolute/path/to/deadcore --prefer-source
```

Configure explicit binary paths when defaults are not valid for the host app:

```env
DEADCODE_SUPERVISOR_INSTALL_PATH=bin/deadcode-supervisor
DEADCODE_SUPERVISOR_BINARY=/absolute/path/to/deadcode-supervisor
DEADCODE_SUPERVISOR_TIMEOUT=300
DEADCORE_BINARY=/absolute/path/to/deadcore
DEADCORE_SOURCE_ROOT=/absolute/path/to/deadcore
DEADCORE_WORKING_DIRECTORY=/absolute/path/to/your/laravel/app
DEADCORE_TIMEOUT=120
```

See [docs/installation.md](docs/installation.md) for the longer install path.
See [docs/fixtures.md](docs/fixtures.md) for the package fixture map and verification boundaries.

## Release Status

`v0.1.5` is the current coordinated public release:

- `deadcode-laravel` provides the Artisan workflow and Laravel runtime snapshot.
- `deadcore` provides the Rust analysis engine and `deadcode.analysis.v1`.
- `go-supervisor` provides the native JSONL worker supervisor used by `deadcode:analyze`.

The GitHub releases publish checksum-verified Windows, Linux, and macOS binaries for the native components.

## Current Coverage

The current verified slice reports:

- controller methods
- dead controller classes
- explicit typed `FormRequest` classes
- direct supported resource usage
- runtime-registered command classes
- runtime-registered listener classes
- explicitly registered subscriber classes
- job classes reached from supported explicit dispatch patterns
- policy classes from the runtime Gate policy map
- model helper methods reached from supported explicit calls
- local scopes reached from supported explicit scope-call patterns
- relationship methods reached from supported explicit access and eager-loading patterns
- legacy and modern accessors/mutators reached from supported explicit reads, writes, and append-style metadata

`deadcode:report` renders compact `reasonSummary` values plus structured `reachabilityReasons` and `evidence` emitted by `deadcore`.

`deadcode:apply --dry-run` explains skipped findings with planner decisions such as report-only category, insufficient confidence, missing range, missing removal plan, or non-isolated removal plan.

## Remediation Policy

Auto-removal is limited to high-confidence findings with a matching removal plan for:

- `unused_controller_method`
- `unused_form_request`
- `unused_resource_class`
- `unused_controller_class`
- `unused_command_class`
- `unused_listener_class`
- `unused_subscriber_class`
- `unused_job_class`

Report-only categories:

- `unused_policy_class`
- `unused_model_method`
- `unused_model_scope`
- `unused_model_relationship`
- `unused_model_accessor`
- `unused_model_mutator`

Rollback currently restores only the latest staged change set.

## Limits

- Controller-class deadness is defined by extracted controller methods.
- Job support is limited to `SomeJob::dispatch(...)`, `dispatch(new SomeJob(...))`, and `Bus::dispatch(new SomeJob(...))`.
- Model-method support is limited to explicit supported calls from already-reachable surfaces.
- Scope support is limited to explicit conventional scope-call patterns.
- Relationship support is limited to explicit access plus supported eager-loading patterns.
- Accessor support is limited to explicit reads plus append-driven serialization support.
- Mutator support is limited to explicit writes, `setAttribute(...)`, and supported bulk write paths.
- The package renders engine explanations; it does not synthesize deeper reasoning than `deadcore` emitted.
