.. include:: /Includes.rst.txt

.. ==================================================
.. HEADER
.. ==================================================

.. _fastblog-start:

=============
Fast Blog
=============

Add-on for TYPO3 CMS (v14+): Blog posts are maintained as Markdown files with
YAML frontmatter in :file:`fileadmin` and imported into TYPO3 by a CLI
command. On the website they are displayed with a blog list (pagination and
tag filter) plus a detail view.

The extension deliberately provides no navigation, footer or color theme
of its own: that is the job of the active theme set (for example a
daisyUI-based theme set that provides the navigation and the color scheme). The blog's stylesheet
maps its color tokens onto the theme's CSS variables - including
``data-theme``-driven schemes like daisyUI - with standalone fallbacks,
so it renders reasonably even without any theme. Post-specific language
links are kept inside the blog (see below) because they depend on the
per-post translations, not on the page layout.

.. ==================================================
.. TABLE OF CONTENTS
.. ==================================================

.. contents::
   :depth: 2

.. ==================================================
.. MAIN CONTENT
.. ==================================================

.. _features:

Features
========

- **Markdown as the single source of truth** – posts live as :file:`.md`
  files with YAML frontmatter in a `fileadmin` folder of your choice.
- **Import command** – :code:`vendor/bin/typo3 fastblog:import` creates or
  updates database records. Re-running it is safe: files are matched by
  their path (:code:`source_file`, stored relative to the docroot), so
  existing records are updated instead of duplicated; records whose
  Markdown file has been deleted are simply no longer updated.
- **Blog content element** – a ready-to-use plugin (CType
  :code:`fastblog_bloglist`) providing a paginated list with tag filter and
  a detail view. Includes the SEO canonical handling for filtered/paginated
  list views.
- **Tags** – :code:`tags` entries in the frontmatter are stored as
  sys\_categories and linked to the blog post.
- **Optional TypoScript** – the plugin renders via Extbase and Fluid, so no
  custom TypoScript is required beyond dropping the content element onto a
  page.

.. _installation:

Installation
============

1. Require the extension via Composer:

   .. code-block:: bash

      composer require michaelstaatz/fast-blog

2. Set up dependencies and create the database table:

   .. code-block:: bash

      vendor/bin/typo3 extension:setup

3. In **Settings > Extension configuration > Fast Blog** configure the
   options below.

.. _configuration:

Configuration
=============

``outputDirectory``
    Default: :file:`fileadmin/blog_posts`

    Directory of the Markdown files, relative to the web root. Only
    :file:`*.md` files directly inside this directory are imported.

``blogStoragePid``
    Default: ``0``

    Page UID of the sysfolder the blog post records are written to.
    The import refuses to run until a valid UID (greater than 0) is set.

``postsPerPage``
    Default: ``5``

    Number of posts shown per page in the blog list. Values below 1
    fall back to 5, so the pagination stays valid.

.. _markdown-format:

The markdown file format
========================

A blog post is a plain Markdown file, the first block being YAML
frontmatter followed by the article body:

.. code-block:: yaml

   ---
   title: 'Hello Fast Blog'
   pubDate: '2026-08-29'
   description: 'One-liner teaser, shown in list view.'
   meta_description: 'Longer description, used for the meta tag.'
   focus_keywords:
       - fast blog
       - typo3
   author: 'Jane Doe'
   tags:
       - TYPO3
       - Blogging
   lang: de
   translationKey: 'hello-fast-blog'
   draft: true
   ---

   ## Hello Fast Blog

   Write your article body in regular Markdown here.

.. list-table::
   :header-rows: 1
   :widths: 25 20 20 35

   * - Field
     - Type
     - Default
     - Description
   * - title
     - string
     - file name
     - Post title. `required`
   * - pubDate
     - `YYYY-MM-DD`
     - now
     - Publication date, displayed in list and detail view.
   * - description
     - string
     - ''
     - Teaser text (list view and detail view header).
   * - meta\_description
     - string
     - ''
     - Description for the HTML meta tag.
   * - focus\_keywords
     - array
     - []
     - Keyword list for SEO (stored as a comma-separated string in the record).
   * - author
     - string
     - ''
     - Author name, shown under the publication date.
   * - tags
     - array
     - []
     - Free-form tags. Created as sys\_category records on first use and
       rendered as clickable tag filters.
   * - lang
     - string
     - ''
     - Language of the file as a two-letter ISO code (`de`, `en`, `fr`,
       ...). The code is matched against the languages configured in your
       site configuration (:file:`config/sites/<site>/config.yaml`), so
       the import adapts automatically to whatever languages the site
       defines - no extension configuration needed. Unknown or missing
       values fall back to the default language (uid 0).
   * - translationKey
     - string
     - ''
     - Groups translated variants together. Files with the same key but
       different :code:`lang` values are linked as
       :code:`l10n_parent` translations of each other.
   * - draft
     - boolean
     - false
     - If set, the record is imported hidden (`hidden = 1`) and must be
       published manually in the backend.

Frontmatter field values are stored 1:1 in the
:code:`tx_fastblog_domain_model_blogpost` table (all fields read-only in
the backend except :code:`title`, :code:`author`, :code:`categories` and
the description fields).

.. _h_command:

Import command
==============

.. code-block:: bash

   vendor/bin/typo3 fastblog:import

- Reads every :file:`*.md` file directly inside
  `outputDirectory` (**not** recursively), converts the body Markdown to
  HTML via `league/commonmark` and writes/updates the
  :code:`tx_fastblog_domain_model_blogpost` record.
- Matching of file to record happens via :code:`source_file`, stored
  relative to the docroot (:code:`Environment::getPublicPath()`), so
  repeated runs update in place - including after copying the database to
  a different system whose docroot path differs.
- Slugs are derived from the title (ASCII-ized, max. 60 chars). If a slug
  is already taken by a different file, a numeric suffix is appended.
  Non-Latin titles are transliterated where possible - a Japanese title
  like :code:`こんにちはファストブログ` becomes the Romaji slug
  :code:`kon-nichihafasutoburogu` - so pretty URLs stay ASCII-safe.
- Tags are created as sys\_category records on root level (pid 0) and
  related via `sys_category_record_mm`.
- In TYPO3/DDEV setups this command is typically called from a system
  cronjob; the backend scheduler module is not required.
- Installations upgrading from an earlier version may still have absolute
  paths stored in :code:`source_file`. Run the **fast_blog: Migrate
  "source_file" to relative paths** upgrade wizard once (backend module
  *System > Upgrade*) to convert them - otherwise the next import may
  create duplicates instead of updating those records.

.. _h_contentelement:

Blog list content element
=========================

The extension registers the content element :code:`fastblog_bloglist`
(group `Blog`). Drop it on a page to get:

- **List view** – newest first, paginated (posts per page is configurable
  through the `postsPerPage` extension setting, default 5) with a simple
  pagination, a tag sidebar listing all categories in use, filterable by
  tag.
- **Detail view** – the post, referenced by its `slug`
  (:file:`/\<page-path\>/\<post-slug\>/` with the route enhancer below),
  including SEO canonical URL handling for filtered and paginated views,
  and a per-post language switcher (shown for every language configured in
  the site).

Both views are controlled by Fluid templates
(:file:`Resources/Private/Templates/Blog/` and
:file:`Resources/Private/Layouts/BlogLayout.html`), so they can be
overridden per sitepackage.

.. _h_routeenhancer:

Route enhancer (optional)
=========================

To get human-readable URLs, register an
`Extbase route enhancer <https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/SiteHandling/RouteEnhancers.html>`__
in :file:`config/sites/<site>/config.yaml`:

.. code-block:: yaml

   routeEnhancers:
     FastBlogBloglist:
       type: Extbase
       extension: FastBlog
       plugin: Bloglist
       routes:
         - routePath: '/page/{page}'
           _controller: 'Blog::list'
           _arguments:
             page: page
         - routePath: '/tag/{tagslug}'
           _controller: 'Blog::list'
           _arguments:
             tagslug: category
         - routePath: '/{post-title}'
           _controller: 'Blog::show'
           _arguments:
             post-title: post
         - routePath: ''
           _controller: 'Blog::list'
       defaultController: 'Blog::list'
       requirements:
         page: '\d+'
       aspects:
         page:
           type: StaticRangeMapper
           start: '1'
           end: '200'
         tagslug:
           type: PersistedAliasMapper
           tableName: sys_category
           routeFieldName: title
         post-title:
           type: PersistedAliasMapper
           tableName: tx_fastblog_domain_model_blogpost
           routeFieldName: slug

Multilingual without configuration
==================================

Fast Blog needs no extension setting for multilingual sites - it reads
everything from the existing TYPO3 site configuration:

- **Import**: the frontmatter `lang` code is matched against the
  languages you already defined in :file:`config/sites/<site>/config.yaml`
  (ISO code, then hreflang). The import writes the posts with the
  matching :code:`sys_language_uid`, completely independent of any
  installed sitepackage.
- **Linking**: files sharing the same `translationKey` are linked as
  TYPO3 translations (:code:`l10n_parent`), no matter which file gets
  imported first.
- **Display**: the detail view checks which counterpart translations of
  the current post exist and renders a switcher pointing to the translated
  post - URLs are generated through the site's own routing (including
  your route enhancer), so each language's base URL and slug handling is
  respected automatically. The list view currently leaves language
  switching to the site's general navigation (e.g. the theme's navbar):
  a generic menu
  cannot link to a translated post (the slug differs per language), so a
  post-specific switcher would be needed there - this may be added as a
  daisyUI dropdown at theme level.

If a site has only one language, everything above is a no-op: all posts
land in the default language and no switcher is shown. Display then
follows TYPO3's usual rules: a language is shown only if the site has a
translated page and a translated content element.

.. ==================================================
.. SITEPACKAGE INTEGRATION
.. ==================================================

.. _h_sitepackage:

Site package integration
========================

Fast Blog provides no navigation, footer, theme or page templates of its
own - it is a building block for a site package / theme package:

- **Include the plugin content element**: the "Blog list" element (CType
  :code:`fastblog_bloglist`) is added to the page where your theme's
  backend layout renders the main column. No TypoScript include is
  needed - :code:`configurePlugin()` in :file:`ext_localconf.php` registers
  the rendering setup automatically.
- **Feed labels per language**: the extension is translated generically
  (English sources). A sitepackage can override the wording per project
  by registering its own XLF files through the ``LANG.resourceOverrides``
  mechanism in :file:`ext_localconf.php` - one file per language, with the
  language key (not the locale variant) so ``de-DE``, ``de-AT``, ... all
  resolve through it:

  .. code-block:: php

     $resourceOverrides = (array) ($GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides'] ?? []);
     foreach (['en', 'de', 'ja'] as $language) {
         $resourceOverrides[$language]['EXT:fast_blog/Resources/Private/Language/locallang.xlf'] = [
             ...(array) ($resourceOverrides[$language]['EXT:fast_blog/Resources/Private/Language/locallang.xlf'] ?? []),
             'EXT:sitepackage/Resources/Private/Language/' . $language . '.locallang_fast_blog.xlf',
         ];
     }
     $GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides'] = $resourceOverrides;

- **Styling**: :file:`Resources/Public/Css/Blog.css` styles the blog itself
  and maps its color tokens onto the theme's CSS variables
  (:code:`--color-base-*`, :code:`--color-primary`, ...) with standalone
  fallbacks, so any theme set (e.g. a daisyUI-based one) can wrap the
  blog in its own navigation and styling.

.. ==================================================
.. LIMITATIONS
.. ==================================================

.. _h_limitations:

Limitations
===========

- The database is the import target only: deleting a Markdown file does
  not delete its record (the record is simply no longer updated). Remove
  records in the backend if they should disappear from the site.
- Multilingual mode mirrors TYPO3's own rules: translations display only
  if the site is set up for them (language configured in the site config,
  translated page and translated content element). The import links
  translated posts via :code:`l10n_parent` regardless.
- The import itself is frontend-agnostic: a file may reference a language
  that no site configuration defines. Such records are imported but stay
  invisible until a matching language configuration exists, and the
  language switcher in the detail view skips them.
- Only Markdown files directly inside the output directory are
  considered, no recursion into subfolders.
