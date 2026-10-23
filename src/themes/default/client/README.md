# Huraga client theme

See [shared frontend components and extension contracts](../../../../frontend/README.md#shared-ui)
for the common client/admin implementations. This file covers client-specific behaviour.

Huraga uses the same `@tabler/core` version as the default admin theme. Tabler
provides Bootstrap-compatible CSS and JavaScript; do not also load Bootstrap's
stylesheet or JavaScript bundle. Tabler icons are compiled through the existing
icon manifest.

Use Tabler components and `--tblr-*` tokens for new styles. The shared shell
keeps client navigation, branding settings, mobile behaviour and extension
blocks separate from framework component styles. Both color modes use the
shared `data-bs-theme` preference controller. The client Appearance menu offers
System, Light and Dark; System follows device changes. Saved preferences are
shared with admin. With no saved choice, the configured client theme remains the
default. Prefer `text-secondary` for supporting text
and `card-title` or Tabler's heading scale for section headings: its `h5`/`h6`
utilities are considerably smaller than Bootstrap's.

The runtime exposes `tabler.*`. The `bootstrap.*` alias remains available for
existing module and extension templates, and accepts their `data-bs-*` markup
and Bootstrap events. `_bootstrap-compat.scss` maps common Bootstrap CSS tokens
to Tabler tokens for existing plugins and extension styles. These compatibility
aliases do not load another framework. Tabler initializes tooltips; additional
code should reuse instances with `getOrCreateInstance()`.

Tom Select keeps its Bootstrap-compatible base stylesheet, followed by Tabler's
vendor integration styles. Phone-input styles and the theme's flag assets remain
part of the client build.

Build with `NODE_ENV=production npm run build-huraga` from the repository root.
Check both color modes, narrow screens, dropdowns, tabs and modals after changing
framework dependencies or component styles.

Custom CSS is limited to the client shell, branding geometry, phone/flag
integration, readable content widths and responsive table layouts. Tabler owns
component appearance, focus styling, heading sizes and Tom Select integration.

Use `btn-primary` for the next step in the current task, such as paying, saving
or sending a reply. Keep navigation, optional tools and fallback links secondary
with `btn-outline-secondary`. Separate forms and tabs can each have their own
primary action. After payment, emphasize the next destination; if payment cannot
start, emphasize invoice recovery. Destructive triggers use `btn-outline-danger`
and their final confirmation uses `btn-danger`. Preserve API attributes,
callbacks, extension slots and branding tokens when changing presentation.
Wrap related client-owned buttons in `client-action-group` to keep touch sizing
and gaps consistent and stack actions on narrow screens. Put the primary action
first in the markup so keyboard and visual order agree. Add
`client-action-destructive` to separate a destructive action at the end of a
stack. Destructive confirmation dialogs keep the safe choice first. Keep these
classes off gateway HTML and extension widgets.

API forms keep their existing callbacks and `aria-busy` state. Huraga supplies
the optional `FOSSBilling.ui.formLoading(form, busy)` presenter for local Tabler
loading feedback; other themes retain the core spinner. Existing presenters
are preserved. Flash messages keep the legacy plain-text `flash-message`
session key, with severity stored separately in `flash-message-type`.

Forms marked `data-unsaved-changes` use the browser's standard leave-page warning
after edits, including rich text. Tabs and collapses retain their values and do
not prompt. API success calls the additive `FOSSBilling.ui.formSaved(form)` hook
before the existing callback; errors retain protection. Only submitted values
are marked saved, so edits made during a request remain protected. Extensions
can opt in their own forms and exclude incidental controls with
`data-unsaved-ignore`. No drafts or credentials are persisted in browser storage.

Inline validation uses native constraints and the existing `FOSSBilling.ui`
error presenters. Update errors only after they have been shown; do not flag
untouched fields while typing. Required checkbox groups use
`data-required-checkbox-group` and a translated `data-required-message`, allowing
any option to satisfy the group without replacing extension custom errors.
Keep layout adjustments scoped to `client-*` classes rather than overriding
all `.nav-item`, `.nav-link`, `.card-header` or `.ts-control` elements, since
extension widgets can render their own components in the same layout.

For empty lists and searches, use `partial_client_empty.html.twig` with translated
`empty_title`, optional `empty_description`, and optional `empty_action_label` /
`empty_action_url` values. Pass only the component's context; keep search misses,
empty accounts and out-of-range pages distinct. Use text and a relevant next
step without decorative artwork.

Use English title case for page titles, navigation, headings, field labels,
tabs, short status labels and action text (for example, "Sign In", "Add Funds"
and "Payment Awaiting Confirmation"). Keep minor words such as "a", "and",
"to" and "with" lowercase within titles; capitalize verb particles in "Sign In"
and "Set Up". Keep explanatory copy, validation messages, placeholders and
conversational prompts in sentence case. Translate complete source strings;
do not apply CSS capitalization or case filters to translations, branding,
product names, ticket subjects or extension-provided content.

Use `form-label`, `form-control` / `form-select`, `form-check` and `form-hint` for
account and checkout forms. Labels must target stable control IDs; preserve field
names, required rules, API attributes and widget hooks. Use `table-vcenter` and
accessible column headings for data tables, with `table-responsive` around wide
tables. Keep the client list classes that provide the mobile reading order.

Client JavaScript uses the Tabler data API for dropdowns, tabs, modals and
collapses. Do not add click handlers that duplicate `data-bs-toggle` or
`data-tblr-toggle`. For programmatic components, reuse instances with
`getOrCreateInstance()`; construct collapses with `{toggle: false}` before
calling `show()` or `hide()`. The tab helper supports both attribute prefixes
and delegates events so extension-inserted tabs retain hash navigation.

The client provides `Modals.create(options)` (also `create(type, options)`)
and `Modals.hideAll()` for API confirmations, prompts and plain text dialogs.
It preserves an existing extension-supplied `window.Modals` implementation.
Use `modal-dialog-scrollable` for client dialogs with long content. When a form
wraps the body and footer, add `client-modal-form` so only the body scrolls and
the title and actions remain visible on short mobile screens.
Callbacks run after dismissal and cleanup; cancel does not confirm an action.
Labels come from the translated layout attributes. `bootstrap.*` remains an
alias for the same Tabler component instances, with Bootstrap event names.

Accessibility: keep the skip link and labelled navigation landmarks, use native
Tabler keyboard behaviour, and return focus when a disclosure or dialog closes.
The client adds Escape handling to its mobile navigation and keeps focus on the
visible appearance toggle when switching color mode. Existing modal libraries
and explicit editor accessible names supplied by extensions take precedence.

Screen-reader support: include `partial_client_announcements.html.twig` once in
custom layouts. Its initially empty status and alert regions receive plain-text
messages after an update boundary; visual toasts do not duplicate those live
regions. Error summaries use focus rather than a second live announcement.
Keep messages, close labels and dialog text translated. Extension layout
overrides without the partial receive equivalent regions when a message arrives.
Payment dialogs describe the invoice amount/currency and the one-time or recurring
payment terms. Mobile invoice headings remain in the accessibility tree; visual
cell labels are hidden from it to avoid repeating each heading.

Native screen-reader speech and live payment-provider outcomes need separate
manual testing. Browser accessibility-tree checks cannot replace VoiceOver/NVDA
listening tests; use a provider sandbox for success, decline and recurring payments.

CKEditor colors are mapped to Tabler tokens in the shared theme stylesheet,
including floating UI and source editing. Keep these variables on the theme
root so admin and client editors respond to the same color-mode attribute.
Email previews keep their sandbox and authored styles. Unstyled HTML and the
plain-text fallback support both color schemes; messages with authored colors
or stylesheets keep their original presentation unless they explicitly declare
support for dark mode.
