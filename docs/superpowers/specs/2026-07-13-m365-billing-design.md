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

## Approach

Reuse existing FOSSBilling primitives — **no new PHP module**:

1. **Formbuilder form** "M365 Subscription Details" with fields:
   - M365 Tenant Domain (text)
   - Plan (select: Business Basic / Business Standard / Business Premium / Apps for Business / E3 / E5 / other)
   - Seat Count (number)
   - Price per Seat / year (text, admin reference only — actual billing price lives on the Product)
   - Renewal Notes (textarea)
2. **Product**: create one FOSSBilling product per M365 plan tier, type = Custom, billing cycle = Annually, quantity enabled (existing Product feature) so price = seats × per-seat price at checkout. Attach the Formbuilder form to each product.
3. **Client-facing gap**: `Servicecustom` ships admin templates only (`mod_servicecustom_config.html.twig`, `mod_servicecustom_manage.html.twig`, `mod_servicecustom_order.html.twig`) — nothing renders to the client. Add one new client template, `mod_servicecustom_client_manage.html.twig` (Huraga theme), read-only, showing the Formbuilder field values plus order renewal date. Wire it into the client order-view flow the same way other service modules expose a details tab.
4. **Renewal workflow** (manual, annual): staff updates seat count / price on the order in admin before the renewal invoice generates, same motion as domain renewal price updates already used in FOSSBilling.

## Non-goals

- No Graph/M365 API integration.
- No automated provisioning, suspension, or seat sync.
- No new database table/migration.

## Testing

- Pest module test: form values persist and round-trip through `Service::getConfig()` / `toApiArray()`.
- Manual check: client order page renders the new read-only panel with seat/plan/domain data.
