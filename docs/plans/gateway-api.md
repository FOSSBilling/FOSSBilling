# The gateway extension API

Status: proposal. Supersedes the ad-hoc contract in
`src/library/FOSSBilling/Extension/Contract/Payment`.

The directory move gave gateways a home, a namespace and a manifest. It did not
give them an API — it carried the BoxBilling one across unchanged. This document
answers the question that was deferred: what should the gateway API be, if we
were writing it today?

## 1. What we actually have

Before proposing anything, the evidence. All four bundled gateways were read,
along with every core call site.

### 1.1 There are two gateway shapes, chosen by `method_exists`

`Box\Mod\Invoice\Service::getPaymentHtml()` branches on whether the adapter
happens to have a `getHtml()` method:

```php
if (method_exists($adapter, 'getHtml')) {
    return [...$adapter->getHtml($this->di['api_system'], $invoice->id, $subscribe)];
}
$mpi = $this->getPaymentInvoice($i, $subscribe);
$r = ($subscribe) ? $adapter->recurrentPayment($mpi) : $adapter->singlePayment($mpi);
```

Core does this six times across the payment path: `getHtml`, `processTransaction`,
`getInvoiceId`, `getTransaction`, `setDi`, `setLog`. None of it is expressed as a
type. A gateway that misspells a method silently takes the other branch.

### 1.2 The elaborate half of the contract is dead

`singlePayment()` and `recurrentPayment()` are called from exactly one line
(`src/modules/Invoice/Service.php:1791`) and implemented by **zero** gateways in
the tree — the only implementation anywhere is a Mockery double in
`src/modules/Invoice/tests/Unit/ServiceTest.php:2828`. Because all four bundled
gateways define `getHtml()`, the branch above returns first and line 1791 is
unreachable.

Everything that exists to serve that branch is therefore also dead:

| Dead | Purpose it served |
| --- | --- |
| `Payment\Invoice`, `Invoice\Buyer`, `Invoice\Item`, `Invoice\Subscription` | argument to `singlePayment()` |
| `Service::getPaymentInvoice()` | builds the above |
| `AdapterAbstract::TYPE_HTML/TYPE_FORM/TYPE_API`, `getType()` | interpreting its return |
| `AdapterAbstract::getServiceUrl()`, `moneyFormat()`, `setOutput()/getOutput()` | helpers for it |

`getTransaction()` is in the same position: `ServiceTransaction::_parseIpnAndApprove()`
requires it and throws error 705 otherwise — and none of the four gateways define
it. The `Payment\Transaction` value object and its `TXTYPE_*` constants are reached
only through that throw.

So the premise is worth stating plainly: **for gateways, there is not much of an
existing design to build on.** There is one live path (`getHtml` +
`processTransaction` + `isIpnValid`) and a large amount of scaffolding around a
path nothing takes.

### 1.3 The live path puts core's job inside the gateway

`processTransaction($api_admin, $id, $data, $gateway_id)` is handed the **admin
API proxy** and is expected to settle the invoice itself. Consequences visible in
the tree today:

- `PayPalEmail::isIpnDuplicate()` runs raw SQL against `transaction` to implement
  webhook idempotency — per gateway, and only for the one gateway that remembered.
- `ClientBalance` calls `payInvoiceWithCredits()` and `markTransactionProcessed()`.
- `Stripe` reaches the container 63 times, mostly to do bookkeeping.

A gateway knows one thing core doesn't: what a given webhook payload means. It
knows nothing about invoices, credit, or transaction rows — and shouldn't.

### 1.4 Smaller problems

- **Capabilities are self-declared strings.** `supports_subscriptions`,
  `supports_one_time_payments`, `can_load_in_iframe` live in a static array; core
  trusts them and then calls methods that may not exist.
- **`getConfig()` does three jobs** — capability flags, presentation (logo,
  description) and the settings form schema — and overlaps `extension.json`.
- **Settings and request data share one bag.** `return_url`, `cancel_url`,
  `notify_url` and `redirect_url` are injected into the merchant's config array
  and read back with `getParam()`. `AdapterAbstract`'s constructor throws if any
  is missing, so every gateway pays for URLs it may not use.
- **Settings validation is `new $class($config)` in a try/catch**
  (`ServicePayGateway::validateGatewayConfig()`).
- **Webhook requests have no headers.** Signature verification (Stripe) has to
  reach into `$_SERVER`.
- **Outbound calls exist but are untyped.** `ServiceSubscription` really does
  cancel subscriptions at the provider — through three more `method_exists`
  checks, one of which silently no-ops (§3.5). Refunds, by contrast, are
  local bookkeeping only: core cannot initiate one. WHMCS (`_refund`) and Blesta
  (`refund`) both can.
- **Test mode is mutable post-construction** (`setTestMode()`) *and* duplicated
  into `$config['test_mode']`.

## 2. What the prior art is worth

**WHMCS** — worth taking: the callback contract is explicit and the helpers
(`checkCbTransID`, `addInvoicePayment`, `logTransaction`) keep settlement in core
where it belongs; that is exactly the inversion we need. Worth rejecting: naming
by function prefix (`gatewayname_capture`), and untyped `$params` arrays where
every key is a documentation promise rather than a type.

**Blesta** — worth taking: capability *interfaces* rather than capability flags
(`NonmerchantGateway`, `MerchantCc`, `MerchantCcOffsite`), and normalising a
callback into one structured return from `validate()`/`success()`. Worth
rejecting: `Input::setErrors()` as an out-of-band error channel, and the exact
shape of its install/upgrade/uninstall lifecycle — we want the hooks (§3.7), but
not signatures that hand the extension a database id and an instance count.

Neither is a model to copy. Both are PHP 5-era designs carrying their own
compatibility burdens. What they confirm is the shape: **a small required
interface, capabilities as types, and settlement owned by core.**

## 3. Proposed contract

Namespace `FOSSBilling\Extension\Contract\Payment`. Every gateway implements
`Gateway`; everything else is opt-in and detected with `instanceof`, never
`method_exists`.

### 3.1 Required

```php
interface Gateway
{
    public function checkout(CheckoutRequest $request): Checkout;
}
```

One method. `checkout()` replaces `getHtml()`, `singlePayment()`,
`recurrentPayment()`, `getServiceUrl()` and `getType()`. Everything `getConfig()`
used to carry moves to the manifest (§3.6) or becomes a capability interface
(§3.2), so there is no `describe()`.

Merchant settings are constructor state; per-payment data is the argument. That
separation is the fix for the shared `$_config` bag:

```php
final readonly class CheckoutRequest
{
    public function __construct(
        public InvoiceView $invoice,
        public bool $subscription,
        public CheckoutUrls $urls,   // return, cancel, callback
        public bool $testMode,
    ) {}
}
```

`Checkout` is a closed set, so core stops guessing at the return type — and the
`(array)` cast working around boxbilling#108 goes away:

```php
Checkout::html(string $html)                                  // render in page
Checkout::redirect(string $url)                               // send the browser
Checkout::form(string $action, array $fields, string $method) // auto-submitting form
```

### 3.2 Capabilities

```php
interface HandlesWebhooks { public function handleWebhook(WebhookRequest $r): WebhookResult; }
interface ContextAware    { public function setContext(Context $c): void; }

// Can take a recurring payment at all. A marker: the work happens in
// checkout() when CheckoutRequest::$subscription is true.
interface SupportsSubscriptions {}

// Outbound subscription control — see §3.5. Separate interfaces because a
// gateway may support one and not the other.
interface CancelsSubscriptions            { public function cancelSubscription(string $reference): void; }
interface CancelsSubscriptionsAtPeriodEnd { public function cancelSubscriptionAtPeriodEnd(string $reference): void; }
```

There is deliberately no `SupportsRefunds` — see §3.5.

`supports_subscriptions` becomes `$gateway instanceof SupportsSubscriptions`.
`supports_one_time_payments` disappears: implementing `Gateway` *is* the claim
that you can take a one-time payment. A capability can no longer be claimed and
not delivered.

**Taking subscriptions and cancelling them are different capabilities**, and an
earlier draft of this section conflated them. All three remaining bundled
gateways declare `supports_subscriptions = true`, but only `Stripe` can cancel
remotely — `Custom` merely renders recurring-payment instructions and
`PayPalEmail` hands off to PayPal's own subscription buttons. Core needs the
distinction *before* it offers the option: `allow_recurrent`,
`canPerformRecurrentPayment()` and the client-side payment prompt all gate on
"can this gateway take a subscription", which is not "can it cancel one".

| Gateway | `SupportsSubscriptions` | `CancelsSubscriptions` | `…AtPeriodEnd` |
| --- | --- | --- | --- |
| `Custom` | yes | — | — |
| `PayPalEmail` | yes | — | — |
| `Stripe` | yes | yes | yes |


### 3.3 The webhook inversion

This is the substantive change. A gateway parses; core settles.

```php
final readonly class WebhookRequest
{
    public function __construct(
        public array $query,     // $_GET
        public array $body,      // $_POST
        public string $rawBody,  // php://input
        public array $headers,   // signature verification, missing today
    ) {}
}

final readonly class WebhookResult
{
    /** @param list<PaymentEvent> $events */
    public function __construct(public array $events, public ?string $responseBody = null) {}

    public static function ignore(?string $responseBody = null): self;
}

final readonly class PaymentEvent
{
    public function __construct(
        public EventKind $kind,      // Captured | Refunded | SubscriptionStarted | SubscriptionCancelled
        public string $reference,    // the gateway's own id — core dedupes on this
        public float $amount,
        public string $currency,
        public ?int $invoiceId = null,
        public ?string $subscriptionReference = null,
        public ?float $fee = null,
    ) {}
}
```

`handleWebhook()` replaces four methods and a side channel: `getInvoiceId()`,
`isIpnValid()`, `getTransaction()`, `processTransaction()` and
`setOutput()/getOutput()`. The admin API proxy is no longer passed to anyone.

Core dedupes on `(gateway, reference)` once, for every gateway — deleting
`PayPalEmail::isIpnDuplicate()` and the class of bug where a gateway forgets.

### 3.4 What `Context` becomes

Most of the current thirteen methods exist because gateways settle invoices. Once
they don't, and the invoice arrives in the request, what's genuinely left is:

```php
interface Context
{
    public function logger(): LoggerInterface;
    public function httpClient(): HttpClientInterface;
    public function url(string $path): string;
    public function renderTemplate(string $template, array $vars): string; // Custom needs this
}
```

`findInvoice`, `isDepositInvoice`, `invoiceTotalWithTax`, `payInvoiceWithCredits`,
`payClientInvoicesWithCredits`, `clientBalance`, `currentClientId`,
`transactionInvoiceId`, `markTransactionProcessed` and `callbackUrl` all go.

**What actually landed:** with ClientBalance promoted to core (§4), `Context`
dropped straight to the three methods that had a caller today —
`logger()`, `httpClient()` and `url(string $path)` — in both
`FOSSBilling\Extension\Contract\Payment\Context` and its implementation,
`Box\Mod\Invoice\PaymentContext`. `renderTemplate()` is deferred to the `Custom`
migration that will need it, rather than added ahead of a caller.
`ContextAware`, `Context`, `PaymentContext` and `InvoiceView` all stay in place
for that next change; `Custom`, `PayPalEmail` and `Stripe` remain on the old
`setDi()` path in the meantime, so `ServicePayGateway::getPaymentAdapter()` still
branches on both.

### 3.5 Subscription cancellation in, refunds out

§1.4 listed "nothing is outbound" as one problem. It is actually two, and they are
in completely different states.

#### Subscription cancellation already ships — it just isn't typed

This is not a "now or later" question. Outbound cancellation is a live,
user-facing feature today, wired end to end and discovered by `method_exists` in
three places in `Box\Mod\Invoice\ServiceSubscription`:

| Site | Check |
| --- | --- |
| `cancelAtGateway()` (via `cancel()`, `cancelForOrder()`) | `method_exists($adapter, 'cancelSubscription')` |
| `scheduleCancellation()` (via `scheduleCancellationForOrder()`) | `method_exists($adapter, 'cancelSubscriptionAtPeriodEnd')` |
| `canCancelAtPeriodEndForOrder()` | `method_exists(...)` — **gates the admin UI** |

`Stripe` implements both. Cancelling an order cancels the subscription at the
provider. Removing this from the contract would be a regression, so it goes in.

Two corrections to the §3.2 draft fall out of this:

1. **It is two methods, not one.** Immediate cancellation and cancellation at
   period end are distinct operations that core calls from different places, and
   a gateway can plausibly support one without the other. Two interfaces, not one
   interface with two methods — otherwise a gateway that only does immediate
   cancellation has to implement a throwing stub.
2. **Typing it fixes a real bug.** `cancelAtGateway()` currently *silently
   no-ops* when the adapter has no `cancelSubscription` method:

   ```php
   if (method_exists($adapter, 'cancelSubscription')) {
       $adapter->cancelSubscription($subscriptionId);
   }
   ```

   The subscription is then marked `canceled` locally while the provider keeps
   billing the customer. With `instanceof`, core can tell the admin the gateway
   cannot cancel remotely and that it must be done in the provider's dashboard —
   rather than reporting success and quietly leaving a live subscription running.
   `scheduleCancellation()` already gets this right by throwing.

#### Refunds are not "later", they are "not designed yet"

Refunds look adjacent but share nothing with the above. `Box\Mod\Invoice\Service::refundInvoice()`
is *purely bookkeeping*. The `invoice_refund_logic` setting has three values —
`manual` (the default), `credit_note` and `negative_invoice` — and all three only
write local records. The `manual` branch is explicit about it:

```php
$this->di['logger']->warning('Refunds are managed manually. No actions performed.');
```

No gateway has a refund method and no core code looks for one. Money never moves.
An admin refunds in the provider's dashboard, then records it here.

So the earlier recommendation — "define the interfaces now, implement later" —
was wrong, and wrong by this document's own argument. §1.2 is a catalogue of what
happens when interface surface is defined ahead of a caller: `Payment\Invoice`,
`Invoice\Buyer`, `Invoice\Item`, `Invoice\Subscription` and the `Transaction`
value object are all dead precisely because they were written for a path nothing
took. Adding `SupportsRefunds`, `RefundRequest` and `RefundResult` with no
implementation, no caller and no admin UI would repeat that mistake in miniature.

Real outbound refunds are a feature, not a refactor. They need a `gateway` value
for `invoice_refund_logic`, partial-amount handling, an admin flow, failure
states, and — the part most likely to be got wrong — reconciliation with the
inbound `EventKind::Refunded` webhook, so that a refund initiated through core
isn't counted twice when the provider then announces it. The `(gateway, reference)`
dedupe in §3.3 is the mechanism, but it has to be designed deliberately.

**Leave refunds out entirely.** Capability interfaces are additive by
construction — that is the point of §3.2 — so adding `SupportsRefunds` later
breaks nothing and changes no existing gateway. There is no cost to waiting and a
demonstrated cost to guessing.

### 3.6 The descriptor lives in `extension.json`

**Decision: the manifest, not PHP.** The earlier recommendation here was PHP, on
two arguments — translatable labels and `required_when` needing logic. Reading the
tree shows both are false.

The evidence, across all 18 bundled extensions (4 gateways, 8 registrars, 6
managers):

- **Every descriptor is a static literal array.** Not one builds a field list at
  runtime, consults `$this`, loops, or branches. There is nothing PHP is doing
  that JSON cannot.
- **`required_when` is already pure data.** `['enabled' => true, 'test_mode' => false]`
  is consumed by a Twig macro (`compute_field_required` in
  `src/themes/admin_default/html/macro_functions.html.twig`) and mirrored into
  `data-required-when-*` attributes for the client-side script. No PHP evaluates it.
- **Labels are not translated today, by either route.** `getConfig()` never calls
  `__trans()`, and the admin template renders `{{ element[1].label }}` with no
  `|trans`. Settings labels for every gateway, registrar and manager are
  untranslatable. That is a live bug, and it is orthogonal to this decision —
  whichever side won, it needs fixing.

What settles it is a property PHP cannot offer: **core can render the settings
form without executing the extension.** With per-extension `vendor/` bundles,
loading a class to ask it its name means autoloading the class and potentially
chaining in a whole SDK — to draw a label and a logo. `ExtensionLocator::listInstalled()`
and `ServicePayGateway::getAvailable()` already want to enumerate
installed-but-unconfigured extensions cheaply. For third-party code the same
property is a security one: an inert manifest cannot run.

A manifest also gets install-time schema validation with real errors, instead of
`new $class($config)` inside a try/catch.

#### The shape already exists

Two competing form schemas are in the tree right now. Gateways and registrars use
a positional tuple keyed by field name:

```php
'email' => ['text', ['label' => '…', 'validators' => ['EmailAddress']]],
```

Server managers use a list of field objects — and it is strictly better:

```php
['name' => 'username', 'type' => 'text', 'label' => 'Username',
 'placeholder' => '…', 'required' => true, 'secret' => true],
```

Explicit ordering, no positional tuple, and a `secret` flag that gateways badly
need and don't have — Stripe's secret keys and webhook signing secrets currently
render as plain text inputs. Take the manager shape, put it in the manifest, and
the three method names (`getConfig()` twice, `getForm()` once) collapse into one
schema shared by all three extension types.

```json
{
  "id": "Stripe",
  "type": "gateway",
  "name": "Stripe",
  "description": "…",
  "version": "1.0.0",
  "api": 1,
  "logo": { "file": "stripe.png", "width": "65px", "height": "30px" },
  "settings": [
    {
      "name": "api_key",
      "type": "password",
      "label": "Live Secret Key",
      "secret": true,
      "required_when": { "enabled": true, "test_mode": false }
    }
  ]
}
```

`description` stops being duplicated between `getConfig()` and the manifest.
Capability flags do not appear at all — they became interfaces in §3.2.

#### What stays in PHP

Shape in the manifest, semantics in PHP. Cross-field rules JSON can't express —
"at least one of the live/test key pairs must be complete", probing that a
credential actually works — belong on an optional capability:

```php
interface ValidatesSettings { public function validateSettings(array $settings): void; }
```

If a gateway ever needs runtime-populated options (choose from your connected
accounts), add an additive `SuppliesSettingsOptions` capability then. Nothing
needs it now, so don't build it.

#### Consequences

- `Gateway` becomes a one-method interface (§3.1).
- Translation needs solving for JSON labels — the extractor scans PHP and Twig,
  not arbitrary JSON. This is new work that the PHP option would not have
  required, and it is the one real cost of this decision. It is bounded: a
  documented convention plus an extractor pass over `extension.json` files.
- Registrars and managers should move to the same manifest schema. That is a
  separate change, but the schema must be designed for all three now, not
  retrofitted.

### 3.7 Lifecycle hooks — yes, but at the extension level

The earlier recommendation ("no — nothing needs them") was answering the wrong
question. It asked whether the four bundled *gateways* need hooks today. The right
question is whether the extension system needs them, and the tree answers yes.

#### FOSSBilling already has module lifecycle hooks

`FOSSBilling\Module` has had `install()`, `uninstall()` and `update()` all along,
dispatched — inevitably — by `method_exists`:

```php
if (method_exists($s, 'install')) { $s->install(); return true; }
```

They are used. `Serviceapikey::install()` creates its `service_apikey` table and
`uninstall()` drops it; `Custompages` and `Massmailer` have install hooks too. So
this is not a speculative capability — it is an existing, exercised part of the
extension system that gateways, registrars and managers simply don't get.

#### The activation path is asymmetric, and versions aren't even recorded

`Box\Mod\Extension\Service::activate()` branches on type:

```php
if ($extensionType instanceof ExtensionType) {        // gateway / registrar / manager
    $this->installExtensionDependencies($extensionType, (string) $ext->getName());
} elseif ($ext->getType() === ExtensionManager::TYPE_MOD) {
    $mod = $this->di['mod']($ext->getName());
    $this->installModule($ext);
    $ext->setVersion($manifest['version'] ...);       // ← only modules
    ...
}
```

Two gaps fall out:

1. **No install hook** for the three types this document is about.
2. **`setVersion()` is never called for them.** `extension.json` carries a
   `version`, and nothing persists it. Without a stored version there is nothing
   to compare against, so an upgrade hook is not merely absent — it is currently
   impossible even in principle. Adding the manifest in §3.6 makes that gap more
   glaring, not less.

`deactivate()` is worse: gateways fall through to `default: break`. The `Extension`
row is deleted, but the `pay_gateway` row and its stored API credentials survive.
Deactivating Stripe leaves its secret key in the database. That is a real
defect an uninstall hook is the natural place to fix.

#### What gateways would actually do with them

- **Register the webhook at the provider.** Today an admin must open the Stripe
  dashboard, create an endpoint by hand, copy the signing secret back into
  FOSSBilling, and repeat it for test mode. This is the single most valuable
  thing a lifecycle hook buys, and it is squarely gateway-shaped work.
- **Deregister it on removal**, and clear stored secrets.
- **Verify credentials actually work** — an outbound check that doesn't belong in
  form validation.
- **Migrate settings keys across versions**, which needs the stored version above.

#### Shape

Do it once, generically, in `FOSSBilling\Extension\Contract` — not per extension
type. Registrars and managers need it identically, and modules already have a
worse version of it that this should eventually replace:

```php
interface HasLifecycle
{
    public function activated(): void;
    public function deactivated(): void;
    public function upgraded(string $fromVersion): void;
}
```

Detected with `instanceof`, like every other capability. Prerequisite: persist
`setVersion()` from the manifest for non-module extension types — small, and
needed regardless.

Two things to get right that Blesta gets clumsily:

- **Don't leak internals into the signature.** Blesta's
  `uninstall($gateway_id, $last_instance)` hands the extension a database id and
  an instance count. Ours should take neither.
- **Extension-level and instance-level are different events.** FOSSBilling allows
  several `pay_gateway` rows per adapter (`ServicePayGateway::copy()`), each with
  its own credentials. "The Stripe extension was activated" and "a Stripe gateway
  was configured" are not the same thing — and webhook registration is the
  *instance*-level one, since each configured gateway needs its own endpoint and
  secret. Blesta's `$last_instance` flag is what you get from conflating them.

So: **extension-level hooks now**, since `activate()`, `deactivate()` and
`uninstall()` are existing call sites standing empty for three extension types.
**Instance-level hooks when the webhook-registration work is actually done** —
that is a feature with a design of its own, and §3.5 is the argument for not
guessing its shape in advance.

## 4. The ClientBalance question

`ClientBalance` is the only gateway that needs `clientBalance()` and
`currentClientId()`, and it is the reason half of the current `Context` exists. It
is not a payment service provider: it moves money that is already inside
FOSSBilling, has no webhook, no external call, and no credentials.

Modelling it as an extension forces core to expose credit operations across the
extension boundary so that one bundled extension can call back in. **Paying from
account credit should be a core payment method, not a gateway.** Doing that keeps
`Context` at four methods and removes the strongest argument for a wide one.

This is a decision to make before writing the interface, not after.

**Decision taken and implemented.** `src/extensions/gateways/ClientBalance/` is
deleted. Paying from account credit is now a direct, client-authenticated API
call — `client/invoice/pay_with_credit` (`Box\Mod\Invoice\Api\Client::pay_with_credit()`,
backed by `Box\Mod\Invoice\Service::payInvoiceFromClientBalance()`) — rather than
a webhook round trip through the guest IPN endpoint. Core already did the
settlement work in `Service::tryPayWithCredits()`; the new entry point adds the
ownership check (the client API framework enforces it, closing the gap the old
guest-endpoint-plus-gateway-check path left open), the deposit-invoice refusal,
and the balance check, then sweeps any remaining credit across the client's
other unpaid invoices exactly as `Context::payClientInvoicesWithCredits()` did.
No `Transaction` row is created — credit payments only ever recorded themselves
in the `client_balance` ledger; the row the gateway used to create existed to
satisfy IPN plumbing, not for reporting. `UpdatePatcher::patch100()` deletes any
`pay_gateway` row left behind by installs that had the gateway enabled.
`Context` was trimmed to the three methods in §3.4 below.

## 5. Cost

| Work | Size |
| --- | --- |
| Delete the dead branch (§1.2) and `_parseIpnAndApprove` | net deletion |
| New contract types | ~10 small readonly classes |
| Core: `checkout()` + one webhook entry point with shared dedupe | moderate |
| `Custom` | small — `Checkout::html()` plus a template render |
| `PayPalEmail` | moderate — its field builders map onto `Checkout::form()` |
| `ClientBalance` | rewrite, or promote to core (§4) |
| `Stripe` | large, but this is the change that makes it tractable: most of its 63 container accesses are bookkeeping that moves to core |

Third-party gateways break. That is consistent with the no-shims decision (D-NS)
and is the last good moment to do it — before the extension API is published.

## 6. Open decisions

1. ~~**ClientBalance: gateway or core payment method?**~~ **Settled: core.**
   Implemented — see §4.
2. ~~**Descriptor in PHP or `extension.json`?**~~ **Settled: `extension.json`.**
   Every descriptor in the tree is already static, `required_when` is already
   pure data, and labels are untranslated either way. See §3.6.
3. ~~**Typed `SettingsField` objects, or keep the nested array?**~~ **Folded into
   #2.** The manifest is the schema; the server managers' field-object shape
   (`name`/`type`/`label`/`placeholder`/`required`/`secret`) is what it adopts.
   Cross-field validation stays in PHP behind a `ValidatesSettings` capability.
4. ~~**Refunds and subscription cancellation now or later?**~~ **Settled: split
   them.** Subscription cancellation is already a shipping feature found by
   `method_exists` in three places, so it is typed now as two interfaces.
   Refunds are local bookkeeping only and are left out entirely rather than
   stubbed. See §3.5.
5. ~~**Lifecycle hooks?**~~ **Settled: yes, at the extension level.** Modules
   already have them; gateways, registrars and managers don't, and their
   manifest `version` is never even persisted. Extension-level hooks now
   (`activate()`/`deactivate()`/`uninstall()` are existing empty call sites);
   instance-level hooks with the webhook-registration feature. See §3.7.

## 7. Implementation notes (contract + `Custom`, landed)

The contract from §3 and `Custom`'s migration landed together. Four things
were settled or corrected while doing it, beyond what §1–§6 anticipated.

### `CheckoutUrls` has three fields, not four

§3.1 left this open. `redirect_url` (`ServicePayGateway::getCallbackRedirect()`)
turned out to be `notify_url` with `redirect=1`, `invoice_id` and
`invoice_hash` appended — and `CheckoutRequest::$invoice` is never null, unlike
the `?Model_Invoice $model` the old URL builders had to allow for. So for a
Gateway-typed adapter there's no case where the redirect-capable URL and the
notify URL differ: `CheckoutUrls::$callback` is simply
`getCallbackRedirect()`'s result. `getCallbackUrl()` (no redirect flag) is
still used directly for the admin-facing "IPN Callback URL" display field,
which has no invoice in scope.

### `InvoiceView` was missing the fields `Custom`'s templates actually use

§3.4 landed `InvoiceView` with buyer/identity fields but no `total`,
`subtotal`, `tax` or line items. `Custom`'s old `getHtml()` handed its
merchant-authored template the *full* `toApiArray($invoice, true)` output, so
an admin's payment instructions can reasonably say
`Wire {{ invoice.total }} {{ invoice.currency }}`. Omitting totals would have
been a silent regression for exactly the one gateway this change ships.
`InvoiceView` now also carries `total`, `subtotal`, `tax` and `lines`,
populated from `toApiArray()` rather than recomputed, in
`Box\Mod\Invoice\Service::buildInvoiceView()`.

### `Custom::processTransaction()`/`isIpnValid()` were already dead — not migrated to `HandlesWebhooks`

The plan for `Custom` assumed its manual "mark as paid" flow would either map
onto `HandlesWebhooks` or need an explicit "doesn't map cleanly" call. It
turned out to be neither: `Box\Mod\Invoice\Service::markAsPaidByAdmin()`
already special-cases `$payGateway->getGateway() === 'Custom'` directly,
creates the audit-trail `Transaction` row itself, and settles the invoice by
calling `markAsPaid()` — it never calls into the adapter at all. Meanwhile
`ipn.php` hardcodes `'source' => 'ipn'`, so `Custom::isIpnValid()` (which only
ever accepted `source === 'admin'`) could never see a request it would
accept. `processTransaction()`/`isIpnValid()` were therefore unreachable
before this change, for a different reason than `getTransaction()` (§1.2) —
not superseded by a branch that runs first, but bypassed by a core method
that never asked. They were deleted rather than ported. `Custom` implements
no webhook capability; `HandlesWebhooks` still gets its caller from the
`ipn.php` → `ServiceTransaction::processTransaction()` dispatch added in this
change (§3.3), ahead of any gateway implementing it, same as planned.

### A second, larger dead branch, found next to the one §1.2 named

Deleting the branch at `Service.php:1791` traced into
`ServiceTransaction::process()` / `_parseIpnAndApprove()` and everything only
reachable from them (`_debit`, `_refund`, `_subscribe`, `_unsubscribe`,
`_isProcessed`, `_markAsProcessed`, `_validateApprovedTransaction`,
`hasProcessedTransaction`). Nothing in the tree calls `process()` — the live
`transaction_process`/`transaction_process_all` API endpoints call
`preProcessTransaction()` → `processTransaction()`, a different method that
does not go through `process()`. `_parseIpnAndApprove()` was also not merely
unreachable but broken: it called `$adapter->isIpnValid($ipn, $mpi)` (two
args) and `$adapter->getTransaction($ipn, $mpi)`, and none of the three
bundled gateways define either signature, so an accidental call would have
been an uncaught `Error`, not a handled exception. This whole subsystem was
deleted along with the branch that fed it. Its replacement is the
`settleWebhook()`/`settlePaymentEvent()` family added to `ServiceTransaction`
for `HandlesWebhooks`, which also fixes the dedupe bug the old code had:
`hasProcessedTransaction()` matched `findOneProcessedByTxnId()` globally
across every gateway, not per `(gateway, reference)` as §3.3 specifies. The
new dedupe is scoped with `findOneByTxnIdAndGatewayId()`.

### `cancelAtGateway()` now throws from a wider blast radius than just `subscription_update`

The fix in §3.5 makes `cancelAtGateway()` throw instead of silently no-op.
That method is reached from two places: `ServiceSubscription::update()`
(a direct, single-subscription admin action — the case §3.5 describes) and
`cancel()`/`cancelForOrder()`, which is called automatically whenever
`Box\Mod\Order\Service::cancelFromOrder()` cancels an order that has an
active subscription. This change makes cancelling *that order* now throw and
abort if the subscription's gateway doesn't implement `CancelsSubscriptions`
— which, on the current bundled set, is `Custom` and `PayPalEmail`, i.e. the
common case for small installs, not the exception. The alternative — catching
and logging inside `cancelForOrder()` so order cancellation always completes
— was not implemented, to stay faithful to §3.5's explicit instruction to
throw and to give that behaviour a test. This is flagged rather than decided:
whether order cancellation should be allowed to fail loudly over a
subscription FOSSBilling was never able to cancel remotely in the first
place is a product decision, not an interface-design one.
