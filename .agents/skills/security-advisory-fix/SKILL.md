---
name: security-advisory-fix
description: Design, implement, test, and ship the actual code fix for a security advisory that has already been verified as real (typically by security-advisory-triage) against this repo. Use this whenever the user asks to fix, patch, remediate, harden, or "build a comprehensive fix for" a confirmed vulnerability or GHSA, or says something like "now let's fix it" right after a triage/verification step. Don't wait for the user to ask for "the process" by name; if a vulnerability is confirmed and they want it shipped, this skill is almost certainly what they want. Companion to security-advisory-triage, which verifies and writes up the advisory; this one builds and ships the fix.
---

# Security advisory fix

Companion to `security-advisory-triage`. That skill answers "is this real,
and what does the advisory say?" This one starts once the answer is yes and
covers everything from there through a merged, CI-green fix: designing the
fix, implementing it, and getting it through review.

Like triage, this is collaborative and staged, not something to run to
completion and hand over at the end. The design stage in particular involves
real judgment calls (how far to change existing behavior, whether a
suggested response masks the bug or just the symptom), and those are worth
putting in front of the user as they come up rather than deciding silently
and revealing the decision only in the diff.

## Carry the disclosure caution forward

If the advisory is still unpublished, the same rule from triage applies to
everything you produce here, not just the write-up. No GHSA/CVE ID, and
nothing specific enough to reconstruct the exploit, in branch names, commit
messages, PR titles or bodies, or CI run titles. If the harness auto-named
your branch after the advisory ID (this happens: a task prompt that mentions
the GHSA ID can get echoed straight into an auto-generated branch name),
rename it before your first commit, not after. Use a plain, symptom-based
name instead, matching how this repo's own fix PRs are named
(`fix/template-exists`, `fix/client-signup-enumeration`: what broke, not
what the finding is called).

## Stage 1: Design the fix

Resist writing code before this stage produces an actual decision. The
temptation is strong, especially when the vulnerable lines are obvious, but
"obvious lines" and "correct fix" are different problems. For anything
beyond a one-line validation gap, there's real design space worth surfacing
to the user rather than picking silently.

### If exploration contradicts the report, stop before designing anything

Triage should have confirmed the report already, but exploring the actual
code for a fix sometimes turns up a mismatch triage didn't catch: the
described mechanism doesn't exist as written. Don't quietly retarget the fix
at whatever you did find and carry on as if that's what was reported. Stop
and put the discrepancy in front of the user: here's what the report claims,
here's what the code actually does. Two shapes of mismatch are both common
enough to expect, not just one:

- **Something adjacent and real is there instead.** Say what it is, and let
  the user decide whether that's what you should fix, whether it's worth a
  separate report, or whether the original claim needs another look.
- **Nothing is actually wrong.** The path the report describes is already
  correctly guarded, sometimes by a fix that landed for an unrelated reason.
  Trace and quote the exact guard so the user can verify your reading rather
  than just taking "looks fine" on faith, and treat closing the report as a
  real outcome, not a failure to find something. It's often still worth
  asking whether the gap is covered by a regression test, and adding one if
  not, so this stays provably true rather than just currently true.

Silently substituting a different fix for a different bug, or silently
closing a report as unfounded without showing your work, are both exactly
the kind of decision this skill exists to make visible, not the kind to make
on your own.

### Look for the pattern already in the codebase

Before inventing a fix, check whether this exact class of problem was
already solved somewhere else in the app. A codebase this size usually has
prior art: a sibling endpoint that handles the same kind of sensitive
comparison, timing concern, or disclosure risk correctly, even if the
vulnerable code never got the same treatment. Grep for the mechanism, not
just the symptom. If the bug is a timing-safe comparison gap, look for other
places doing timing-safe comparisons; if it's an enumeration oracle, look
for other endpoints that already had to solve enumeration. Reusing an
established, already-reviewed pattern is almost always better than
designing a new one from scratch, and it gives you a concrete thing to
propose instead of an abstract description of what you're about to build.

### Lay out real options, then ask

Once you understand the vulnerable mechanism and what prior art exists,
identify the genuinely distinct strategies for fixing it. Not a strawman
next to the obviously-correct answer, but options that trade real things
against each other: how much existing behavior or API contract changes, how
completely the underlying issue closes versus just becoming harder to
exploit, how much UX or performance cost it adds. Recommend one, but put the
real choice to the user with an explicit question (`AskUserQuestion` in
Claude Code) rather than deciding and presenting the result as a fait
accompli. This matters most exactly when the
strongest fix would change public behavior other things might depend on.
That's a call the user should get to make deliberately, with the tradeoff
stated plainly, not discover after the fact in a diff.

### Check whether the fix closes the mechanism, or just the reported instance

The first fix that comes to mind usually addresses exactly the instance the
report pointed at, and it's worth asking whether that's the same thing as
closing the underlying mechanism. The two come apart in different ways for
different bug classes, so what to check depends on what kind of bug this is:

- For a **disclosure/enumeration oracle**, the report usually names one
  channel (an error message, say). Walk through the others explicitly: does
  the response body still differ, does a returned value's type or shape
  leak something even once the message is gone (a real ID sitting next to a
  masked branch's placeholder is itself a signal), do HTTP status, headers,
  cookies/session state, or timing still diverge between the two cases. A
  fix that closes the loudest channel can leave a quieter one wide open, and
  because it's quieter it's easy to miss unless you look on purpose.
- For a **validation/injection/traversal gap**, the report usually names one
  call site. Check whether the same unvalidated value reaches the same
  dangerous sink through any other path, and whether the fix belongs at the
  point of use (so every current and future caller is covered) rather than
  only in the one entry point that happened to get reported.
- For a **broken access control / ownership-scoping gap** (one account
  reaching another's data by id), check every sibling endpoint that fetches
  the same kind of entity by id for the same ownership predicate, not just
  the reported one. This is a common enough bug class in this app's history
  to expect it by default, not just when a report happens to name it.
- For other bug classes, ask the equivalent question in whatever terms
  actually apply: what would still let the underlying issue happen even
  after this specific report's reproduction case stops working?

This still applies even when Stage 1 concluded there's no code fix to write,
only a regression test to add (see above): ask the same question about the
test instead. Does it actually prove the general protection holds, or only
that this one reported case happens to pass?

If you find something the obvious fix doesn't cover, treat it as its own
decision. Surface it and ask, same as the primary strategy choice, rather
than silently folding a second significant design change into the same
commit as the first fix.

## Stage 2: Implement

Once the design is settled:

1. **Write the fix**, reusing whatever established pattern Stage 1 found
   where possible instead of a bespoke implementation.
2. **Find every consumer of anything you changed the contract of.** If a
   return type, response shape, or error behavior changed, grep broadly for
   callers, not just obvious application code but every test framework this
   repo uses that might assert on the old contract. That can include
   PHP/Pest unit tests, PHP E2E tests that hit a live running instance, and
   Playwright specs, and they don't all share helper code, so a fix in one
   doesn't mean the equivalent fix landed in the others. Update each one you
   find.
3. **Write a regression test** for the vulnerability itself, not just for
   the refactor.
4. **Run the full local suite before ever pushing**: `composer test`
   (Unit + Modules), `composer phpstan`, and CS-Fixer. For CS-Fixer, pass
   `--config=.php-cs-fixer.dist.php` explicitly:
   `src/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run
   --diff <files>`. Without it, CS-Fixer tries to locate the project's config
   by walking up from a single given path, which is ambiguous once you pass
   more than one, so it refuses outright ("For multiple paths config
   parameter is required") rather than guessing which path's config to use.
   Passing `--config` explicitly sidesteps the ambiguity regardless of how
   many files follow. This catches most things, but know its ceiling going
   in. The E2E suites need
   `APP_URL`/`TEST_API_KEY` or a live instance to run, and network-timing
   dependent behavior (rate limits under real request latency, for example)
   plain doesn't reproduce in a unit test's mocked DI container no matter how
   it's stubbed. Real regressions in that territory only ever surface once
   CI actually runs them; expect that, don't be surprised by it later.

## Stage 3: Open the PR and handle review

Open the PR with the same plain, symptom-based naming as the branch. No
advisory ID anywhere in the title or body either, even once the fix is
written and the temptation is to just describe what was actually wrong.

Naming carefully doesn't make this disclosure-free: the diff itself, whatever
it's titled, is now visible to anyone with read access to the repo, before
the advisory is published. This project's accepted practice is to ship the
fix that way anyway (a working patch reaching anyone tracking `main`
immediately outweighs holding it back for a slower private-disclosure
process), and to hold the advisory's own *publication* for a window
afterward instead, separately (see Stage 4). That's a real, deliberate
tradeoff, not an oversight, so don't second-guess it by default. But it's a
tradeoff made for this project's normal case, not a universal constant. If
something about this specific advisory argues for more caution than usual
(an unusually severe impact, a likely well-resourced attacker actively
watching the repo), or if you're applying this skill somewhere that hasn't
made this same call, confirm with the user before pushing anything rather
than assuming the general practice applies unconditionally.

### Responding to CI and review comments

Verify every finding against the code as it currently stands before acting
on it. This is the single highest-value habit in this whole loop. Two
failure modes show up constantly, and both look identical to a real,
actionable finding at a glance:

- **Stale findings.** A review can run against a commit you've since
  superseded. Check the finding's `original_commit_id`/timestamp against
  what's actually on the branch now before assuming it's still true. If a
  later commit already fixed it, say so in the reply instead of re-doing the
  work or, worse, reverting a correct fix back to the flagged state.
- **Wrong findings.** A suggestion can be plausible-sounding and still be
  incorrect, including, notably, a suggestion that would silently reopen the
  exact vulnerability this whole skill exists to close ("just return the
  real ID here for compatibility," say, when the whole point of the fix was
  that the ID was a side channel). When that happens, don't comply to be
  agreeable. Reply with the concrete reason it's wrong and leave the code as
  it is.

When CI itself fails, read the actual failure log, not just the check name.
The real cause is often several layers removed from the symptom, and the
first plausible-looking cause isn't always the right one. Before reaching
for a fix, ask whether the thing you're trying to preserve is even used by
anything; the simplest correct fix for a broken test fixture is sometimes to
stop trying to recover a value nothing downstream actually reads, not to
route around whatever broke while getting it.

Reply to and resolve each review thread you actually acted on (including an
"already fixed, here's why" reply for stale ones). Skip threads you didn't
act on rather than resolving them as a formality.

## Stage 4: After merge

Confirm CI is actually green on the merge commit on the base branch, not
just on the PR branch before merging. A squash-merge or a concurrent change
to the base branch can still surface something new. Clean up the now-merged
branch and worktree once confirmed.

Publishing or updating the advisory itself (the patched-version field,
whether and when it goes public) is always the maintainer's call, never
something to do unprompted just because the fix shipped. Advisories are
routinely held back deliberately for a while after the fix lands, to give
users an update window before the vulnerability becomes public knowledge.
Update your own notes to reflect that timing decision once the user states
it, and don't revisit or nudge toward publishing until they raise it again.
