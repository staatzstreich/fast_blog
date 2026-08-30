# Fast Blog – Markdown-first blog for TYPO3

A TYPO3 v14 blog extension where posts are maintained as Markdown files with YAML frontmatter in your fileadmin area and imported into TYPO3 by a single CLI command. The website gets a paginated blog list with a tag filter and a detail view with a per-post language switcher.

Detailed documentation: [`Documentation/Index.rst`](Documentation/Index.rst) (rendered docs in `RenderedDocs/`).

## Features

- **Markdown files as source of truth** – frontmatter carries title, date, description, author, tags, language and a translation key; `draft: true` hides a post (on import only).
- **Import / re-import** – `vendor/bin/typo3 fastblog:import <source> <pid>` picks up new and changed files; translated files (same `translationKey`) are linked as TYPO3 translations (`l10n_parent`).
- **Security** – Markdown is rendered with league/commonmark at import time; raw HTML and unsafe links are stripped.
- **Editorial state preserved** – re-importing a file never touches the backend `hidden` flag.
- **Multilingual by configuration** – language handling reads entirely from the site configuration; no extension settings needed.
- **Theme-agnostic** – no page chrome; the stylesheet maps its tokens onto the active theme's CSS variables (daisyUI patterns included) with standalone fallbacks.
- **SEO** – canonical URL listener keeps paginated/filtered list URLs canonical; meta description and focus keywords from the frontmatter.

## Requirements

- TYPO3 14.3+ (`typo3/cms-seo` for the canonical handling)
- PHP 8.3+

## Installation

1. Install the extension (`composer req michaelstaatz/fast-blog` in your distribution).
2. Create the blog storage folder (or reuse an existing one) and add the "Blog list" content element to a page – no TypoScript include required.
3. Put your posts next to your other content, e.g. `fileadmin/blog/`:

```markdown
---
title: All About Apples
pubDate: 2026-08-29
author: Jane Orchard
description: An overview of apple varieties.
meta_description: Learn about apple varieties from the orchard.
tags: [varieties]
lang: en
translationKey: apples
---

# All About Apples

The orchard speaks...
```

4. Configure the source directory and the storage sysfolder pid in the extension settings (Admin Tools → Settings → Extension Configuration: `outputDirectory` and `blogStoragePid`; optionally `postsPerPage` for the list length), then import:

```sh
vendor/bin/typo3 fastblog:import
```

The import is idempotent – re-running picks up changes to existing files and skips unchanged ones.

5. Optional – register an Extbase route enhancer for pretty URLs (`/apple-blog/all-about-apples`, `/page/2`, `/tag/harvest`), see the documentation.

## Sitepackage integration

The extension is a building block for a sitepackage/theme: its frontend labels are generic English and can be re-worded per project via the `LANG.resourceOverrides` mechanism in a sitepackage's `ext_localconf.php`; the blog element renders inside any backend layout. See the "Sitepackage integration" chapter of the documentation.

## License

GPL-2.0-or-later. See `LICENSE.txt`.