# Interrupted saved payments

Open **Transactions → Payment recovery** to see unresolved saved-payment attempts and processing transactions. The queue shows up to 500 oldest records in each category. A pending attempt may be a legitimate bank debit still clearing.

New saved charges commit an immutable attempt ID before contacting the gateway, and record a returned payment reference before local settlement. Stripe and PayPal REST receive this ID as part of their provider idempotency key. Unresolved attempts block staff, client and automatic charges. They are never retried by sending another charge, even after provider idempotency retention expires. Legacy PayPal reference transactions also receive an attempt-specific `MSGSUBID`, limited to 38 bytes; their transaction details can also be read during recovery.

Stop the original worker before reconciliation. **Check gateway** only reads Stripe PaymentIntents, PayPal REST captures, or legacy PayPal transaction details and verifies invoice ownership, gateway metadata where available, and currency. If a reference was lost, staff can supply one from the provider dashboard. A missing reference or unsupported lookup means **unknown**, not failed. Network errors do not release the attempt.

**Reconcile existing attempt** processes the existing payment through the normal settlement path, or records a definitive failure. Existing transaction records are reused. An interrupted transaction must first be released on its transaction page, after checking its worker and gateway outcome. Pending bank debits stay blocked until they settle or fail. Recovery never initiates another charge.

If the provider confirms that no payment exists and there is no recorded reference, staff may explicitly record that outcome with a reconciliation note. The attempt retains the staff ID, note and resolution source in the database. A later charge gets a new attempt ID. Automatic retries remain held after an ambiguous outcome; staff can deliberately charge after resolving it.

The lock prevents recovery overlapping a live charge worker; attempt-scoped conditional updates reject late results after resolution. Neither mechanism can undo a request already sent to a provider. Never declare “no payment” based only on elapsed time or a missing local record. For unknown outcomes, the provider dashboard or provider support remains the authority. Existing transactions created before this journal still require the transaction-page recovery workflow.
