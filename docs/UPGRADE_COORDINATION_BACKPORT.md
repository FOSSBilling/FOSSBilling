# Upgrade coordination backport for 0.8-next

This coordination-only backport targets `0.8-next`, which tracks the next
maintenance release, currently planned as 0.8.10, with its other planned changes.
It adds a shared bootstrap request/cron gate and exclusive updater extraction,
private temporary downloads, archive path/runtime-state protection, session
release before draining, and finalization by the newly installed application.
It adds no Phase B/C payment or support schema/features.

Native PHP ZIP and writable `data/uploads` are required. All application workers
must share working advisory locks. Archives exclude installation configuration,
update markers and live lock/state files. Do not delete live locks. A failed or
interrupted extraction requires restoring a complete verified release or backup;
configuration and database finalization retries after successful extraction.

Older already-running processes cannot retroactively acquire the new protocol.
This backport prepares subsequent upgrades to coordinate active work, but does
not prove an indefinitely running older cron job has ended. Release notes should
explain that first-transition boundary and the existing maintenance procedure
where older work cannot otherwise be confirmed finished. A separate mandatory
bridge release and permanent release-service routing are not required.

If the maintenance backport is unsuitable, include coordination and Phase B/C in
0.9.0 with the same first-transition limitation. Users receive normal application
releases; they are not asked to apply patches or run manual schema migrations.

Regression tests cover active request/cron draining, exclusion of new work during
extraction, bootstrap freshness, stale markers, safe handoff, protected runtime
files, and cache deletion before/during download. The maintenance PR should run
the full unit and module suite with its own locked dependencies.
