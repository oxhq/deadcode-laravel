# deadcode-laravel

Local incubation fork of `oxcribe` for Laravel dead code reporting and staged removals.

This repo is a local-only fork used to build the Laravel package that captures runtime truth and orchestrates dead code analysis.

## PHP Runtime Boundary

`deadcode:analyze` runs through a typed PHP runtime boundary backed by an external native supervisor.

The Laravel package owns:

- task contracts
- worker bootstrap
- progress rendering
- deadcore request orchestration

The native supervisor owns process lifecycle, stdio framing, timeout enforcement, and crash handling.
