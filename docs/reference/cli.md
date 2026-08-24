# CLI reference

## Command

```bash
composer hpbxxtr:upgrade-interactive [options]
```

| | |
|---|---|
| Name | `hpbxxtr:upgrade-interactive` |
| Aliases | `h:ui`, `upgrade-interactive` |
| Description | Interactively select and upgrade outdated dependencies |

## Options

| Option | Description |
|---|---|
| `--caret` | Write a caret range constraint (e.g. `^1.2.3`) instead of an exact version (`1.2.3`) |
| `--min-age=<duration>` | Minimum release age before a version may be selected. Accepts `7d`, `2w`, `3m`, `1y` or a bare number of days (`14`). `0` disables the gate. |

Neither option has a short form. Global Composer options (`-v`, `--no-ansi`, `--working-dir`, …)
work as usual.

### `--caret`

Pin exact versions (default):

```bash
composer h:ui
# writes: "vendor/pkg": "1.2.3"
```

Allow future minor and patch updates:

```bash
composer h:ui --caret
# writes: "vendor/pkg": "^1.2.3"
```

### `--min-age`

Skip releases younger than two weeks:

```bash
composer h:ui --min-age=2w
```

The option overrides `extra.hpbxxtr-upgrade-interactive.minimum-release-age` from `composer.json`.
See [Minimum release age](/guide/minimum-release-age) for the behaviour and
[Configuration](/reference/configuration) for the accepted syntax.

## Exit codes

| Code | Condition | Output |
|---|---|---|
| `0` | Upgrades applied successfully | — |
| `0` | Nothing to upgrade | `All direct dependencies are up to date.` |
| `0` | The TUI was confirmed with no selection | `No packages selected. Nothing to do.` |
| `1` | Malformed `--min-age` or `extra` threshold | `Invalid duration "…". Expected a number of days (e.g. "14") or a value with a unit: "7d", "2w", "3m", "1y".` |
| `1` | No TTY available | `upgrade-interactive requires an interactive terminal.` |
| `1` | Resolution failed (network, repository, metadata) | the underlying exception message |
| `1` | `composer update` failed while applying | the underlying exception message |

Aborting with `Ctrl+C` leaves `composer.json` untouched.

::: warning Non-interactive use
There is no `--no-interaction` mode. The command exists to make a human choose, so with no TTY it
refuses rather than guessing. Use `composer outdated` in CI.
:::
