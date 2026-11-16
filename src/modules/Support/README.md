# Support attachment recovery

The support cron automatically reconciles attachments with recovery markers. Older unmarked orphan files require a separate maintenance step: their age and an absent database row cannot prove that a legacy upload has finished.

Before cleaning those files, upgrade or stop **every** legacy attachment writer on every application node, including web and background workers. Wait for all of their in-flight requests and database transactions to finish, and keep those legacy writers stopped during cleanup. Workers running the current marker-based upload code may continue.

Then run `php src/console.php support:files:cleanup-legacy --legacy-uploads-drained`. The flag confirms that the handoff is complete; the command cannot verify other nodes. It removes at most 500 eligible files per run. Repeat if it reports 500 removals. The command retains referenced files, files with recovery markers, and files less than 24 hours old. If it fails, resolve the error and rerun it; prior removals are not rolled back.
