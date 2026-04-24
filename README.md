# deadcode-laravel

Local incubation fork of `oxcribe` for Laravel dead code reporting and staged removals.

This repo is a local-only fork used to build the Laravel package that captures runtime truth and orchestrates dead code analysis.

## Phase 2 Boundary

`deadcode:analyze {projectPath?}` runs the supervisor-backed runtime path. The Laravel command sends an analysis task to `deadcode-supervisor`, streams task progress, and prints the generated `deadcode.analysis.v1` payload path.

`deadcode:report` does not analyze a project. It renders an existing `deadcode.analysis.v1` payload from `--input`.

## Phase 2 Usage

The current verified slice is HTTP-adjacent Laravel dead code handling with local report rendering plus conservative staged removal and rollback.

```bash
php artisan deadcode:doctor
php artisan deadcode:analyze
php artisan deadcode:analyze /absolute/path/to/laravel-app
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=json
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=table
php artisan deadcode:apply --input=storage/app/deadcode/analysis.json --dry-run
php artisan deadcode:apply --input=storage/app/deadcode/analysis.json --stage
php artisan deadcode:rollback
```

When passing a project path, the supervisor must bootstrap the PHP worker for that same Laravel app. The worker rejects mismatches so runtime routes, package manifest, deadcore execution, and output path stay aligned.

Set `DEADCODE_SUPERVISOR_BINARY` when the default `../go-supervisor/bin/deadcode-supervisor` path is not correct for the host app:

```env
DEADCODE_SUPERVISOR_BINARY=/absolute/path/to/deadcode-supervisor
DEADCODE_SUPERVISOR_TIMEOUT=300
```

Current limits:

- reachability is limited to controller methods, dead controller classes, explicit typed `FormRequest` classes, and direct supported resource usage
- controller-class deadness is still defined in terms of extracted controller methods
- auto-removal is limited to high-confidence findings with a matching removal plan for:
  - `unused_controller_method`
  - `unused_form_request`
  - `unused_resource_class`
  - `unused_controller_class`
- rollback stores only the latest staged change set
