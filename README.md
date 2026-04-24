# deadcode-laravel

Local incubation fork of `oxcribe` for Laravel dead code reporting and staged removals.

This repo is a local-only fork used to build the Laravel package that captures runtime truth and orchestrates dead code analysis.

## Phase 1 Boundary

`deadcode:analyze` bootstraps Laravel, captures runtime route truth, and sends a `deadcode.analysis.v1` request directly to `deadcore`.

## Phase 1 Usage

The current verified slice is controller reachability with local report rendering plus conservative staged removal and rollback.

```bash
php artisan deadcode:doctor
php artisan deadcode:analyze --write=storage/app/deadcode-report.json
php artisan deadcode:report --input=storage/app/deadcode-report.json --format=json
php artisan deadcode:report --input=storage/app/deadcode-report.json --format=table
php artisan deadcode:apply --input=storage/app/deadcode-report.json --dry-run
php artisan deadcode:apply --input=storage/app/deadcode-report.json --stage
php artisan deadcode:rollback
```

Current limits:

- only controller and controller-method reachability is implemented
- auto-removal is limited to high-confidence `unused_controller_method` findings with a matching removal plan
- rollback stores only the latest staged change set
