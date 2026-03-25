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
- **require and require-dev** sections rendered separately
- **Abandoned package warnings** with replacement suggestions
- **Comparison and release URLs** in the footer for whichever bump type is focused (GitHub, GitLab, Packagist)
- **Parallel dependency resolution** — all outdated checks run concurrently
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

Navigate to a package, move across the patch/minor/major columns with the arrow keys, press `space` to select a target version, then `enter` to apply.

---

## ⌨️ Key bindings

| Key | Action |
|---|---|
| `↑` / `k` / `Ctrl+P` | Move up |
| `↓` / `j` / `Ctrl+N` | Move down |
| `←` / `h` / `Ctrl+B` | Move left (previous bump column) |
| `→` / `l` / `Ctrl+F` | Move right (next bump column) |
| `space` | Toggle selection |
| `enter` | Confirm and apply upgrades |
| `Ctrl+C` | Cancel |

---

## ⚙️ How it works

1. Runs `composer outdated` in parallel for patch, minor, and major ranges to build a version map.
2. Renders a table-style multiselect — one row per outdated package, three columns for each bump level.
3. On confirm, writes updated constraints to `composer.json` then runs `composer update --with-all-dependencies` for all selected packages in one pass.

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
