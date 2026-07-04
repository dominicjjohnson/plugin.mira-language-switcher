# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A WordPress plugin (single-file architecture) providing WPML-style URL-based
language switching for `miracx`. Default language has no URL prefix
(`/about-us/`); other enabled languages get a prefix (`/it/about-us/`).
Supported languages are English, Italian, Spanish (`MIRA_LS_SUPPORTED_LANGUAGES`
in `mira-language-switcher.php`), though the Term Translations UI also lists
French/German/Portuguese/Russian/Japanese/Chinese/Arabic as selectable options.

There is no build step, package manager, or automated test suite. This is
classic procedural/OOP WordPress plugin code, edited and deployed directly.

## Running / testing

- No `composer.json` / `package.json` / build tooling — just edit PHP and
  reload the site (`miracx` install this plugin lives inside).
- After changing rewrite rules (`add_rewrite_rules`) or CPT registration,
  flush rewrite rules: deactivate/reactivate the plugin, or visit
  Settings > Permalinks > Save Changes in wp-admin.
- `test-menu-flags.php` is a manual diagnostic script (not a unit test) for
  the menu-flags feature. Run via `php test-menu-flags.php` from the plugin
  directory (loads `wp-load.php` via relative path) or hit it directly in a
  browser. It prints current settings and expected shortcode/flag output —
  read the output yourself, there are no assertions.
- Bump `MIRA_LS_VERSION` and the `Version:` header in
  `mira-language-switcher.php`, and add a changelog line in the file's
  top-of-file docblock, for every release-worthy change (see commit history
  for the established one-line-per-version style).

## Architecture

Everything routes through the single `Mira_Language_Switcher` class in
`mira-language-switcher.php` (~2500 lines). All hooks are registered in the
constructor — that's the fastest way to find what a given request path does.

### Language detection & storage
- **Current language** is detected once per request in `detect_language()`
  (regex on `REQUEST_URI` against enabled languages) and cached on
  `$this->current_language`. It also sets/reads a `mira_language` cookie so
  language persists across bare (unprefixed) URLs and menu rendering.
- `get_query_var('lang')` (set via the rewrite rules) is the most reliable
  signal *during* a themed request; the cookie is the fallback used by code
  that runs outside the main query (e.g. `get_role_page()`).
- Per-post language is stored in post meta `_mira_page_language`.
- Post-to-post translation relationships are stored in a single option,
  `MIRA_LS_TRANSLATIONS_OPTION` (`mira_ls_translation_links`), shaped as
  `[default_page_id => [lang_code => translated_page_id, ...], ...]`. Lookups
  in both directions (default→translation and translation→default) scan this
  array — see `get_language_url()`.
- `get_translatable_post_types()` (default `['page']`) is the single source of
  truth for which post types get this "separate post per language" treatment
  (Language metabox, translation links, translated-slug URLs) rather than
  being treated as a single never-translated CPT. Other plugins opt in via
  the `mira_ls_translatable_post_types` filter — e.g. `mira-event` adds
  `seminars` this way (see `seminars/cpt/seminars.php`). Everything hardcoded
  to `'page'` before v1.2.27 (metabox registration, the Translation Links
  admin page, the page-by-slug lookup in `load_translated_content()`, the
  CPT-vs-page branch in `get_language_url()`) now reads this list instead.
- Term (taxonomy) translations are stored per-term in term meta
  `category_name_{lang}` (kept intentionally aligned with the sibling
  `cw-plugin-exhibitors` plugin's meta key naming — see v1.2.26 changelog
  entry) and applied on the frontend via `get_term`/`get_terms` filters.
- Settings live in plain options: `mira_ls_default_language`,
  `mira_ls_enabled_languages`, `mira_ls_auto_redirect`, `mira_ls_add_to_menu`,
  `mira_ls_menu_location`, `mira_ls_menu_flag_type`,
  `mira_ls_show_lang_in_title`, `mira_ls_cookie_prompt`,
  `mira_ls_header_pages` / `mira_ls_footer_pages` (per-language role pages).

### URL / routing
- `add_rewrite_rules()` registers per-language rules for: language homepage,
  CPT single routes (`/{lang}/{cpt-slug}/{post-slug}/` — added before the
  generic rule so they win), single-level pages, and nested page paths. It
  runs on `init` at priority 20 so custom post types (registered at priority
  10) already exist when their rewrite slugs are read.
- `prevent_language_redirect()` stops WP's canonical redirect from stripping
  the language prefix.
- `redirect_to_language_prefix()` and `auto_redirect_to_translation()` handle
  legacy/bare URL redirects based on the saved cookie.
- `handle_set_lang_param()` supports `?mira_set_lang=xx` to set the cookie
  and redirect to a clean URL without it (used by flag links when no
  translation exists, to avoid bouncing to the homepage).
- `get_language_url($target_lang)` is the core "what URL should this flag
  link to" resolver. It special-cases: front page, translated front page,
  CPTs not in `get_translatable_post_types()` (never translated — same slug,
  just re-prefixed with the post type's rewrite slug), and
  default-vs-non-default language slug resolution via the translation-links
  option for `page`/`post`/opted-in CPTs (prefixed with that post type's
  rewrite slug when it isn't `page`/`post` — see `$slug_prefix`). Read this
  function fully before changing link-generation logic — it has several
  intentional fallback tiers, not accidental duplication.
- `prefix_cpt_permalink()` prefixes CPT permalinks with the current language
  via `post_type_link`.

### Content/UI integration points
- `get_header_page()` / `get_footer_page()` (static) expose
  `miramedia_header_page` / `miramedia_footer_page` filters so the active
  theme can request the language-appropriate header/footer page. Resolution
  order: configured page for current language → configured page for default
  language → slug lookup restricted to top-level pages (`post_parent = 0`,
  to avoid matching translated child pages sharing the same slug). Results
  are cached per-request in a static array.
- `miramedia_cookie_prompt_text` / `_url` / `_url_text` filters let the theme
  cookie banner pull per-language overrides from `mira_ls_cookie_prompt`.
- Admin bar (`add_language_to_admin_bar`) shows the detected language on the
  frontend, and on post edit screens shows that post's own language plus
  dropdown links to edit its translations.
- Menu integration: `register_language_menu_locations()` registers
  language-specific nav menu locations, `filter_menu_locations()` and
  `prefix_menu_item_urls()` rewrite menu item URLs with the current language,
  and `add_flags_to_menu()` / `menu_flags_css()` inject language flag links
  into a chosen menu location (emoji or text mode).
- Admin pages are split into `includes/`: `setup-page.php` (onboarding),
  `settings-page.php` (language/display settings), `guide-page.php`, and the
  Translation Links / Term Translations pages (rendered from methods in the
  main class: `translation_links_page()`, `term_translations_page()`).
- `includes/wpml-migration.php` is a one-off admin tool to import translation
  relationships and page languages from an existing WPML installation
  (reads WPML's DB tables directly via `$wpdb`), plus a cleanup action. Not
  part of normal runtime flow — only relevant when migrating a site off WPML.
- `render_translatable_field()` / `render_translatable_field_admin()` /
  `save_translatable_field()` (static) are reusable helpers for the "single
  post, only one field differs per language, switched via an in-page flag
  button" pattern (as opposed to the separate-post-per-language pattern
  above) — the exhibitor-bio style. They read/write plain post meta keyed
  `{$meta_key_base}_{$lang}` for non-default languages, and the caller
  supplies the default-language text (since it usually already lives
  elsewhere, e.g. `post_content`). `mira-event`'s speakers CPT is the first
  consumer (`speaker_bio`); `cw-plugin-exhibitors` predates these helpers and
  still has its own independent, unrelated implementation of the same idea
  for `ex_rich_text_profile` — not worth retrofitting.

### Conventions worth preserving
- WPML-style URL scheme is intentional: **default language never gets a URL
  prefix**; only non-default enabled languages do. Don't "fix" this to prefix
  every language uniformly.
- CPT content (exhibitors/speakers/sponsors, etc., from the sibling
  `cw-plugin-exhibitors` and `mira-event` plugins) is never translated by
  default — only the URL prefix and surrounding chrome (menu, cookie banner)
  change language for CPT pages. `mira-event`'s `seminars` post type is the
  one exception, having opted into `get_translatable_post_types()` — it gets
  the full separate-post-per-language treatment like pages instead.
- When a translation doesn't exist for a page, link generation falls back to
  the *same slug* under the target language prefix rather than the homepage —
  preserve this behavior in any link-resolution changes.
