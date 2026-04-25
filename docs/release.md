# Release Checklist

## Before Tagging

- `composer validate --strict`
- `./vendor/bin/pest`
- `cargo test --locked` in `deadcore`
- `cargo build --locked --release` in `deadcore`
- verify `deadcode:install-binary` still matches the current `deadcore` release/build contract
- verify the GitHub Actions matrix passes for Laravel `10`, `11`, `12` and `13`
- verify the GitHub Actions Windows smoke job passes
- verify the GitHub Actions install-proof job builds `deadcore` from the sibling GitHub repo, runs the installed binary, and confirms `deadcode.analysis.v1`

## Local Install Proof

Before tagging a preview, verify the source-build fallback against the real `deadcore` checkout:

```bash
vendor/bin/testbench deadcode:install-binary v0.1.4 \
  --path=/tmp/deadcode-proof/bin/deadcore \
  --source-root=/absolute/path/to/deadcore \
  --prefer-source \
  --force
```

On Windows, pass `--os=windows --arch=amd64` when you need to prove the `.exe` install path explicitly.

Then smoke the installed binary:

```bash
/tmp/deadcode-proof/bin/deadcore --version
/tmp/deadcode-proof/bin/deadcore \
  --request /absolute/path/to/deadcore/test/fixtures/contracts/deadcode/controller-basic.json \
  --out /tmp/deadcode-proof/controller-basic.analysis.json
```

The output payload must keep `contractVersion` equal to `deadcode.analysis.v1`.

## Local Doctor Proof

`deadcode:doctor` also needs a supervisor binary. If the native supervisor is not part of the current release candidate, use a tiny local executable test double only for preflight proof and label the result honestly:

```bash
DEADCORE_BINARY=/tmp/deadcode-proof/bin/deadcore \
DEADCODE_SUPERVISOR_BINARY=/tmp/deadcode-proof/bin/deadcode-supervisor \
vendor/bin/testbench deadcode:doctor --project-root=/absolute/path/to/laravel-app
```

That proves package preflight wiring and binary resolution. It does not prove `deadcode:analyze` end to end unless the real supervisor and a real Laravel app are used.

## Package Metadata

- keep `composer.json` without a hardcoded `version`
- update `CHANGELOG.md`
- make sure docs mention current limitations and supported stacks
- verify `deadcode:install-binary` still works against the tagged `deadcore` release and against a local `DEADCORE_SOURCE_ROOT` checkout

## Binary Contract

`deadcode:install-binary` expects the tagged `deadcore` release to expose:

- platform binaries named `deadcore_<tag>_<os>_<arch>[.exe]`
- a `checksums.txt` file in the same release

If a release is missing those assets, the supported fallback is to configure `DEADCORE_SOURCE_ROOT` and build from source locally.

## Real App Smoke

- install `deadcode/deadcode-laravel` in at least one real Laravel app
- publish config
- set `DEADCODE_SUPERVISOR_BINARY` when the host app cannot use the default `../go-supervisor/bin/deadcode-supervisor` path
- run `php artisan deadcode:doctor`
- run `php artisan deadcode:analyze`
- run `php artisan deadcode:report --input=storage/app/deadcode/analysis.json --format=json --write=storage/app/deadcode-report.json --pretty`
- inspect output for runtime progress, finding totals, report path generation, and rendered report contents
- if you do not have an external app yet, rerun the owned-app smoke before tagging and clearly label the release as a preview

## Proof Boundaries

Local package proof can verify tests, command registration, source-install fallback, installed `deadcore` execution, and `deadcode:doctor` preflight wiring.

It does not verify Packagist publication, GitHub release assets, hosted CI, the real native supervisor, or an external consumer app. Keep release notes scoped to the highest proof level actually completed.
