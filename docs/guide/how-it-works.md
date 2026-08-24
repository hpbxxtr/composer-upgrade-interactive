# How it works

1. **Resolution** — iterates the local Composer repository via internal Composer APIs and finds the
   best available patch, minor and major target for every direct dependency.
2. **Initial compatibility check** — before the TUI opens, every candidate version is checked against
   the currently installed package set. Conflicting versions are pre-marked with `!` when the table
   first renders.
3. **Table** — one row per outdated package, three columns for patch / minor / major. Cells marked
   `!` indicate conflicts with the current state; the footer explains the broken dependency chain.
4. **Version picker** — pressing `v` on any cell fetches all stable releases for that bump level from
   Packagist and renders them as a side-by-side column picker, grouped by series (e.g. `1.4.x`,
   `1.3.x`). Each version is checked for compatibility before the picker opens.
5. **Live conflict recheck** — every time a selection changes, all visible versions are re-evaluated
   against the updated effective world (see below).
6. **Age gate** — when a minimum release age is configured, versions younger than the threshold are
   marked `⊘` and refuse selection. See [Minimum release age](/guide/minimum-release-age).
7. **Apply** — writes updated constraints to `composer.json` (exact version by default, caret range
   with `--caret`) then runs `composer update --with-all-dependencies` for all selected packages in
   one pass.

## Conflict detection

Compatibility is checked against an **effective world** that combines three layers:

| Layer | Description |
|---|---|
| Installed base | All currently installed direct dependencies and their versions |
| Selection overrides | Any packages you have already selected for upgrade in this session — their selected version replaces the installed one |
| Candidate override | The specific version being evaluated replaces the installed version of that package in the world |

A conflict is reported when:

- **Forward** — the candidate version declares a `require` that is not satisfied by another package's
  version in the effective world (e.g. `vendor/pkg 2.0` requires `vendor/dep ^3.0` but
  `vendor/dep 2.8` is installed)
- **Backward** — another package in the effective world declares a `require` on the candidate package
  that the candidate's version does not satisfy (e.g. `vendor/other 1.5` requires `vendor/pkg ^1.0`
  but you are upgrading to `vendor/pkg 2.0`)

The conflict footer shows up to five conflicts at a time. Each line identifies the dependent package,
the constraint it declares, and whether the conflicting dependency comes from a **selected upgrade**
(`selected: X`) — meaning you can potentially resolve it by also upgrading that package — or from an
**installed package** (`installed: X` / `update available`) that is not part of the current upgrade
set.

::: info
A conflict marker is information, not a hard block. You can still select a conflicting version —
Composer will have the final say when the update runs.
:::
