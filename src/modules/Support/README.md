# Support attachment recovery

The support cron automatically reconciles attachments with recovery markers. Older unmarked orphan files require a separate maintenance step: their age and an absent database row cannot prove that a legacy upload has finished.

Before cleaning those files, upgrade or stop **every** legacy attachment writer on every application node, including web and background workers. Wait for all of their in-flight requests and database transactions to finish, and keep those legacy writers stopped during cleanup. Workers running the current marker-based upload code may continue.

Generate a read-only inventory (this does not require a drain):

```sh
php src/console.php support:files:cleanup-legacy --inventory > legacy-files.json
```

Review the relative client/token paths, sizes, modification times and SHA-256 fingerprints. Keep only the entries approved for deletion, preserving the JSON format. After draining every legacy writer, run:

```sh
php src/console.php support:files:cleanup-legacy --manifest=legacy-files.json --legacy-uploads-drained
```

Cleanup rechecks metadata and recovery markers under the owner lock and skips files whose size, timestamp or content changed. Every removed path is logged. Archive the reviewed manifest alongside the logs. Generate and review another inventory for the next batch; rerunning the same manifest is harmless. The flag confirms that the handoff is complete; the command cannot verify other nodes. It removes at most 500 eligible files per run. Repeat if it reports 500 removals. The command retains referenced files, files with recovery markers, and files less than 24 hours old. If it fails, resolve the error and rerun it; prior removals are not rolled back.
