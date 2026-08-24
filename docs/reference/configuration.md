# Configuration

All configuration lives under a single key in the root `composer.json`.

```json
{
    "extra": {
        "hpbxxtr-upgrade-interactive": {
            "minimum-release-age": "7d"
        }
    }
}
```

## Options

### `minimum-release-age`

- **Type:** string (duration) or integer (days)
- **Default:** none — the gate is off
- **CLI equivalent:** `--min-age=<duration>` (always wins over this value)

Blocks selection of versions published less recently than the threshold. `0` disables the gate.
Behaviour is described in [Minimum release age](/guide/minimum-release-age).

An integer is read as a number of days, so these two are equivalent:

```json
{ "minimum-release-age": 14 }
{ "minimum-release-age": "14d" }
```

Any other JSON type is rejected:

```
extra.hpbxxtr-upgrade-interactive.minimum-release-age must be a string or an integer number of days.
```

## Duration syntax

A duration is a whole number followed by an optional unit. Whitespace between the two is allowed and
the value is case-insensitive.

| Unit | Aliases | Length |
|---|---|---|
| Day | `d`, `day`, `days` | 1 day |
| Week | `w`, `week`, `weeks` | 7 days |
| Month | `m`, `month`, `months` | 30 days |
| Year | `y`, `year`, `years` | 365 days |

Omitting the unit means days. All of these are valid: `14`, `14d`, `2 w`, `3months`, `1Y`.

Months and years are **fixed** lengths rather than calendar spans, so a threshold always means the
same number of days no matter when the command runs.

An unparsable value aborts with exit code `1`:

```
Invalid duration "2 fortnights". Expected a number of days (e.g. "14") or a value with a unit: "7d", "2w", "3m", "1y".
```

## Precedence

1. `--min-age=<duration>` on the command line
2. `extra.hpbxxtr-upgrade-interactive.minimum-release-age` in the root `composer.json`
3. Off

An empty `--min-age=` falls through to the `extra` value rather than disabling the gate; pass
`--min-age=0` to switch it off for a single run.
