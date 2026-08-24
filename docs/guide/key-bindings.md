# Key bindings

## Normal mode

| Key | Action |
|---|---|
| `↑` / `k` / `Ctrl+P` | Move up |
| `↓` / `j` / `Ctrl+N` | Move down |
| `←` / `h` / `Ctrl+B` | Move left (previous bump column) |
| `→` / `l` / `Ctrl+F` | Move right (next bump column) |
| `space` | Toggle selection (latest version for the focused bump) |
| `v` | Open the version picker for the focused cell |
| `a` | Edit the [minimum release age](/guide/minimum-release-age) |
| `enter` | Confirm and apply upgrades |
| `Ctrl+C` | Cancel |

## Version picker

| Key | Action |
|---|---|
| `↑` / `↓` | Navigate versions within the current column |
| `←` / `→` | Switch between version columns (oldest left, newest right) |
| `space` | Select the highlighted version and close the picker |
| `a` | Edit the minimum release age |
| `esc` / `←` at the first column | Close the picker without changing the selection |

## Age input

| Key | Action |
|---|---|
| `enter` | Apply the typed threshold (empty value turns the gate off) |
| `esc` | Discard and keep the previous threshold |
| `backspace` / `Ctrl+H` | Delete the character before the cursor |
| `Ctrl+U` | Clear the field |
