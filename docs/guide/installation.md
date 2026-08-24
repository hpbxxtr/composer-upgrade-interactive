# Installation

## Requirements

- PHP **8.3+**
- Composer **2.x**

Composer 1.x is not supported — the plugin targets `composer-plugin-api ^2.0`.

## Install

Globally, so the command is available in every project:

```bash
composer global require hpbxxtr/composer-upgrade-interactive
```

Or as a dev dependency of a single project:

```bash
composer require --dev hpbxxtr/composer-upgrade-interactive
```

## Trust prompt

Composer asks you to allow the plugin to execute code. Answer `y` to enable it:

```
Do you trust "hpbxxtr/composer-upgrade-interactive" to execute code and wish to enable it now?
(writes "allow-plugins" to composer.json) [y,n,d,?] y
```

::: tip
Answering `y` writes an `allow-plugins` entry to `composer.json`. Declining leaves the plugin
installed but inert — the command will not be registered.
:::

## Next

- [Usage](/guide/usage) — what the table shows and how to drive it
- [CLI reference](/reference/cli) — options, aliases, exit codes
