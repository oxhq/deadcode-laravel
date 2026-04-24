# deadcode-laravel

Local incubation fork of `oxcribe` for Laravel dead code reporting and staged removals.

This repo is a local-only fork used to build the Laravel package that captures runtime truth and orchestrates dead code analysis.

## Phase 4 Boundary

`deadcode:analyze {projectPath?}` runs the supervisor-backed runtime path. The Laravel command sends an analysis task to `deadcode-supervisor`, streams task progress, and prints the generated `deadcode.analysis.v1` payload path.

`deadcode:report` does not analyze a project. It renders an existing `deadcode.analysis.v1` payload from `--input`.

## Phase 4 Usage

The current verified slice is HTTP-adjacent Laravel dead code handling plus the first execution surfaces beyond HTTP and the first model-heavy inference slice, with local report rendering and conservative staged removal only where confidence is structurally strong enough.

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

- reachability is limited to:
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
- controller-class deadness is still defined in terms of extracted controller methods
- job support is limited to:
  - `SomeJob::dispatch(...)`
  - `dispatch(new SomeJob(...))`
  - `Bus::dispatch(new SomeJob(...))`
- model-method support is limited to explicit supported calls from already-reachable surfaces
- scope support is limited to explicit conventional scope-call patterns
- relationship support is limited to explicit access plus supported eager-loading patterns
- accessor support is limited to explicit reads plus append-driven serialization support
- mutator support is limited to explicit writes, `setAttribute(...)`, and supported bulk write paths
- auto-removal is limited to high-confidence findings with a matching removal plan for:
  - `unused_controller_method`
  - `unused_form_request`
  - `unused_resource_class`
  - `unused_controller_class`
  - `unused_command_class`
  - `unused_listener_class`
  - `unused_subscriber_class`
  - `unused_job_class`
- `unused_policy_class` is report-only for now; the package does not stage or roll back policy removals
- all Phase 4 model-heavy categories stay report-only for now:
  - `unused_model_method`
  - `unused_model_scope`
  - `unused_model_relationship`
  - `unused_model_accessor`
  - `unused_model_mutator`
- rollback stores only the latest staged change set
