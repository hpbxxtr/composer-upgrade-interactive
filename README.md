![composer upgrade-interactive][hero-img]

[![Packagist Version][packagist-shield-url]][packagist-url]
[![PHP][php-shield-url]][php-url]
[![License][license-shield-url]][license-url]
[![Codecov][codecov-shield-url]][codecov-url]
[![PHPStan][phpstan-shield-url]][phpstan-url]
[![PHP-CS-Fixer][php-cs-fixer-shield-url]][php-cs-fixer-url]
[![Rector][rector-shield-url]][rector-url]
[![brnshkr/config][brnshkr-config-shield-url]][brnshkr-config-url]

<p align="center">
  <a href="#-features">Features</a> | <a href="#-requirements">Requirements</a> | <a href="#-installation">Installation</a> | <a href="#-usage">Usage</a> | <a href="#️-key-bindings">Key bindings</a> | <a href="#️-how-it-works">How it works</a>
</p>

---

<h3 align="center">An interactive TUI for selectively upgrading Composer dependencies</h3>
<h4 align="center"><code>yarn upgrade-interactive</code> for PHP!</h4>

![demo][demo-img]

---

## ✨ Features


- **Patch / minor / major** columns per package — choose exactly how far to upgrade each one
- **Full version picker** — press `v` on any cell to browse all available stable versions in an inline multi-column list and pin an exact release
- **Live compatibility checking** — conflicting versions are marked with `!` as you navigate; the footer explains which dependency chain is broken and whether it originates from the current upgrade selection or from an already-installed package
- **require and require-dev** sections rendered separately
- **Abandoned package warnings** with replacement suggestions
- **Comparison and release URLs** in the footer for whichever bump type or version is focused (GitHub, GitLab, Packagist)
- **Full keyboard navigation** — arrow keys, vim (`hjkl`), and Emacs (`Ctrl+P/N/B/F`) bindings

---

## 📋 Requirements

- PHP **8.3+**
- Composer **2.x**

---

## 📦 Installation

```bash
composer global require hpbxxtr/composer-upgrade-interactive
```

Or as a project dev dependency:

```bash
composer require --dev hpbxxtr/composer-upgrade-interactive
```

> **Trust prompt** — Composer will ask you to allow the plugin to execute code. Answer `y` to enable it:
> ```
> Do you trust "hpbxxtr/composer-upgrade-interactive" to execute code and wish to enable it now?
> (writes "allow-plugins" to composer.json) [y,n,d,?] y
> ```

---

## 🚀 Usage

```bash
composer h:ui
```

Aliases: `composer hpbxxtr:upgrade-interactive` · `composer upgrade-interactive`

Navigate to a package, move across the patch/minor/major columns with the arrow keys, press `space` to select the latest version for that bump level, or press `v` to open an inline picker and choose any specific stable release. Press `enter` to confirm and apply.

### Options

| Option | Description |
|---|---|
| `--caret` | Write a caret range constraint (e.g. `^1.2.3`) instead of an exact version (`1.2.3`) |
| `--min-age=<duration>` | Minimum release age before a version may be selected. Accepts `7d`, `2w`, `3m`, `1y` or a bare number of days (`14`). `0` disables the gate. |

**Example — pin exact versions (default):**
```bash
composer h:ui
# writes: "vendor/pkg": "1.2.3"
```

**Example — allow future minor/patch updates:**
```bash
composer h:ui --caret
# writes: "vendor/pkg": "^1.2.3"
```

**Example — skip releases younger than two weeks:**
```bash
composer h:ui --min-age=2w
```

---

## ⏳ Minimum release age

A release published minutes ago is the riskiest one to install. `--min-age` defines a cooldown:
versions younger than the threshold stay visible but cannot be selected. They are marked with `⊘`,
and the footer explains why (`released 3 days ago · blocked by min age 7d`).

Set a project-wide default in `composer.json` — the CLI option always wins over it:

```json
{
    "extra": {
        "hpbxxtr-upgrade-interactive": {
            "minimum-release-age": "7d"
        }
    }
}
```

Press `a` inside the TUI to edit the threshold. The field opens prefilled with the current value;
type any duration the option accepts, then `enter` to apply, `esc` to discard, `ctrl+u` to clear.
An empty value turns the gate off. An unparsable value keeps the field open and shows why.

```
  min age  ▸ 2w▏
           type a duration (7d · 2w · 3m · 1y) · enter apply · esc cancel · ctrl+u clear · empty = off
```

Tightening the threshold drops selections it no longer allows; loosening it makes previously blocked
versions selectable again. Nothing is re-fetched — every displayed version already carries its
release date.

Details worth knowing:

- Release dates come from the repository metadata (`time` in Packagist v2 metadata). No extra
  requests are made.
- A version with **no** release date — path repositories, some private VCS repositories,
  `dev-*` branches — is treated as **eligible**. The gate never hides a package just because its
  repository omits the field; such targets show `release date unknown`.
- Units are fixed lengths: `1w` = 7 days, `1m` = 30 days, `1y` = 365 days.
- The threshold applies to the **direct** dependencies listed in the table. Applying the upgrades
  runs `composer update --with-all-dependencies`, so transitive dependencies still resolve to their
  newest matching versions — Composer offers no knob to constrain them.
- Packagist reports the tag publication time. Re-tagging a release resets it. Treat this as a
  cooldown, not a supply-chain guarantee.

---

## ⌨️ Key bindings

**Normal mode**

| Key | Action |
|---|---|
| `↑` / `k` / `Ctrl+P` | Move up |
| `↓` / `j` / `Ctrl+N` | Move down |
| `←` / `h` / `Ctrl+B` | Move left (previous bump column) |
| `→` / `l` / `Ctrl+F` | Move right (next bump column) |
| `space` | Toggle selection (latest version for focused bump) |
| `v` | Open version picker for the focused cell |
| `a` | Edit the minimum release age (type `7d`, `2w`, `3m`, `1y`, `14`; empty applies *off*) |
| `enter` | Confirm and apply upgrades |
| `Ctrl+C` | Cancel |

**Version picker**

| Key | Action |
|---|---|
| `↑` / `↓` | Navigate versions within the current column |
| `←` / `→` | Switch between version columns (oldest left, newest right) |
| `space` | Select the highlighted version and close picker |
| `a` | Edit the minimum release age |
| `esc` / `←` at first column | Close picker without changing selection |

---

## ⚙️ How it works

1. **Resolution** — iterates the local Composer repository via internal Composer APIs and finds the best available patch, minor, and major target for every direct dependency.
2. **Initial compatibility check** — before the TUI opens, every candidate version is checked against the currently installed package set. Conflicting versions are pre-marked with `!` when the table first renders.
3. **Table** — one row per outdated package, three columns for patch / minor / major. Cells marked `!` indicate conflicts with the current state; the footer explains the broken dependency chain.
4. **Version picker** — pressing `v` on any cell fetches all stable releases for that bump level from Packagist and renders them as a side-by-side column picker, grouped by series (e.g. `1.4.x`, `1.3.x`). Each version is checked for compatibility before the picker opens.
5. **Live conflict recheck** — every time a selection changes, all visible versions are re-evaluated against the updated effective world (see below).
6. **Age gate** — when a minimum release age is configured, versions younger than the threshold are marked `⊘` and refuse selection (see [Minimum release age](#-minimum-release-age)).
7. **Apply** — writes updated constraints to `composer.json` (exact version by default, caret range with `--caret`) then runs `composer update --with-all-dependencies` for all selected packages in one pass.

### Conflict detection

Compatibility is checked against an **effective world** that combines three layers:

| Layer | Description |
|---|---|
| Installed base | All currently installed direct dependencies and their versions |
| Selection overrides | Any packages you have already selected for upgrade in this session — their selected version replaces the installed one |
| Candidate override | The specific version being evaluated replaces the installed version of that package in the world |

A conflict is reported when:

- **Forward** — the candidate version declares a `require` that is not satisfied by another package's version in the effective world (e.g. `vendor/pkg 2.0` requires `vendor/dep ^3.0` but `vendor/dep 2.8` is installed)
- **Backward** — another package in the effective world declares a `require` on the candidate package that the candidate's version does not satisfy (e.g. `vendor/other 1.5` requires `vendor/pkg ^1.0` but you are upgrading to `vendor/pkg 2.0`)

The conflict footer shows up to five conflicts at a time. Each line identifies the dependent package, the constraint it declares, and whether the conflicting dependency comes from a **selected upgrade** (`selected: X`) — meaning you can potentially resolve it by also upgrading that package — or from an **installed package** (`installed: X` / `update available`) that is not part of the current upgrade set.

---

## 📄 License

MIT — see [LICENSE][license-url].

<!-- LINKS -->

[hero-img]: ./.readme/composer_hui.png
[demo-img]: ./.readme/demo.gif

[packagist-url]: https://packagist.org/packages/hpbxxtr/composer-upgrade-interactive
[packagist-shield-url]: https://img.shields.io/packagist/v/hpbxxtr/composer-upgrade-interactive?style=flat-square&logo=packagist&logoColor=ff00ff&labelColor=0d0d1a&color=ff00ff

[php-url]: https://www.php.net
[php-shield-url]: https://img.shields.io/badge/php-%5E8.3-40e5ff?style=flat-square&logo=php&logoColor=40e5ff&labelColor=0d0d1a

[license-url]: LICENSE
[license-shield-url]: https://img.shields.io/github/license/hpbxxtr/composer-upgrade-interactive?style=flat-square&logo=github&logoColor=bf00ff&labelColor=0d0d1a&color=bf00ff

[codecov-url]: https://codecov.io/gh/hpbxxtr/composer-upgrade-interactive
[codecov-shield-url]: https://img.shields.io/codecov/c/github/hpbxxtr/composer-upgrade-interactive?style=flat-square&logo=codecov&logoColor=20ffa7&labelColor=0d0d1a&color=20ffa7

[phpstan-url]: https://phpstan.org
[phpstan-shield-url]: https://img.shields.io/badge/PHPStan-level%2010-ff0090?style=flat-square&labelColor=0d0d1a

[php-cs-fixer-url]: https://cs.symfony.com
[php-cs-fixer-shield-url]: https://img.shields.io/badge/PHP--CS--Fixer-enabled-ff6b35?style=flat-square&labelColor=0d0d1a

[rector-url]: https://getrector.com
[rector-shield-url]: https://img.shields.io/badge/Rector-enabled-ccff00?style=flat-square&labelColor=0d0d1a

[brnshkr-config-url]: https://github.com/brnshkr/config
[brnshkr-config-shield-url]: https://img.shields.io/badge/🧠%20brnshkr-config-ccff00?style=flat-square&logoColor=ff00ff&labelColor=0d0d1a&color=ff00ff
