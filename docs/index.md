---
layout: home

hero:
  name: composer upgrade-interactive
  text: Pick your upgrades, one package at a time
  tagline: An interactive TUI for selectively upgrading Composer dependencies — yarn upgrade-interactive for PHP.
  actions:
    - theme: brand
      text: Get started
      link: /guide/installation
    - theme: alt
      text: CLI reference
      link: /reference/cli
    - theme: alt
      text: GitHub
      link: https://github.com/hpbxxtr/composer-upgrade-interactive

features:
  - title: Patch / minor / major columns
    details: One row per outdated package, three bump columns. Choose exactly how far to move each dependency instead of taking whatever composer update resolves.
  - title: Full version picker
    details: Press v on any cell to browse every available stable version in an inline multi-column list and pin an exact release.
  - title: Live compatibility checking
    details: Conflicting versions are marked with ! as you navigate. The footer names the broken dependency chain and whether it comes from your selection or from an already-installed package.
  - title: Minimum release age
    details: Gate out releases published minutes ago. Set a project-wide default in composer.json, override it per run, or retune it inside the TUI.
  - title: require and require-dev
    details: Both sections are rendered separately, so a dev-only bump never hides among production dependencies.
  - title: Abandoned package warnings
    details: Abandoned packages are flagged in place, with the replacement Packagist suggests.
  - title: Comparison and release URLs
    details: The footer carries a compare or release link for whichever bump type or version is focused — GitHub, GitLab and Packagist.
  - title: Full keyboard navigation
    details: Arrow keys, vim (hjkl) and Emacs (Ctrl+P/N/B/F) bindings all work.
---

![composer upgrade-interactive in action](/demo.gif)
