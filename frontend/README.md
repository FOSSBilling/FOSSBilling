# Frontend and Theme Builds

FOSSBilling builds shared browser assets from `frontend/` and theme assets from each theme workspace.

## Build Commands

- `npm run build` checks frontend source, builds core assets, and builds all themes.
- `npm run build:production` runs the same build with `NODE_ENV=production`.
- `npm run check` runs TypeScript checks and validates theme icon manifests.
- `npm run build-admin_default` and `npm run build-huraga` build a single theme.

Generated assets are written to `src/public/assets` and `src/themes/**/assets/build`. These paths are ignored by Git and are copied into release artifacts by the Docker build.

Admin widget regression tests use isolated browser fixtures and require no application database or admin credentials. Build production assets first so the tests cover purged CSS:

```sh
npm run build:production
npx playwright test tests/E2E/Playwright/specs/admin/tabler-widgets.spec.ts
```

## Theme Dependencies

Themes own the UI packages they consume. Shared tooling in `frontend/tools` can help compile assets, generate sprites, and post-process CSS, but it should not make a theme depend on a specific UI package.

Examples (the shipped `default` theme package bundles both — `src/themes/default/admin` and `src/themes/default/client`; their npm workspace package names, `admin_default` and `huraga`, are unrelated to their directory paths):

- `admin_default` (`src/themes/default/admin`) owns Tabler, Coloris, Chart.js, Vanilla Calendar Pro, Tom Select, Flag Icons, and its Tabler icon package.
- `huraga` (`src/themes/default/client`) owns Tabler, intl-tel-input, Tom Select, Flag Icons, and its Tabler icon package.
- `svg-sprite` is a root development dependency because the shared sprite helper imports it.

The admin theme uses Tabler's component bundle and `data-bs-*` APIs. Do not load Bootstrap's JavaScript alongside it. The `tabler` global exposes the bundle; `bootstrap` remains a compatibility alias for extensions.

When using admin components:

- Keep `.datepicker`, `YYYY-MM-DD`, and `data-name-from` / `data-name-to` for API date fields. The datepicker adapter loads Vanilla Calendar Pro and synchronizes range values within each form.
- Use the clipboard adapter to retain copy support on HTTP and when Clipboard API access is denied.
- Use `partial_password_strength.html.twig` for translated password feedback; server-side validation remains authoritative.
- Use `data-bs-toggle="autosize"` for plain-text notes, not CKEditor fields. Growth is capped at 24rem.

## Icons

Theme icon sprites are generated from each theme's `icon-manifest.json`.

- Add static SVG icon usage to the relevant theme manifest.
- Mark icons as `"dynamic": true` when the icon name is resolved at runtime, such as admin navigation icons from module metadata.
- Put theme-specific SVG files in the theme's `custom-icons` directory.
- Reference generated symbols with inline sprite IDs, for example `<use href="#home" />`.

Run `npm run check-icons` before changing icon usage.

## Shared UI

| Resource | Location | Responsibility |
| --- | --- | --- |
| Components | `src/themes/default/shared/html` | Common markup; theme partials/macros remain override entrypoints |
| UI behaviour | `frontend/ui` | Tabler lifecycle, accessibility and focus handling |
| API/editor runtime | `frontend/core`, `frontend/editor` | Requests, completion, editor integration |
| Theme styles | `frontend/styles/_shared-theme.scss` | Rules identical across client and admin |
| Reading styles | `frontend/styles/markdown.css` | Theme-aware Markdown typography and media containment |

Use Tabler components and utilities before adding custom CSS or JavaScript.
Share implementations that have multiple consumers; keep one-off code with its
owner. Retain public import paths, Twig blocks, IDs and API attributes used by
extensions. Admin density, client mobile lists, branding settings and workflow
callbacks remain local decisions.

### Components

| Entrypoint | Contract |
| --- | --- |
| `status_badge(status, type)` | Shared semantics; solid admin and subtle client palettes. `badge_palette` accepts extension overrides. |
| `partial_pagination.html.twig` | Shared context/items; retain filters, anchors, page parameter names and each area's record controls. |
| `partial_sortable_th.html.twig` | Preserve query filters and reset the relevant page on sort; expose `scope` and `aria-sort`. |
| `partial_client_empty.html.twig`, admin `partial_empty.html.twig` | Text and optional recovery link. Keep legacy `empty_list()` table-cell behaviour. |
| `partial_search.html.twig`, `partial_client_search.html.twig` | Shared GET form. `search_preserve_fields` allowlists filters; explicit `search_hidden_fields` win. Omit page fields. |
| `partial_notice.html.twig` | Escaped copy or the `notice_content` block. Static notices have no live role; use `alert`/`status` for submission feedback. |
| `partial_card_heading.html.twig` | `card_heading_content` and `card_heading_actions` preserve caller headings, IDs and native controls. |
| `partial_page_heading.html.twig` | H1 with optional subtitle/actions; use `page_title_class` for compact admin titles. |
| `partial_breadcrumbs.html.twig` | Named landmark; retain client `breadcrumbs`/`breadcrumb` and admin `breadcrumbs` blocks and client visibility settings. |
| `partial_company_logo.html.twig` | Explicit URL/null settings, light/dark variants, text fallback, caller link and sizing options. |
| `partial_progress.html.twig` | Named determinate progress with a bounded value and visible percentage. Use status text/spinners for unknown durations. |
| `partial_help_popover.html.twig` | Short supplementary text in a native button outside field labels. Essential instructions stay inline. |
| `partial_ui_feedback.html.twig` | One container and translated labels per layout; retain client dataset aliases. |

Keep admin form/summary/detail-card block names and client action-group ordering.
Do not add generic subtitles that merely describe the title or visible controls.
Status mappings live in `partial_status_badge_shared.html.twig`; unknown statuses
use primary, and unknown types use the generic fallback. Search/filter recovery
belongs outside responsive tables.

### Runtime and extension contracts

- `tabler-modal.ts` handles lifecycle, focus restoration and cleanup. Admin keeps
  its HTML templates and immediate callbacks; client callbacks wait until hidden.
  Both respect an extension-supplied `Modals.create`. Static dialogs use Tabler.
- `tabler-toast.ts` shares rendering. Theme wrappers retain announcement,
  auto-dismiss and focus policies. `notice-dismissal.ts` restores focus only if
  removing a notice would leave it lost.
- `form-errors.ts` handles inline errors, summaries, enhanced controls and focus
  in inactive tabs. Keep theme adapters, existing IDs and `FOSSBilling.ui` hooks.
- `form-loading.ts` presents progress; the API owns locking and retry cleanup.
  The optional submitter argument identifies which button spins. Preserve prior
  disabled/loading states and form-associated controls. Modal status stays outside
  the busy form. Client unsaved-change tracking forwards this hook.
- `api-completion.ts` keeps callback → redirect → reload → inline feedback
  precedence. Only explicit messages cross navigation through `flash.ts`; preserve
  its legacy storage keys and global `flashMessage`. Forms are not reset implicitly.
- `theme-preference.ts` shares System/Light/Dark behaviour and pre-paint setup.
  Respect configured defaults and extension controls.
- `tab-navigation.ts` shares URL updates and horizontal tab reveal. Admin retains
  list namespace pruning, active-tab resets and `data-tab-jump`. Keep history state,
  original field/input nodes and local pane IDs.
- `navigation-disclosure.ts` and `dropdown-focus.ts` recover keyboard focus without
  stealing focus moved elsewhere. Tabler owns transitions and keyboard menus.
- `floating-help.ts` handles Escape and hidden-container cleanup. Tabler initializes
  tooltips/popovers; do not add another initialization loop. Legacy extension
  popovers retain their outside-click policy.
- `email-preview.ts` prepares documents inertly: plain messages may follow the
  theme; authored styles and explicit colour schemes remain authoritative.
  Keep iframe sandbox permissions, existing preview entrypoints and helper overrides.

`_createLoader()` stays in the API. Preserve its `spinner-border` class, 250ms
delay, `_fadeInTimeout` and request-owned cleanup. `data-request-loading` supplies
translated status text. Configured Orderbutton loader images remain supported.

### Styles and assets

Vendor CSS belongs in each theme's `assets/css/vendor.css`; local layout rules
belong in its Sass entrypoint. Both themes load Tabler, including its Tom Select
integration after the plugin preset. Use theme tokens for colour and spacing.
Do not replace branding fonts, invert uploaded images or add light-only surfaces.

Use `.icon` and `.btn.btn-icon` with native link/button semantics and a translated
accessible name. Decorative SVGs are hidden from assistive technology; tooltips
cannot be their only label. Shared mobile targets are 2.75rem; Tabler owns glyph
size and interaction styling. Shared spinners respect reduced motion.

Use `avatar()` with `.avatar` and an adjacent visible name. Its generated
`.db-avatar` class stays safelisted during production cleanup. `.fb-image-contain`
fits branding, gateway and extension assets; content images use `.img-fluid`.
Markdown inherits surrounding typography; client reader widths/line spacing and
admin's denser body scale stay local.

PurgeCSS runs in production and failures fail the build. Its inputs include theme,
module and shared templates plus core/UI runtime sources. Preserve runtime-only
selectors such as the calendar's `data-vc-*` states, CKEditor and generated avatars.
Test production assets when changing dynamic selectors.
