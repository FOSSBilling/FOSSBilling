# Product

## Register

product

## Platform

web

## Users
SMB and individual customers who purchased hosting, domains, or Microsoft 365 licenses from TenantNinja. They log in infrequently and task-focused: paying an invoice, checking a service's status, opening a support ticket, or renewing/ordering something. They are not browsing for pleasure — every visit has a specific job, and the portal should get them in and out with minimal friction.

## Product Purpose
TenantNinja's client portal (built on FOSSBilling/Twig) is the self-service front door to a customer's account: dashboard, services, invoices, support, and the order/checkout flow for new products. It exists so customers can manage their own billing and services without contacting support for routine tasks. Success is a customer completing their task (paying, renewing, checking status) quickly and without confusion, on both the logged-in dashboard and the logged-out storefront/login.

## Positioning
A fast, trustworthy self-service account portal — customers can pay, renew, and manage their hosting, domains, and Microsoft 365 subscriptions without ever needing to open a support ticket.

## Brand Personality
Clean, trustworthy, modern. The interface should read as a serious, professional billing tool a customer trusts with their card details and renewals — not flashy or loud. Confidence is communicated through clarity and restraint, not decoration.

## Anti-references
- Oversaturated lime/neon accent color used broadly (labels, eyebrows, body text). The theme's original bright `#C7F23C` lime read as "super extra green" once applied everywhere; the accent should be a single restrained green reserved for primary actions, focus rings, and active state only.
- Boxed/square headers nested inside a page gutter. The topbar must be a flush, full-width bar — never boxed inside `.container` padding.
- Large stock-photo hero imagery on the homepage or login page. Prefer compact, plain-text intros over Unsplash-style photography.

## Design Principles
- **Opaque, solid surfaces over decoration.** Cards and panels use flat backgrounds, hairline borders, and subtle shadows — no glass/blur effects revealing content behind them.
- **Accent used sparingly, not as wallpaper.** Green appears only on primary actions, focus rings, and active/selected state — never as a fill on labels, backgrounds, or body copy.
- **One consistent design language across auth states.** Logged-in dashboard and logged-out storefront/login share the same tokens, topbar shell, and card treatment — no separate "marketing mode" visual system.
- **Full-bleed structural chrome.** Navigation and page-level chrome (topbar) span edge-to-edge; content gets the container, chrome does not.
- **Design tokens are the source of truth.** All color, spacing, radius, and typography decisions flow through `_tokens.scss` custom properties, scoped for light (`:root`) and dark (`[data-bs-theme="dark"]`) — never hardcoded per component.

## Accessibility & Inclusion
WCAG AA contrast minimum across both light and dark modes. All motion/animation must respect `prefers-reduced-motion`.
