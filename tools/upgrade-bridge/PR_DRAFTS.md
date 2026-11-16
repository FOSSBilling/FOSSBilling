# Local pull request drafts

These drafts describe local branches only. Do not publish or merge the main
candidate until its integration validation is complete.

## Main

Target: `main` at `5ec8f29067`  
Branch: `codex/phase-bc-main`  
Suggested title: Add saved payment recovery, support workflows and coordinated upgrades

Interrupted payment workers and concurrent support operations can leave uncertain
provider outcomes, duplicate effects or files outside their intended lifetime.
Add payment claim fencing, durable charge/refund recovery and staff reconciliation,
saved payment methods and pending bank-debit settlement, plus support ticket locks,
close-event delivery and recoverable attachment cleanup. Coordinate built-in
updates with participating requests and cron, hand finalization to newly installed
code, and test historical upgrades with frozen release fixtures.

Integrate the upstream gateway receipt and local-completion model rather than
maintaining two independent settlement paths. Retain explicit staff recovery for
ambiguous provider operations. Preserve main's secret delivery and immutable
timestamps. Keep published migration numbers 140–145 and move the unpublished
Phase B/C migrations to 146–160.

Validation status is recorded in the local review report; this draft is not a
claim that every check has passed. The original `local/clientfence-backports`
branch through `3ce2ad35f` remains intact.

## Maintenance backport

Target: `0.8-next` at `da1f48896d` (next release currently planned as 0.8.10)  
Branch: `codex/update-coordination-0.8-next`  
Local commit: `8fe59b910`  
Suggested title: Coordinate built-in upgrades with active requests and cron

Built-in extraction can replace application code while another request or cron
job still uses it. Register bootstrap readers and drain them before extraction,
reject new work during replacement, release the updating session before waiting,
use private archives outside cache, and hand finalization to newly installed code.
Protect installation configuration and runtime lock/state files during extraction
and packaging. This backport contains no payment/support feature migrations.

Validation: full Unit and Modules suite, 3427 tests / 13083 assertions; focused
coordination/updater tests, 24 tests / 91 assertions; PHPStan and PHP 8.3 syntax
checks pass. Native PHP ZIP is required.

## Release notes and fallback

Review the backport through the normal maintenance release process alongside the
other planned 0.8.10 work. If unsuitable, include coordination with Phase B/C in
0.9.0. No standalone mandatory bridge release or permanent release-API routing
contract is introduced. Already-running legacy processes cannot retroactively join
the lock protocol: explain this first-transition boundary and the existing
maintenance procedure for installations that cannot establish old workers ended.
Users install normal releases through the updater, without manual schema scripts
or blanket payment-claim resets. See `src/core/Update/UPGRADING.md` for operation.
