---
target: "https://tenantninja.io/ (homepage/login)"
total_score: 22
p0_count: 1
p1_count: 2
timestamp: 2026-07-14T05-39-39Z
slug: tenantninja-io
---
Method: dual-agent (A: design review · B: detector/browser evidence)

## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 2/4 | Dark-mode toggle leaves the page in a permanent stuck skeleton with no feedback |
| 2 | Match System / Real World | 3/4 | Copy is plain and task-oriented — solid |
| 3 | User Control and Freedom | 3/4 | Standard affordances present |
| 4 | Consistency and Standards | 2/4 | Animated domain-search widget has no equivalent pattern elsewhere in the system |
| 5 | Error Prevention | n/a | Not observable — no logged-in access to invoices/checkout |
| 6 | Recognition Rather Than Recall | 3/4 | n/a issue |
| 7 | Flexibility and Efficiency | 3/4 | n/a issue |
| 8 | Aesthetic and Minimalist Design | 2/4 | Full login form + full marketing hero stacked with equal weight |
| 9 | Error Recovery | 1/4 | Stuck skeleton state has zero recovery path — no retry, no timeout, no fallback |
| 10 | Help and Documentation | 3/4 | KB link present, discoverable |
| **Total** | | **22/36 scored** | **Acceptable band — one login-gated heuristic unassessed** |

## Anti-Patterns Verdict

LLM assessment: Borderline. Token system is disciplined; homepage composition (login + full storefront hero glued together) reads as AI-assembled.

Deterministic scan: 0 findings in themes/tenantninja/html. 105 findings in src/modules/Invoice/templates/pdf/*.css (PDF invoice CSS, out of scope, noted for completeness).

Visual/browser evidence: Reproduced a real P0 bug — dark-mode toggle collapses homepage into a permanent stuck skeleton, fails silently.

## Overall Impression

The rebuild fixed what it set out to fix (lime overload, glass/blur mess gone). But the homepage has a structural identity problem — login screen and storefront landing page glued together — undermining the "Quiet Ledger" premise. A real P0 bug sits on the highest-traffic page.

## What's Working

1. Token discipline holds up under inspection — accent stays confined to buttons/focus/active states.
2. Full-bleed topbar correctly built, matches DESIGN.md exactly.
3. Auth-gated nav partial works as designed — verified in DOM, no leak of account-scoped items to guests.

## Priority Issues

[P0] Dark-mode toggle breaks the homepage into a permanently stuck skeleton.
Why it matters: low-stakes action fails silently with zero recovery, on the highest-traffic page. Reproduced independently by both agents.
Fix: toggle should be a pure data-bs-theme attribute flip, not trigger an async re-fetch. Audit tenantninja.js for the request firing on toggle.
Suggested command: /impeccable harden

[P1] Homepage conflates login and marketing storefront into one screen with equal visual weight.
Why it matters: contradicts PRODUCT.md's "minimal friction" purpose for task-focused, low-frequency visitors.
Fix: give sign-in primacy (single-column, centered); demote storefront pitch to /order or a secondary link.
Suggested command: /impeccable distill or /impeccable layout

[P1] DOM order and visual order diverge (WCAG 1.3.2 risk).
Why it matters: screen-reader/keyboard users hit the page in a different sequence than sighted users. WCAG AA is a stated PRODUCT.md requirement.
Fix: reorder markup to match visual order; stop relying on CSS order to flip it.
Suggested command: /impeccable audit

[P2] Animated domain-search widget contradicts the system's own restraint principle, dilutes primary CTA.
Why it matters: undocumented decorative pattern; two full-green buttons (Sign in, Search) compete for the same weight, violating the One Fill Rule.
Fix: demote Search to outline/ghost, or move widget to /order entirely.
Suggested command: /impeccable quieter

[P3] Missing autocomplete attributes on login fields.
Why it matters: degrades password-manager autofill UX.
Fix: add autocomplete="email" / autocomplete="current-password" in mod_page_login.html.twig.
Suggested command: /impeccable harden

## Persona Red Flags

Jordan (first-timer): On mobile, Sign In renders above the explanatory headline; "Create an account" buried in small text.

Sam (accessibility-dependent): Screen-reader tab order hits the entire marketing hero before Sign In — reverse of sighted visual order. Concrete WCAG 1.3.2 violation.

Riley (stress-tester): The single "safest to poke" control (theme toggle) breaks the page outright on first or second click, no recovery.

## Minor Observations

- Footer news items are unconfigured FOSSBilling stock demo content.
- Mobile topnav drops Store/KB/Announcements/Support links with no visible hamburger menu.
- Detector flagged Dejavu Sans and off-token hex/font-size values in PDF invoice CSS — low priority.

## Questions to Consider

- Is / actually trying to be two different pages (storefront + login) that should split into / and /login?
- If "The Quiet Ledger" rejects flashy/decorative elements, why an animated cycling-text search box?
- Has the theme toggle been smoke-tested since the last deploy? It's a two-click repro on the highest-traffic page.
