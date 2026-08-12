---
name: doc-page
description: Write, update or reorganize a page of the Cube documentation in docs/, then regenerate the previous/next menus and sync docs/README.md. Use whenever a docs/ page is created, renamed, deleted or edited, or when the doc menus / table of contents need to be rebuilt.
---

# Writing a Cube documentation page

Follow `.claude/documentation.md` for numbering, page shape, style and example rules. This skill is
the procedure around it.

## 1. Gather the truth from the source

Never write a page from memory. Before writing, read the code the page documents :

- the component itself (`src/<Namespace>/...`) and every public method you intend to show
- the matching `ConfigurationElement` subclass, for the configuration section
- `tests/units` and `tests/integration-root/App` for real usage of the API

Check each signature you put in an example. If a documented option turns out to have no effect,
say so in one clause instead of describing the intent, and report it to the user.

## 2. Write the page

- File name : `docs/<number>-<kebab-case-title>.md`, `1xx` core / `2xx` features / `9xx` advanced.
- The `#` title must match the filename, since the menu labels are derived from it.
- Write the content **without** the menu tables — step 3 generates them. When editing an existing
  page, leave the existing tables in place and let the generator rewrite them.

## 3. Regenerate the menus

```sh
cd docs && php generate-menus.php
```

This rewrites the menu table at the top and bottom of every page, and prints the full page list on
stdout.

## 4. Sync the table of contents

Paste the generator's stdout into `docs/README.md` — it is the whole file, one line per page. It is
the only place the reading order is listed, and it drifts as soon as a page is added or renamed.

## 5. Report

Tell the user which page was written, which sections it covers, and anything you found in the
source that contradicts the documentation (dead options, wrong types, misleading names). Those are
findings, not things to fix silently inside a documentation change.
