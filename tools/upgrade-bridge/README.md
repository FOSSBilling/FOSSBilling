# Phase B/C pull request and release plan

All preparation remains local. No PR has been published, and nothing has been
pushed or deployed.

## Targets

- `codex/phase-bc-main` targets `main`, integrating the complete payment, support
  concurrency and upgrade automation changes with origin/main at `5ec8f29067`.
  This candidate is under integration validation; do not label it merge-ready
  until its complete test and concurrency checks pass.
- `codex/update-coordination-0.8-next` starts at `da1f48896d`. `0.8-next` tracks
  the next maintenance release, currently planned as 0.8.10, including its other
  planned changes. The candidate backports request/cron coordination, private
  update downloads, safe extraction and handoff to newly installed code. It adds
  no Phase B/C payment or support schema or feature changes.

The frozen `0.8.9-coordination.patch` is a historical regression fixture. It is
not the proposed PR against 0.8-next and is not an installation instruction.
Users should install normal published releases using the built-in updater.

## Transition decision

Prefer reviewing and merging the small coordination backport through the normal
0.8-next process, alongside the other planned 0.8.10 changes. Then target the full
Phase B/C feature set at main and the appropriate next release. The maintenance
release need not be a separate bridge-only release, and no permanent release API
routing contract is introduced by these candidates.

The first transition has a real boundary: software already running from older
releases cannot retroactively acquire the new request lock. A maintenance release
helps subsequent updates, but elapsed rollout time alone does not prove that an
unbounded old cron job has finished. Release notes must explain how installations
establish that old workers have ended, using their existing maintenance controls
when needed. Do not promise automatic draining of unregistered old workers.

If the backport is rejected for maintenance-branch risk or scheduling, ship the
protocol and complete changes in 0.9.0, with that first-transition limitation
explicit in its upgrade notes. This is a project release decision, not an
outstanding migration on a centrally managed production installation.

## Packaging and operation

Require native PHP ZIP, writable application uploads and configured data paths,
and shared lock storage with working advisory locks. Exclude `.update-lock`,
`data/uploads/.update-runtime.lock`, `data/update-finalization.*`, `config.php`
and `config.old.php` from archives. Never replace live lock files. Multi-node
installations with independent storage need infrastructure-level coordination.
Interrupted extraction requires a complete verified release or backup restore;
finalization retries database/configuration work after successful extraction.

Payment ambiguities require staff reconciliation of the existing provider record,
not another charge or blanket reset. Old unmarked attachment cleanup remains an
optional reviewed manifest operation after legacy upload writers have ended.
