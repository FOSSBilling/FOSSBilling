# Built-in upgrades

Use the administration update screen after taking a backup. The updater downloads
and verifies the release archive, stops new application work, and waits for active
requests and cron jobs before replacing application files. If work does not drain
within the wait limit, no files are extracted; retry once that work finishes.
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

The release path must first provide the coordination intermediate release.
Already-running processes from earlier releases cannot be enrolled retroactively.
The next release must only be enabled after those old processes have ended; a
fixed waiting period cannot prove this for an indefinitely running old cron job.
See the repository's `tools/upgrade-bridge/README.md` for the tested backport and
release-routing requirements. The bridge and routing must be published as release
work before this can be described as the available upgrade path for users.

Historical SQL/config upgrade tests cover 0.7.2, 0.8.1 and 0.8.9 on MySQL/MariaDB,
including interruption and retry. Portable schema and concurrency tests also
exercise SQLite and PostgreSQL. Historical MySQL-only data transformations remain
MySQL/MariaDB-only; this upgrade does not provide cross-database conversion.
