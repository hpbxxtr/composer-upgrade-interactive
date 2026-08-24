# Usage

```bash
composer h:ui
```

Aliases: `composer hpbxxtr:upgrade-interactive` · `composer upgrade-interactive`

The command reads the direct dependencies of the current project, resolves the best available patch,
minor and major target for each one, and opens a table.

## Driving the table

1. Move to a package with `↑` / `↓`.
2. Move across the **patch / minor / major** columns with `←` / `→`.
3. Press `space` to select the latest version for the focused bump level.
4. Or press `v` to open the version picker and choose any specific stable release.
5. Press `enter` to confirm and apply.

Nothing is written until you press `enter`. `Ctrl+C` aborts without touching `composer.json`.

See [Key bindings](/guide/key-bindings) for the full list, including the vim and Emacs aliases.

## Reading the table

| Marker | Meaning |
|---|---|
| `!` | The version conflicts with the current effective dependency set — the footer explains which chain is broken. See [Conflict detection](/guide/how-it-works#conflict-detection). |
| `⊘` | The version is younger than the configured minimum release age and cannot be selected. See [Minimum release age](/guide/minimum-release-age). |

`require` and `require-dev` are rendered as separate sections. Abandoned packages are flagged with
the replacement Packagist suggests, if any.

The footer also carries the comparison or release URL for whatever is focused, resolved for GitHub,
GitLab or Packagist.

## Applying

On `enter` the plugin writes the new constraints to `composer.json` and then runs
`composer update --with-all-dependencies` once for every selected package.

By default an **exact** version is written:

```bash
composer h:ui
# writes: "vendor/pkg": "1.2.3"
```

Pass `--caret` to write a caret range instead, leaving room for future minor and patch updates:

```bash
composer h:ui --caret
# writes: "vendor/pkg": "^1.2.3"
```

## Non-interactive environments

The command needs a TTY. In CI or when piped it exits with code `1` and
`upgrade-interactive requires an interactive terminal.` — see [Exit codes](/reference/cli#exit-codes).
