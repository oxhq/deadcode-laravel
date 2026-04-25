# Changelog

## v0.1.5

- prepare the package for Packagist consumption with public package metadata
- refresh README and installation docs around the current coordinated release
- keep runtime behavior unchanged from `v0.1.4`

## v0.1.4

- first coordinated `deadcode-laravel`, `deadcore`, and `go-supervisor` release
- add the Laravel Artisan workflow for `deadcode:doctor`, `deadcode:analyze`, `deadcode:report`, `deadcode:apply`, and `deadcode:rollback`
- install checksum-verified `deadcore` and `deadcode-supervisor` release binaries
- emit and render the `deadcode.analysis.v1` report contract
- stage only conservative high-confidence removals and keep rollback data for the latest staged change set
- verify hosted CI, GitHub releases, Windows binary installation, and owned Laravel dogfood analysis
