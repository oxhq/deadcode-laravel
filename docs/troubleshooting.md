# Troubleshooting

## Start With `deadcode:doctor`

For the supervisor-backed analysis path, run:

```bash
php artisan deadcode:doctor
```

That preflight checks:

- the Laravel project root it is about to inspect
- whether `composer.json` exists there
- whether `deadcode` can resolve and execute the `deadcode-supervisor` binary
- whether the configured supervisor timeout is valid

If the supervisor binary is outside the default path, configure it explicitly:

```env
DEADCODE_SUPERVISOR_INSTALL_PATH=bin/deadcode-supervisor
DEADCODE_SUPERVISOR_BINARY=/absolute/path/to/deadcode-supervisor
DEADCODE_SUPERVISOR_TIMEOUT=300
```

Then rerun:

```bash
php artisan deadcode:doctor
```

## Common Analysis Failures

### `Unable to find the supervisor binary`

Install the app-local supervisor binary:

```bash
php artisan deadcode:install-supervisor v0.1.5
```

Or set `DEADCODE_SUPERVISOR_BINARY` to the executable used by `deadcode:analyze`:

```env
DEADCODE_SUPERVISOR_BINARY=/absolute/path/to/deadcode-supervisor
```

Then verify the path:

```bash
php artisan deadcode:doctor
```

### Project root is wrong

If `deadcode` is pointed at the wrong Laravel app, pass the app root explicitly:

```bash
php artisan deadcode:doctor --project-root=/absolute/path/to/app
php artisan deadcode:analyze /absolute/path/to/app
```

### `deadcode:report` says an input is required

`deadcode:report` renders an existing `deadcode.analysis.v1` payload. It no longer runs analysis itself.

Run analysis first and use the report path printed by the command:

```bash
php artisan deadcode:analyze
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=table
```

You can also render JSON and write the rendered report to a separate file:

```bash
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=json --write=storage/app/deadcode-report.json --pretty
```

## After Setup Is Green

Once `doctor` and `analyze` are working on your app, inspect the generated analysis payload and render it with `deadcode:report` before staging removals:

```bash
php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=table
php artisan deadcode:apply --input=storage/app/deadcode/analysis.json --dry-run
```
