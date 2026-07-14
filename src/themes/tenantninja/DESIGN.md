---
name: TenantNinja
description: Client billing portal for hosting, domains, and M365 subscriptions
colors:
  accent: "#72E3AD"
  accent-hover: "#8EEAC0"
  accent-press: "#1FAE6B"
  accent-dark-mode: "#006239"
  on-accent: "#1E2723"
  bg: "#FCFCFC"
  bg-deep: "#EDEDED"
  surface: "#FCFCFC"
  surface-2: "#EDEDED"
  surface-3: "#DFDFDF"
  surface-inset: "#F6F6F6"
  border: "#DFDFDF"
  border-strong: "#C7C7C7"
  text: "#171717"
  text-muted: "#525252"
  text-faint: "#707070"
  success: "#3FCF8E"
  warning: "#F6B53C"
  danger: "#CA3214"
  info: "#3B82F6"
typography:
  display:
    fontFamily: "Space Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "64px"
    fontWeight: 700
    lineHeight: 1.05
    letterSpacing: "-0.02em"
  h1:
    fontFamily: "Space Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "44px"
    fontWeight: 700
    lineHeight: 1.2
  body:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, -apple-system, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.45
  label:
    fontFamily: "Hanken Grotesk, ui-sans-serif, system-ui, -apple-system, sans-serif"
    fontSize: "13px"
    fontWeight: 500
    letterSpacing: "0.16em"
  mono:
    fontFamily: "JetBrains Mono, ui-monospace, SF Mono, Menlo, monospace"
    fontSize: "14px"
rounded:
  xs: "4px"
  sm: "6px"
  md: "10px"
  lg: "14px"
  xl: "20px"
  pill: "999px"
spacing:
  1: "4px"
  2: "8px"
  3: "12px"
  4: "16px"
  5: "20px"
  6: "24px"
  8: "32px"
  10: "40px"
  12: "48px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.on-accent}"
    rounded: "{rounded.sm}"
    padding: "0 20px"
    height: "40px"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
  button-primary-active:
    backgroundColor: "{colors.accent-press}"
  card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.text}"
    rounded: "{rounded.lg}"
    padding: "24px"
  input:
    backgroundColor: "{colors.surface-inset}"
    textColor: "{colors.text}"
    rounded: "{rounded.xs}"
    height: "40px"
---

# Design System: TenantNinja

## 1. Overview

**Creative North Star: "The Quiet Ledger"**

TenantNinja's portal is a place people visit to settle something — pay an invoice, renew a domain, check on a service — and leave. It should feel the way a well-run bank branch feels: calm, competent, unmissably clear about numbers and status, and completely uninterested in impressing anyone. The system was rebuilt this cycle specifically to walk back two earlier mistakes: a frosted-glass/mesh-background treatment that made content hard to read, and a lime accent (`#C7F23C`) so saturated it bled into labels, eyebrows, and body copy until the whole interface read as "extra." What replaced both is a flat, opaque surface system with a single restrained green reserved for the moments that matter — a primary button, a focus ring, an active nav item — and nothing else.

The palette itself is lifted from Supabase's shadcn theme: plain, hue-neutral grays (no green tint bleeding into "gray") paired with a soft mint (`#72E3AD`) in light mode and a deep, desaturated green (`#006239`) in dark mode. Both are calmer than the bright `#4ADE80` "ring" green, which is deliberately fenced off to focus states and chart accents only.

**Key Characteristics:**
- Fully opaque surfaces — no backdrop-filter, no translucency, no ambient background art
- One accent, used sparingly, never as a fill on text or backgrounds
- Full-bleed topbar chrome; content lives inside a container, chrome doesn't
- Same tokens and shell across logged-in dashboard and logged-out storefront/login
- Numbers get tabular treatment and monospace where they line up in tables

## 2. Colors

Plain neutral grays carry almost the entire interface; green shows up only where the user needs to notice something.

### Primary
- **Blade Mint** (`#72E3AD`, light mode): The single accent. Used only on primary buttons, focus rings, active nav state, and progress fills — never on labels, eyebrows, or body text.
- **Blade Deep** (`#006239`, dark mode): Dark-mode's primary is deliberately deeper and more desaturated than the light-mode mint, not just an inverted swatch — it reads as ink on the dark surface rather than a glow.

### Neutral
- **Paper** (`#FCFCFC`): Default page and card background in light mode.
- **Mist** (`#EDEDED`): Secondary surface / deep background step, light mode.
- **Fog Border** (`#DFDFDF`): Default hairline border, light mode.
- **Ink** (`#171717`): Primary text, light mode; also the page background in dark mode.
- **Slate Muted** (`#525252`): Secondary text — captions, helper copy, metadata.
- **Ash Faint** (`#707070`): Tertiary text — timestamps, disabled-adjacent labels.

### Semantic
- **Success** (`#3FCF8E`), **Warning** (`#F6B53C`), **Danger** (`#CA3214`), **Info** (`#3B82F6`): status pills, banners, form validation. These are independent of the brand accent and never substitute for it.

### Named Rules
**The One Fill Rule.** The accent green fills exactly one thing per screen at a time: the primary call-to-action. Everywhere else it's a border, a ring, or an icon stroke at most — never a background wash on a card, label, or paragraph.

## 3. Typography

**Display Font:** Space Grotesk (with ui-sans-serif, system-ui fallback)
**Body Font:** Hanken Grotesk (with ui-sans-serif, -apple-system fallback)
**Label/Mono Font:** JetBrains Mono (with ui-monospace, SF Mono fallback)

**Character:** A geometric, slightly technical display face paired with a warmer, more legible grotesk for body copy — confident headings over highly readable running text, with monospace reserved for anything that's actually a number or code (prices, invoice IDs, ports).

### Hierarchy
- **Display** (700, 64px, 1.05 line-height, -0.02em tracking): Marketing-adjacent moments only — the logged-out homepage headline.
- **H1** (700, 44px, 1.2): Page titles ("Invoices", "Services").
- **H2** (600–700, 32px): Section headers within a page.
- **Body** (400, 16px, 1.45 line-height): Default paragraph and UI copy.
- **Label** (500, 13px, 0.16em tracking, uppercase where used): Eyebrows and field labels — used deliberately, not on every section.
- **Micro/Mono** (400, 11–14px): Timestamps, invoice numbers, prices in tables — tabular-nums where digits stack in a column.

### Named Rules
**The Numbers-Are-Mono Rule.** Any value a customer is scanning to compare — price, invoice total, date in a table — renders in JetBrains Mono with tabular figures. Everything else stays in Hanken Grotesk.

## 4. Elevation

Flat by default. Depth comes from a hairline border plus a very soft ambient shadow, not from blur or stacked drop shadows. A one-pixel inset highlight (`--inset-top`) on the top edge of raised surfaces gives just enough of a "lifted" cue without reading as skeuomorphic.

### Shadow Vocabulary
- **shadow-sm** (`0 1px 2px rgba(0,0,0,0.4)`): Default resting elevation for cards, dropdowns, modal content.
- **shadow-md** (`0 4px 16px rgba(0,0,0,0.45)`): Reserved for hover/interactive lift, used sparingly.
- **shadow-pop** (`0 12px 32px rgba(0,0,0,0.6), 0 0 0 1px rgba(255,255,255,0.06)`): Modals only — the one place a stronger pop is earned.
- **glow-blade** (`0 0 0 1px var(--blade-600), 0 6px 24px var(--blade-glow)`): Hover state on the primary button only — the accent's one moment of glow.

### Named Rules
**The No-Blur Rule.** Nothing in this system uses `backdrop-filter`. If a surface needs separation from what's behind it, that's a border and a shadow, not translucency.

## 5. Components

### Buttons
- **Shape:** 6px radius (`--r-sm`), 40px height, 0–24px horizontal padding depending on size.
- **Primary:** `background: var(--accent)`, text `var(--on-accent)`. Hover shifts to `--accent-hover` plus `--glow-blade`; active/focus-visible locks to `--accent-press`.
- **Outline/Ghost:** Transparent fill, `--accent` border and text; hover fills solid with `--glow-blade-soft`, a quieter version of the primary's glow.

### Cards
- **Corner Style:** 14px radius (`--r-lg`).
- **Background:** `var(--surface)`, flat, opaque.
- **Border:** 1px hairline, `var(--border)`, brightening to `--border-strong` on hover.
- **Shadow Strategy:** `shadow-sm` + `inset-top` at rest — see Elevation.
- **Internal Padding:** 24px body, 16/24px header and footer.

### Inputs / Fields
- **Style:** 4px radius (`--r-xs`), background `var(--surface-inset)` (a step lighter/darker than the surrounding surface so fields read as "editable"), 1px `--border`.
- **Focus:** border shifts to `--focus-ring`, plus a 3px soft accent halo (`box-shadow: 0 0 0 0.2rem var(--accent-soft)`).

### Navigation
- **Topbar:** 60px, full-bleed, `background: var(--bg)` (not a surface color — it sits flush with the page), `border-bottom: 1px solid var(--border)` only, no shadow, no rounding. Inner content constrained to a 1200px `.tn-topbar__inner` wrapper with 28px side padding — this is what fixed the earlier "boxed square header" problem, where the header sat inside the page gutter instead of spanning edge to edge.
- **Nav items:** 6px radius, active state uses accent-tinted text/underline, never a solid accent fill.
- **Auth-state gating:** the logged-in and guest topbars render different item sets from one shared partial (`partial_topnav.html.twig`) — dashboard/services/invoices/store for clients, store/knowledge-base/announcements/support for guests. Never show account-scoped nav items to a logged-out visitor.

### Status Pills
- 4px radius, small caps, background is the semantic color at low opacity (`--success-bg`, `--warning-bg`, etc.) with the full-strength color as text — never a solid fill.

## 6. Do's and Don'ts

### Do:
- **Do** keep the accent (`#72E3AD` light / `#006239` dark) to one fill per screen — the primary action.
- **Do** use flat, opaque surfaces with a hairline border (`var(--border)`) and `shadow-sm` for elevation.
- **Do** run the topbar full-bleed with `background: var(--bg)`, containing inner content in `.tn-topbar__inner` rather than the page `.container`.
- **Do** share one design-token system (`_tokens.scss`) across the logged-in dashboard and the logged-out storefront/login — same topbar shell, same card recipe, same accent rules.
- **Do** set tabular-nums and JetBrains Mono on any column of prices, dates, or invoice numbers.

### Don't:
- **Don't** use `backdrop-filter` / glass or translucent surfaces anywhere — this system rejected that treatment as illegible.
- **Don't** apply the accent green to labels, eyebrows, body text, or as a card/section background wash — it's reserved for primary actions, focus rings, and active state only.
- **Don't** nest the header inside `.container` padding — it reads as a boxed, squared-off bar instead of full-width chrome.
- **Don't** use large stock-photo hero imagery (e.g. Unsplash) on the homepage or login page — use a compact, plain-text intro instead.
- **Don't** show account-scoped nav items (Dashboard, Services, Invoices) to a logged-out guest, or vice versa hide store/support links from them.
