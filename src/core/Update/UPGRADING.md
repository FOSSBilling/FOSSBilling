# Built-in upgrades

Use the administration update screen after taking a backup. The updater downloads
and verifies the release archive, stops new application work, and waits for active
requests and cron jobs before replacing application files. If work does not drain
within the wait limit, no files are extracted; retry once that work finishes.
Requests arriving during extraction receive the maintenance/retry response and
restart with a fresh bootstrap after the update. Cron can retry on its next run.
There is no routine requirement to stop cron, run SQL, reset payment claims or
manually purge support files between coordination-capable releases.

After extraction, a request running the newly installed code applies pending
configuration patches, database patches and additive schema synchronization before
normal application/session queries resume. The finalization screen retains its
review/completion step. A schema-sync failure keeps finalization pending and
maintenance enabled; correct the reported problem and retry finalization.
Interrupted patches are designed to resume on retry. This does not roll back a
partially extracted archive: restore a backup or replace the application files
with a complete verified release if extraction itself was interrupted.

The PHP ZIP extension must be enabled. The existing `data/uploads` directory
under the application root must be writable for the bootstrap coordination lock,
including installations using a custom `path_data`. The configured data directory
must also be writable for finalization state and its lock. Do not delete or
replace either runtime lock file while application processes are running.

Every web, CLI and cron process must load the application bootstrap and share the
same lock storage with working advisory-lock semantics. Built-in coordination
does not cover direct database scripts or multi-node installations whose lock
files are independent. Those installations and manual file replacement require a
coordinated rollout appropriate to their infrastructure.

## Transition from older releases

Request and cron coordination was merged into `0.8-next` in
[#4699](https://github.com/FOSSBilling/FOSSBilling/pull/4699). The complete Phase
B/C changes target `main` for 0.9.0. Install a published coordination-capable
maintenance release before moving to that release.

Processes already running code from an older release cannot join the new lock
protocol retroactively. During the first transition, let those processes finish;
use the installation's existing maintenance controls if necessary. Subsequent
built-in updates drain participating requests and cron automatically.

Historical SQL/config upgrade tests cover 0.7.2, 0.8.1 and 0.8.9 on MySQL/MariaDB,
including interruption and retry. Portable schema and concurrency tests also
exercise SQLite and PostgreSQL. Historical MySQL-only data transformations remain
MySQL/MariaDB-only; this upgrade does not provide cross-database conversion.

## Interrupted extraction recovery

Before changing application files, the updater records `.update-extraction-pending`
in the application root. It removes this marker only after extraction succeeds and
finalization state is saved. An exception or killed updater leaves the marker in
place; it does not expire. New web and cron requests stay blocked before Composer
loads, so they cannot execute a partially replaced release.

An archive rejected before any writes leaves the application available. After an
interrupted write, stop the updater and restore a complete verified release or
backup, then remove `.update-extraction-pending` before retrying finalization.
Do not clear this marker merely to bypass the recovery check. Release archives
must exclude it, alongside the transient update lock and other runtime state.
