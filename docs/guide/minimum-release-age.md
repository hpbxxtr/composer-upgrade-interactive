# Minimum release age

A release published minutes ago is the riskiest one to install. The minimum release age defines a
cooldown: versions younger than the threshold stay visible but cannot be selected. They are marked
with `⊘`, and the footer explains why:

```
released 3 days ago · blocked by min age 7d
```

## Setting the threshold

Per run, with the CLI option:

```bash
composer h:ui --min-age=2w
```

Project-wide, in `composer.json` — the CLI option always wins over it:

```json
{
    "extra": {
        "hpbxxtr-upgrade-interactive": {
            "minimum-release-age": "7d"
        }
    }
}
```

Both accept the same syntax; see [Configuration](/reference/configuration#duration-syntax) for every
accepted form. `0` disables the gate.

## Retuning it inside the TUI

Press `a` to edit the threshold without restarting. The field opens prefilled with the current value;
type any duration the option accepts, then `enter` to apply, `esc` to discard, `Ctrl+U` to clear.
An empty value turns the gate off. An unparsable value keeps the field open and shows why.

```
  min age  ▸ 2w▏
           type a duration (7d · 2w · 3m · 1y) · enter apply · esc cancel · ctrl+u clear · empty = off
```

Tightening the threshold drops selections it no longer allows; loosening it makes previously blocked
versions selectable again. Nothing is re-fetched — every displayed version already carries its
release date.

## Details worth knowing

- Release dates come from the repository metadata (`time` in Packagist v2 metadata). No extra
  requests are made.
- A version with **no** release date — path repositories, some private VCS repositories, `dev-*`
  branches — is treated as **eligible**. The gate never hides a package just because its repository
  omits the field; such targets show `release date unknown`.
- Units are fixed lengths: `1w` = 7 days, `1m` = 30 days, `1y` = 365 days. Calendar arithmetic would
  make the cutoff depend on the day the command runs.
- The threshold applies to the **direct** dependencies listed in the table. Applying the upgrades
  runs `composer update --with-all-dependencies`, so transitive dependencies still resolve to their
  newest matching versions — Composer offers no knob to constrain them.
- Packagist reports the tag publication time. Re-tagging a release resets it. Treat this as a
  cooldown, not a supply-chain guarantee.
