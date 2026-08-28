---
name: security-advisory-triage
description: Verify a reported GitHub Security Advisory (GHSA) against this repo's actual current code, then refine its title/description/CVSS/CWE metadata into this project's house style and apply it to the live advisory. Use this whenever the user gives you a GHSA ID, a security advisory number, a security report to "check", "verify", "triage", or "confirm", or asks whether a vulnerability report is valid/real/still exploitable on main. Also use it when the user wants an advisory's writeup improved, its CVSS score computed or recalculated, its CWEs corrected, or its title/description brought in line with how this repo normally publishes advisories, even if they only ask for one of those pieces (e.g. "recompute the CVSS for GHSA-xxxx") rather than the full workflow. Don't wait for the user to ask for "the process" by name; if they hand you a GHSA id or a raw vulnerability report against this codebase, this skill is almost certainly what they want.
---

# Security advisory triage

This is a two-stage workflow: first establish, with evidence, whether a reported
vulnerability is real against the code as it stands today; only then move on to
making the advisory itself good. Don't skip straight to writing prose about a bug
you haven't confirmed exists.

Both stages are collaborative and iterative, not something to run to completion
and dump on the user at the end. The reason is simple: writing a security advisory
involves a lot of small judgment calls (how far back does "affected versions" really
go, is this workaround actually true, does this deserve its own CWE), and the user
catching a wrong assumption early is much cheaper than catching it after you've
built five more paragraphs on top of it. Work through it piece by piece, show your
reasoning, and let the user correct course before you continue.

## Stage 1: Verify the report against current code

Goal: reach a confident, evidence-backed valid/invalid verdict, not "this looks
plausible."

1. **Fetch the advisory.** `gh api /repos/{owner}/{repo}/security-advisories/{ghsa_id}`
   gives you the reporter's summary, description, severity, CVSS, CWEs, and state
   (`triage`/`draft`/`published`/`closed`). Read the whole thing before touching code.

2. **Confirm you're looking at current code.** Before you verify anything, check
   that your working tree matches the branch the report claims to target:
   `git merge-base --is-ancestor origin/main HEAD` (or whatever branch is relevant).
   A "verified" conclusion built on stale code isn't a verification at all. This
   is cheap to check and easy to skip by accident, so make it a reflex.

3. **Trace the full path, not just the quoted lines.** Reporters usually quote two
   or three lines as evidence. Read those lines for real, but don't stop there.
   Follow the code end to end: where does the request enter, what's the actual
   vulnerable logic, and how does the response or exception actually get back to
   the caller? That last part is often what reports skip, and a bug that looks
   real at the "sink" can turn out to be redacted, caught, or rate-limited
   somewhere in the response path the reporter never looked at. In this codebase
   specifically, API exceptions flow through `Box\Mod\Api\Controller\Client::tryCall()`
   into `FOSSBilling\Http\ApiResponseFactory`, which is worth checking on any
   report about information disclosure via error messages. Also check whatever
   the report claims is *missing* (rate limiting, CAPTCHA, timing normalization,
   authorization checks) by grepping/reading for it yourself. Absence claims are
   exactly the kind of thing worth double-checking, not assuming.

4. **Pull related history for context.** `gh api /repos/{owner}/{repo}/security-advisories`
   lists every advisory in the repo, including closed and draft ones most people
   never look at. Skim titles for anything thematically close to the current
   report and read the close ones in full. This surfaces real things: a prior
   closed report about a related bug class, or a PR that hardened sibling code
   but happened to miss the exact path being reported now. That kind of context
   turns "here's a bug" into "here's why it slipped through," which is both more
   convincing and useful for Stage 2's Details section later.

5. **If unit tests exist for the relevant code, read them.** They're often the
   fastest, most authoritative way to confirm exact behavior (exception messages,
   config-flag semantics, order of operations) instead of inferring it from
   reading application code alone.

6. **Conclude with an explicit verdict**, laid out so each of the reporter's
   claims is checked against a specific file:line, not just asserted as "confirmed."
   A table works well for this. If something in the report doesn't hold up
   (wrong line numbers, a claimed-missing mitigation that actually exists,
   a severity that seems off), say so plainly. The point of this stage is an
   honest verdict, not validating the reporter's framing.

**Confidentiality note:** if the advisory is unpublished (`published_at: null`,
state `triage`/`draft`), treat the GHSA ID and exploit details as sensitive while
you work. Don't put them in public commit messages, PR titles/bodies, branch
names, or CI run titles; this has actually leaked before. Keep working notes in
a scratchpad or a private location instead.

## Stage 2: Refine the advisory and apply it

Only start this once Stage 1 has produced a real "yes, this is valid" verdict.

### Use the house style, and keep it current

`references/house-style.md` already has the FOSSBilling template (section
order, per-section rules, voice, CVSS/CWE/title conventions), derived by
sampling this repo's own *published* advisories rather than assuming the
reporter's raw submission format is the target. Read it before drafting
anything; it holds the detail so this file doesn't have to repeat it.

It's a living document, not a fixed spec. If a new advisory surfaces
something it doesn't cover, or the user corrects an assumption it makes,
update it in place and log the change under its Revisions section, the same
way it got built in the first place. If you ever suspect it's drifted from
reality (a new published advisory breaks one of its rules), re-derive by
sampling directly:

```
gh api /repos/{owner}/{repo}/security-advisories --paginate
# then fetch several state=published ones in full and diff their structure
```

### Work section by section

Draft one section, show it, take the feedback, apply it, move to the next.
Don't write the whole thing and ask for one blanket approval. By the time you've
written seven sections, an early wrong assumption has propagated through all of
them, and the user has to hold the whole draft in their head to catch it. A
tight loop on individual sections is much cheaper to fix and cheaper to review.

Things worth doing before the user has to point them out, because the pattern
already caught real mistakes when it wasn't done:

- **Verify every factual claim in the writeup against real code or tests**,
  don't just write what sounds plausible and flag it for the user to check.
  Config key names, exact admin-UI label text, and whether a suggested
  workaround genuinely behaves as described are all things you can grep and
  read yourself. If you catch yourself writing "I believe this is called X,"
  stop and go check.
- **Ground "Affected Versions" in real git history**, not guesswork. Reporters
  usually only tested the current release. `git fetch --unshallow` first if
  the checkout is shallow (cheap, do it by default, worth checking with
  `git rev-parse --is-shallow-repository`), then `git log -S"<distinguishing
  string>" -- <file>` to find where the vulnerable logic actually appeared.
  Also check whether a later, unrelated hardening pass should have covered
  this exact path but missed it. That's both a strong version-range signal
  and good material for the Details section. If history hits a genuine wall
  (a rename/rewrite commit that shows the whole file as newly added) and the
  pattern already existed at that wall, that's a legitimate stopping point:
  state the range as confirmed rather than manufacturing false precision by
  digging further with no possible payoff.
- **Compute CVSS with an actual tool**, not by hand. CVSS v4 in particular is
  scored via a nested lookup table (the "macrovector"), not a simple formula,
  and is easy to get wrong from memory. Python's `cvss` package
  (`from cvss import CVSS4`) is normally available; use it. Reason through
  each base metric explicitly before computing (see `references/house-style.md`
  for FOSSBilling's specific answer on which CVSS version this repo publishes).
- **Don't restate CVSS in the description body.** It belongs solely in the
  advisory's dedicated `cvss_vector_string` field.
- **Pick CWEs by mechanism, not vibes.** A message-content oracle and a
  timing oracle are different CWEs (204 vs. 208) even though they're both
  "enumeration" bugs in casual conversation. Get the mechanism right. Check
  whether a similar past advisory in this repo used a particular CWE pairing
  worth following as precedent.

### Voice

Technical but approachable, precise on file paths/method names/code, not
written like a jargon-dense internal analyst note. See `references/house-style.md`
for the specifics (jargon substitutions, AI-writing tics to avoid, how to open
the Impact section). Worth internalizing this one before drafting: it's the
kind of thing the user will keep correcting a little at a time if you don't.

### Apply it, but only once approved

The GHSA API has two places that both need updating on every advisory (the
description body, and a separate structured `vulnerabilities[0]` field), and
it's easy to only remember one. See `references/house-style.md` for exactly
what that second field is and the ecosystem-enum gotcha that will otherwise
produce a confusing 422.

Treat `gh api --method PATCH .../security-advisories/{ghsa_id}` as a real,
external, semi-irreversible action, even while the advisory is still
unpublished/triage. Only run it after the user has explicitly said to apply
the changes, not proactively once you think it's ready. If they've approved
individual sections along the way, that's still not the same as approval to
push to the live advisory; ask once the full set of changes is ready to go.
