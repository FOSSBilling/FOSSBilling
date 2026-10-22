# Huraga client theme

Huraga uses the same `@tabler/core` version as the default admin theme. Tabler
provides Bootstrap-compatible CSS and JavaScript; do not also load Bootstrap's
stylesheet or JavaScript bundle. Tabler icons are compiled through the existing
icon manifest.

Use Tabler components and `--tblr-*` tokens for new styles. The shared shell
keeps client navigation, branding settings, mobile behaviour and extension
blocks separate from framework component styles. Both colour modes use the
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
Check both colour modes, narrow screens, dropdowns, tabs and modals after changing
framework dependencies or component styles.

Custom CSS is limited to the client shell, branding geometry, phone/flag
integration, readable content widths and responsive table layouts. Tabler owns
component appearance, focus styling, heading sizes and Tom Select integration.

Use `btn-primary` for the next step in the current task, such as paying, saving
or sending a reply. Keep navigation, optional tools and fallback links secondary
with `btn-outline-secondary`. Separate forms and tabs can each have their own
primary action. After payment, emphasise the next destination; if payment cannot
start, emphasise invoice recovery. Destructive triggers use `btn-outline-danger`
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

API forms preserve named submitters, uploads and existing disabled buttons,
reject overlapping submissions, and restore their busy state after a response.
Extension validators can cancel the submit event in capture phase before the API
handler. Continue using `fb_api_form()` rather than adding another API submit
handler to the same form. Password widgets can be initialized again safely.

Accessibility: keep the skip link and labelled navigation landmarks, use native
Tabler keyboard behaviour, and return focus when a disclosure or dialog closes.
The client adds Escape handling to its mobile navigation and keeps focus on the
visible appearance toggle when switching colour mode. Existing modal libraries
and explicit editor accessible names supplied by extensions take precedence.

The client exposes optional `FOSSBilling.ui.formError`, `clearFormError`,
`fieldError` and `clearFieldError` hooks. API failures remain in a focusable,
labelled summary inside the originating form; native invalid fields gain linked
feedback without losing their existing hints. Errors on hidden forms use a
persistent notification instead of moving focus into hidden content. General
success/status notifications are polite; error notifications remain until closed.
The editor adapter transfers form labels, required state and hints to the editable
surface. Preserve those associations when adding custom controls or editor adapters.

Screen-reader support: include `partial_client_announcements.html.twig` once in
custom layouts. Its initially empty status and alert regions receive plain-text
messages after an update boundary; visual toasts do not duplicate those live
regions. Error summaries use focus rather than a second live announcement.
Keep messages, close labels and dialog text translated. Extension layout
overrides without the partial receive equivalent regions when a message arrives.
Payment dialogs describe the invoice amount/currency and the one-time or recurring
payment terms. Mobile invoice headings remain in the accessibility tree; visual
cell labels are hidden from it to avoid repeating each heading.

### Accessibility review, 22 October 2026

Verified using Chromium's accessibility tree and keyboard interaction, with
sample data at desktop and 320px mobile widths:

- Navigation exposes named landmarks, a skip link, current-page state and
  expanded/collapsed controls. Hidden desktop/mobile navigation is excluded.
  Sidebar balance and notes do not introduce unrelated level-five/six headings.
- Password validation exposes named, required, invalid controls with error
  descriptions while retaining password guidance. A server product-configuration
  error and a simulated checkout error expose a focused, labelled summary.
- Payment confirmation dialogs expose invoice amount, currency and payment
  terms as their description. Escape returns focus to the payment-method button.
  Mobile charges retain table, column-header and cell roles. Checkout has a named
  payment-method group and row headers for totals. No purchase was completed.
- Status and error messages update separate live regions without changing focus
  or adding a second live announcement on the visible toast. Identical messages
  can be repeated. Native speech output has not been verified.
- Payment-return headings distinguish awaiting confirmation from confirmed
  payment. Template tests cover startup failure, provider handoff/manual fallback,
  refunded/canceled invoices, wallet deposits and unavailable payment methods.
  Gateway HTML and third-party hosted payment pages retain their extension
  contracts and require their own assistive-technology testing.

Native VoiceOver/NVDA speech and browse-mode testing remains pending: this
workspace's browser tools expose the accessibility tree but cannot operate a
native screen reader or capture its speech. A browser-tree check is not a
screen-reader listening test or a WCAG conformance claim.

For a listening pass in VoiceOver/Safari and NVDA/Firefox or Chrome:

1. Navigate by landmarks, headings and links. Activate the skip link; open/close
   the mobile navigation and account/notification disclosures. Confirm the current
   page and expanded state are spoken and focus returns on Escape.
2. Submit empty sign-in/password fields. Listen for the control name, required
   and invalid states, hints and error. Correct the value and confirm stale errors
   disappear. Trigger a rejected form request, follow its summary links, and check
   the editor label/error while entering a support reply without sending it.
3. Review charges using table-navigation commands on desktop and mobile. Open a
   payment method and listen for invoice, amount, currency and recurrence terms.
   Tab both directions, cancel, and confirm focus returns to the method.
4. In a payment-provider sandbox, test one-time/recurring handoff, decline,
   cancellation, pending confirmation and success. Confirm the outcome heading,
   manual fallback and retry/support links; do not make duplicate payments.
5. Trigger status/error notifications, including repeating the same message.
   Confirm each is spoken once, ordinary updates are polite, errors remain
   available, and dismissing a toast restores focus appropriately.

Reference patterns: [W3C status messages](https://www.w3.org/WAI/WCAG22/Techniques/aria/ARIA22)
and [modal dialogs](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/).

### Final regression review, 22 October 2026

The review fixed payment-panel focus after the checkout form is removed, and
invoice recovery for missing/empty embedded gateway content or synchronous
extension gateway errors. The invoice recovery link describes the payment status;
focus moves after the panel becomes visible. Gateway HTML and parent-target
continuation links retain their existing contracts. No purchase was completed.

The standalone order widget now accepts both the legacy `order` product parameter
emitted by the embed script and the current `product` parameter. A product supplied
by the controller still takes precedence. Its public layout contains the grid
inside a padded main landmark to prevent horizontal overflow.

Verification covered all eleven main client routes at desktop and 320px widths,
guest authentication pages, sample service/ticket/email/news/help details,
checkout recovery, payment startup failure, and pending/confirmed outcomes.
Browser checks found no remaining overflow, broken visible images or captured
JavaScript errors on the main routes. Extension-supplied modal and form-error
handlers survived initialization; dynamically inserted Tabler tabs worked and
Bootstrap aliases resolved to the same component constructors. Existing widget
hook names, logo settings and navigation visibility contracts remain covered.

All 86 JavaScript tests, 274 final focused PHP tests, type/icon checks, formatting
checks and production builds for core/admin/client passed. The broad PHP rerun
excluded the unchanged `SessionTest.php`: 4,647 tests ran with four PostgreSQL
checks skipped and no failures. Running that session file separately reproduces
two failures because its fingerprint-validation expectations assume fingerprinting
is enabled, while this workspace disables it. Production session behavior was
not changed to satisfy those tests.

Live payment-provider success, decline and recurring authorization still require
a configured provider sandbox. This demo's Custom gateway has no payment
instructions; its startup-failure recovery was verified. Native screen-reader
speech testing remains pending as recorded above.

CKEditor colours are mapped to Tabler tokens in the shared theme stylesheet,
including floating UI and source editing. Keep these variables on the theme
root so admin and client editors respond to the same colour-mode attribute.
Email previews keep their sandbox and authored styles. Unstyled HTML and the
plain-text fallback support both colour schemes; messages with authored colours
or stylesheets keep their original presentation unless they explicitly declare
support for dark mode.
