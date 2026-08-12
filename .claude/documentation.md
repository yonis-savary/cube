# Documentation guidelines

How pages in `docs/` are written. The audience is a developer building an application **with**
Cube, not someone maintaining the framework — internal design belongs in
[architecture.md](./architecture.md), not in `docs/`.

## Files and numbering

- One page per subsystem, named `<number>-<kebab-case-title>.md`.
  `1xx` = core concepts every app needs (applications, commands, logging, routing, controllers,
  storage, events), `2xx` = optional features (static server, authentication, http client, queues,
  websockets), `9xx` = niche or advanced topics.
- The number is the reading order, so insert a new page where it belongs in the learning path
  rather than appending it at the end.
- The `#` title must match the filename : `generate-menus.php` derives menu labels from the
  filename (number stripped, dashes turned into spaces, first letter uppercased), so
  `106-routing-and-middlewares.md` shows up as *Routing and middlewares*.
- After adding, renaming or removing a page, regenerate the menus **from inside `docs/`** :
  ```sh
  cd docs && php generate-menus.php
  ```
  It rewrites the previous/next menu table at the top and bottom of every page, and prints the page
  list to paste into `docs/README.md`. Never hand-write a menu table, and always keep
  `docs/README.md` in sync — it is the documentation's only table of contents.
- Keep one blank line between the menu table and the `#` title (the generator preserves it).

## Page shape

1. **One or two sentences** saying what the subsystem does and which component owns it. No
   marketing, no history.
2. **The 80% case, as code, immediately.** A reader should be able to copy the first block and have
   something working.
3. Then the details, one `##` per question the reader will actually ask, ordered from most to
   least common.
4. End with the reference material : configuration options, produced responses, available commands.

Keep pages skimmable : short sections, no wall of prose between two code blocks.

## Writing style

- Address the reader as *you*, and call the framework *Cube* or name the component.
- Present tense, no future ("Cube converts it", not "will convert it").
- Introduce a code block with a short lead-in line, the way the existing pages do ("Here is an
  example").
- Prefer showing over explaining : a commented code block beats a paragraph. Put the explanation
  **inside** the block as a `//` comment when it concerns one specific line.
- Tables for anything enumerable : route factories, slug types, configuration options, injected
  parameter types, produced status codes. The thing on the left, what it does on the right.
- A small ASCII diagram is welcome when the flow is not obvious (see the websocket page).

## Code examples

- Examples must be **runnable and honest**. Check the signature in `src/` before writing a call.
- Use realistic application classes (`ProductController`, `StoreProductRequest`, `Agency`) in the
  `App\` style of `tests/integration-root/App`, never `Foo`/`Bar`.
- Show the recommended form. When two forms exist, document both and say which to prefer and why
  (e.g. `[Controller::class, 'method']` over a closure, because the framework can reflect on it).
- Omit imports in snippets — the pages assume the reader's IDE handles them.
- Never document behaviour you have not verified in the source. If an option exists but does
  nothing yet, say so in one clause ("reserved, currently a no-op") instead of describing the
  intent as if it worked.
- `(WIP)` is acceptable for a page nobody has written yet, but a page you touch should lose its
  `(WIP)` marker, or keep it only on the specific unfinished section.

## Keeping docs alive

- A framework feature is not finished until the matching page or section exists : the change that
  adds a capability updates `docs/`, `docs/README.md` and, when it belongs in the feature list, the
  root `README.md`.
- When you change a signature or a default, grep `docs/` for the old name before considering the
  change done.
- An outdated page is worse than a missing one — fix it or delete it, do not leave it drifting.
