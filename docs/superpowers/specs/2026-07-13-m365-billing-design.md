# M365 Billing Support — Design

## Problem

TenantNinja needs to bill clients for Microsoft 365 subscriptions (annual, per-seat) inside FOSSBilling. No M365/Graph API access is available — this is billing management only, updated manually ~once a year at renewal. No WHMCS/Blesta/ClientExec/Upmind plugin does live M365 provisioning either; all handle this as a manual/generic product.

## Research: how other billing platforms handle it

| Platform | Pattern |
|---|---|
| WHMCS | "Other" product type; per-service data in generic `tblcustomfieldsvalues`, not a bespoke schema |
| Blesta | Generic `service_fields` / `service_field_values` key-value store per service |
| ClientExec | Config values on the product template, same generic-field convention |
| Upmind | Generic product custom fields, no dedicated M365 module found |

Convention: **no vendor ships a bespoke DB table for manual/no-API products.** They all use a generic per-service key-value config mechanism. FOSSBilling already has the equivalent: the `Servicecustom` module, which stores an admin-defined `Formbuilder` form's values as JSON on the service record.

## Validated against a live order

Order 21 already proves the base pattern with zero new code:

- Product 9 "Office 365 A3 (Education Faculty Pricing)" — `type = custom` (Servicecustom), `stock_control = 1`, `quantity_in_stock = 999`
- Order: `quantity = 3` (seats), `price = 150` (set manually), `form_id = NULL`, `config = {"period":"1Y"}`

**No new product type or module needed** — `custom` already is the correct type. Each M365 plan/pricing tier (A3 Faculty, A3 Student, Business Standard, etc.) is just another product of this same type, priced/quantity'd manually per order since orders are staff-created, not self-checkout.

## Approach

Reuse existing FOSSBilling primitives — **no new PHP module, no new product type**:

1. **Product per plan tier** (as already done for order 21): one `custom`-type product per M365 SKU/pricing tier. `stock_control` optional (order 21 uses it as a seat-pool counter). `allow_quantity_select` stays 0 since admin sets quantity/price by hand per order — no client self-checkout math to worry about.
2. **Per-order structured fields** — this is where tenant domain, auto-renew, and similar order-specific data belong (they vary per client/order, not per product). Two options, pick per product:
   - **Minimum effort:** use the existing order `notes` free-text field. Works today, zero setup, matches how order 21 currently has no form attached.
   - **Structured (recommended if you want consistent fields across every M365 order):** attach a `Formbuilder` form (`form_id` on the product) with fields:
     - M365 Tenant Domain (text)
     - Auto-Renew (yes/no select)
     - Renewal Notes (textarea)
     - *(Seat count is NOT duplicated here — it's already the native order `quantity` field, as seen on order 21.)*
   Formbuilder-collected values are saved into `client_order.config` as JSON automatically (same column already holding `{"period":"1Y"}` on order 21) and read back via `Service::getConfig()`.
3. **Client-facing gap**: `Servicecustom` ships admin templates only (`mod_servicecustom_config.html.twig`, `mod_servicecustom_manage.html.twig`, `mod_servicecustom_order.html.twig`) — nothing renders to the client. Add one new client template, `mod_servicecustom_client_manage.html.twig` (Huraga theme), read-only, showing tenant domain / auto-renew / quantity (seats) / renewal date pulled from `config` + order fields. Wire it into the client order-view flow the same way other service modules expose a details tab.
4. **Renewal workflow** (manual, annual): staff updates seat count (order `quantity`) and price on the order in admin before the renewal invoice generates — same motion as domain renewal price updates already used in FOSSBilling. Auto-Renew field is informational only (staff still triggers renewal manually); it just tells staff whether to bother renewing this order at all.

## Non-goals

- No Graph/M365 API integration.
- No automated provisioning, suspension, or seat sync.
- No new database table/migration.
- No new product type — `custom` is reused as-is.

## Testing

- Pest module test: Formbuilder field values (tenant domain, auto-renew) persist and round-trip through `Service::getConfig()` / `toApiArray()`, same as the existing `{"period":"1Y"}` config on order 21.
- Manual check: client order page renders the new read-only panel with tenant domain / auto-renew / seats / renewal date.
