# Yolfi hosted checkout

This adapter adds Yolfi to the existing FOSSBilling payment-gateway architecture. It uses the native Checkout Sessions API, not a Stripe-compatible API.

## Setup

1. Add and enable the Yolfi gateway in the payment-gateway administration screen.
2. Configure its Yolfi API key and its webhook endpoint signing secret. Both fields are marked secret for the existing credential-masking mechanism. These are different credentials.
3. In Yolfi, configure a native webhook endpoint with adapter `NONE`, using the gateway's callback URL shown by FOSSBilling (`ipn.php?gateway_id=...`, without a fixed invoice ID). Enable `payment.confirmed`. Use that endpoint's signing secret, not the API key.
4. Enable one-time payments only. Restrict the gateway's accepted currencies to the supported subset: USD, EUR, CNY, RUB, INR, TRY, KRW and JPY. Leave test mode disabled; this adapter does not implement a separate sandbox transport.

The invoice must be unpaid, without partial payments or applied account credit. The adapter sends the final server-calculated tax-inclusive invoice total as a decimal string in pricing-currency major units. It does not send buyer names, email addresses, cart line items or other customer fields.

The browser receives an escaped link to Yolfi's hosted checkout. Successful and cancelled checkouts return to the existing FOSSBilling payment-return page. Neither a return URL nor a checkout status of `COMPLETED` is accepted as proof of payment. Cancelled, expired and overdue attempts leave the invoice unpaid.

## Verification and retries

Native callbacks are authenticated before storage through `JsonWebhookAuthenticatorInterface`, then authenticated again when processed. Verification uses HMAC-SHA256 over the exact raw request body, base64 decoding and `hash_equals`. Only live, one-time `payment.confirmed` events with confirmed status can settle an invoice. Other correctly signed event types are acknowledged without crediting anything.

The adapter retrieves the organization-scoped Checkout Session with the configured API key. It checks its ID, immutable fiat amount, currency and order reference against the invoice, gateway and invoice hash. A supplied webhook order reference must agree. Callback query parameters cannot change the signed payment target. Webhook token amounts and USD conversions are not used as fiat invoice amounts.

The signed event ID is recorded as the transaction identifier. Financial replay protection uses FOSSBilling's existing atomic `recordGatewayPayment` contract with the Yolfi invoice ID as the stable receipt reference. That also covers different confirmation event IDs for the same payment. The existing receipt service owns settlement, credit locking and completion recovery; this adapter does not implement a separate balance-credit path. Before a new receipt is committed, its settlement callback rechecks that the invoice is unpaid, unchanged and has no partial credit. A second distinct payment for an already-paid invoice is refused for reconciliation rather than credited again.

Checkout creation uses a deterministic idempotency key derived from the exact request and the checkout revision. An HTTP timeout can be retried with the same request without creating another logical attempt. Requests have finite timeouts, reject redirects and non-2xx or malformed responses, and do not expose transport exceptions or API response bodies.

A checkout revision defaults to `1`. Changing it permits another logical attempt. Do this only after reviewing the previous attempt and any payments, including late confirmations. Expired sessions are not silently replaced. Existing valid confirmations remain bound to their original invoice even after a revision change. Do not alter the invoice's amount, currency or gateway while a payment is pending; mismatches are refused for review.

## Supported scope

One-time, full-invoice hosted checkout only. No automatic renewals, saved cards, refunds, history synchronization, manual payment confirmation, partial payments or partial-credit invoices. Recurring invoice charges and subscription requests are not implemented. Unsupported currencies, configured test mode and subscription checkout requests fail explicitly.

## Focused offline tests

Prerequisites: PHP 8.3 or newer with `proc_open` enabled, the project's already-installed Composer dependencies under `src/vendor` (including `symfony/filesystem` and the HTTP client contracts brought by `symfony/http-client`), and Python 3 with its standard-library `sqlite3` module. The runner directly loads the required dependency files from that existing vendor layout; it does not install packages or start the application. No dependency source copies belong in these fixtures.

From the repository root:

```sh
php -n tests/fixtures/yolfi/run.php
# Optional on POSIX shells: select a Python executable instead of python3 on PATH.
YOLFI_TEST_PYTHON=/path/to/python3 php -n tests/fixtures/yolfi/run.php
```

`YOLFI_TEST_PYTHON` accepts one executable path or name, not a shell command or arguments; when unset or empty it defaults to `python3` on `PATH`. The runner checks Python 3/`sqlite3` availability before executing the groups and reports a missing prerequisite as a failure, not a skipped/pass result. Python and `proc_open` are **test-only** requirements, not adapter or production dependencies. The test launcher must permit the child process and writes to a private temporary directory. Use the same filesystem/network isolation as other inspected offline fixtures; `-n` disables PHP ini loading but does not itself provide isolation.

The standalone runner executes the actual adapter, upstream invoice/transaction entities, private gateway-settings validator, native exception/Config chain and callback retry selection/error methods. Its Python bridge executes those methods' unchanged SQL with bound parameters against a real temporary SQLite database; it is a transport, not a replacement retry policy. HTTP, dependency injection, the client, repositories and the invoice settlement service are explicitly mocked collaborators. The receipt-service double models its inspected deduplication and settlement-callback contract; these tests do not execute receipt settlement SQL, prove database concurrency, exercise the real Doctrine DBAL driver or replace the upstream Pest/application test suite.

Coverage includes native creation and deterministic retry keys, raw-body signature tampering, verified confirmation, reference/money/currency mismatches, duplicate confirmation, non-payment events, API errors/timeouts, invalid URLs, unsupported inputs and settlement retry. All HTTP responses and payment events are synthetic fixtures. No live payments, real credentials, full application build or upstream CI are claimed.

Public contract references:

- https://docs.yolfi.com/en/checkout/api-reference
- https://docs.yolfi.com/en/checkout/quickstart
- https://docs.yolfi.com/en/checkout/lifecycle
- https://docs.yolfi.com/en/webhooks/webhook-details
- https://docs.yolfi.com/en/webhooks/webhook-signatures
