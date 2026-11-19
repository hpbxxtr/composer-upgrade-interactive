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

## Composer's cooldown policy

Composer 2.11 added its own cooldown, [`config.policy.cooldown`](https://getcomposer.org/doc/06-config.md#cooldown):

```json
{
    "config": {
        "policy": {
            "cooldown": {
                "period": "7 days"
            }
        }
    }
}
```

When Composer would block an update with it, the upgrade picker respects it too. A version still in
the cooldown period is marked `⊘` and cannot be selected, and the footer names the cooldown instead of
the minimum release age:

```
released 2 days ago · blocked by composer cooldown 7d
```

The help line shows both values: `a min age (2w · cooldown 7d)`.

The cooldown is a **floor**, not a default. The minimum release age can make the gate stricter, but
`--min-age=0`, an empty `a` input or a shorter `extra` value never makes a version inside the
cooldown selectable — `composer update` would refuse it anyway. When both gates block a version, the
footer names the longer one.

Composer's own rules decide when the cooldown counts:

- It applies only when Composer would block with it: `period` set and not `0`, `block` not `false`,
  and `policy` not disabled.
- `COMPOSER_POLICY_COOLDOWN_PERIOD`, `COMPOSER_POLICY_COOLDOWN_BLOCK`, `COMPOSER_NO_BLOCKING` and
  `COMPOSER_NO_SECURITY_BLOCKING` are honoured, exactly as `composer update` honours them.
- Dev versions and versions matching a `policy.cooldown.ignore` rule (including its version
  constraint) bypass the cooldown; only the minimum release age applies to them.
- An invalid `period` or `COMPOSER_POLICY_COOLDOWN_PERIOD` aborts with exit code `1` and Composer's
  error message.

On Composer older than 2.11 there is no cooldown policy, and the minimum release age works on its own.

## Details worth knowing

- Release dates come from the repository metadata: `published-time` (set by the server, as Composer's
  cooldown uses it), falling back to `time` in Packagist v2 metadata. No extra requests are made.
- A version with **no** release date — path repositories, some private VCS repositories, `dev-*`
  branches — is treated as **eligible**. The gate never hides a package just because its repository
  omits the field; such targets show `release date unknown`.
- Units are fixed lengths: `1w` = 7 days, `1m` = 30 days, `1y` = 365 days. Calendar arithmetic would
  make the cutoff depend on the day the command runs.
- The minimum release age applies to the **direct** dependencies listed in the table. Applying the
  upgrades runs `composer update --with-all-dependencies`, so transitive dependencies still resolve to
  their newest matching versions. To hold those back as well, configure Composer's
  [cooldown policy](#composer-s-cooldown-policy): `composer update` enforces it for every package.
- Packagist reports the tag publication time. Re-tagging a release resets it. Treat this as a
  cooldown, not a supply-chain guarantee.
