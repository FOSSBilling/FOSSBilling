# FOSSBilling security advisory house style

Derived by sampling this repo's own *published* advisories directly (not
assumed), originally from 6/6 that were 100% consistent on structure:
GHSA-3g7m-ph2r-jhcx, GHSA-rrp7-fvc6-xxrw, GHSA-cqqm-p3x5-9fqg,
GHSA-x7p2-xhvc-cfp9, GHSA-q4rq-9844-r9w2, GHSA-5493-9m76-2qrr.

This is a *living* reference: it should keep accumulating rules and
revisions as more advisories get triaged, the same way it was built in the
first place. If the user corrects something during a future advisory, update
this file, don't just fix that one draft and move on. Log the change under
Revisions at the bottom so the reasoning behind a rule stays visible, not
just the rule itself.

This is distinct from the *reporter's* raw submission format (GitHub's
default Summary/Details/PoC/Impact template): this file describes the
maintainer-authored rewrite that actually gets published.

## Section order

```
## Summary
## Impact
## Affected Versions
## Patched Versions
## Details
## Workarounds
## Acknowledgements
```

### Summary
1-3 sentences: what the flaw is, where it lives (endpoint/class/method), and
the immediate consequence. State the mechanism, not just the outcome label.

### Impact
Lead with one or two plain-language sentences stating the real-world effect,
what a person or the business actually experiences, before any technical
framing. Don't open with a CIA-triad label ("Confidentiality: ...") as the
first thing the reader sees; save classification framing, if used at all,
for after the plain statement. Then list realistic follow-on scenarios in
concrete terms (what an attacker would actually do with this), not the
abstract class of bug.

### Affected Versions
Keep this to a single brief version-range line, nothing else:
- `FOSSBilling version 0.1.0 through 0.7.2 (all releases to date).`
- `FOSSBilling versions >= X and <= 0.7.2.`

Required, not optional: confirm this range to a reasonable degree via code
and git history before writing it down. Don't just trust the reporter's
guess; reporters usually only tested the current release. In practice:
- `git log -S"<distinguishing string/symbol>" -- <file>` to find when the
  vulnerable logic was introduced.
- Unshallow the clone first if needed (`git fetch --unshallow`). A shallow
  worktree will silently truncate the search at its shallow boundary and
  give a false "this is where it was introduced" answer. Check with
  `git rev-parse --is-shallow-repository`. This is cheap (took under a
  minute the one time it was needed) and worth doing by default.
- Check whether a later, unrelated hardening/refactor pass should have
  covered this code path but didn't. That's a strong signal for the true
  affected range, and good Details material (see the worked example below).
- If history genuinely can't be pinned down past a certain point (e.g. a
  rename/rewrite commit that shows the whole file as newly added), that's a
  legitimate place to stop. State the range as bounded by what's confirmed
  rather than chasing false precision that can't change the answer anyway.

Put any narrative about *why* that range is what it is in **Details**, not
here. This field is a fact, not an explanation.

### Patched Versions
Just the version, or `TBD` if no fix exists yet. Don't write a sentence
here either:
- `0.8.7`
- `TBD`

### Details
Technical root-cause walkthrough:
- Exact vulnerable entry point(s), by file/method.
- The specific logic responsible (quote it).
- Contrast with sibling code that handles the same concern correctly.
  FOSSBilling's own advisories consistently point out when "the fix pattern
  already exists elsewhere in the codebase"; it reads as more convincing
  than describing the bug in isolation.
- History where relevant: which PR/commit introduced the gap, or which
  later hardening pass should have covered this path but missed it. This
  turns "here's a bug" into "here's why it slipped through," which reads as
  far more credible and helps prevent recurrence.

### Workarounds
Practical steps an operator can take before a patch ships. If there is no
real workaround, say so plainly ("There is no complete workaround without
patching") and then list partial mitigations (WAF/reverse-proxy rate
limiting, disabling the affected feature, config hardening).

### Acknowledgements
`Thanks to <reporter> for responsibly reporting this vulnerability.`
Exact phrasing used in every sample checked; keep it consistent.

## Voice

Technical but approachable. Stay precise on file paths, method names, and
code (don't soften those), but avoid security-jargon labels and
internal-analyst asides where a plain description works ("the existence
check" over "sink", "how the message reaches the attacker" over "why the
message reaches the attacker verbatim", no "noted in passing"-style
meta-commentary). The reader is a technical person, not necessarily a
security specialist.

Avoid em dashes and other tics that read as AI-generated: triads of
adjectives, "it's not just X, it's Y" constructions, excessive bolded
lead-ins. Rewrite with plain periods/commas or restructure the sentence
instead.

## Severity / CVSS

This repo scores **CVSS v4 only**. Every published advisory checked has
`cvss_v3: null` and a populated `cvss_v4` field; don't carry over a v3.1
vector just because the reporter submitted one. Do NOT restate the vector
or score anywhere in the description body; it lives solely in the
advisory's `cvss_vector_string` API field.

Compute the score with a real tool rather than by hand. CVSS v4's base
score comes from a nested lookup table (the "macrovector"), not a formula,
and is easy to get wrong from memory. Python's `cvss` package supports it;
the vector string below is just an example of the syntax, not a default to
reuse:

```python
from cvss import CVSS4
c = CVSS4("CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:L/VI:N/VA:N/SC:N/SI:N/SA:N")
c.base_score, c.severity
```

Reason through each metric explicitly for the actual bug in front of you
before computing, rather than guessing the vector wholesale:
- `AV` (Attack Vector, N/Adjacent/Local/Physical): how does the attacker
  reach the vulnerable component?
- `AC` (Attack Complexity): does the attacker need to actively evade some
  built-in defense to get a working exploit, or does it just work?
- `AT` (Attack Requirements, new in v4, distinct from AC): does exploitation
  need a specific pre-existing state (a race window, being on-path, prior
  reconnaissance)? Most straightforward unauthenticated endpoint bugs are
  `AT:N`.
- `PR` (Privileges Required) / `UI` (User Interaction): self-explanatory.
- `VC`/`VI`/`VA` (Confidentiality/Integrity/Availability impact to the
  **Vulnerable** System): what does this specific vulnerability actually
  expose or corrupt on the system where it lives?
- `SC`/`SI`/`SA` (impact to a **Subsequent** System): only non-`N` when the
  impact genuinely extends to a separate downstream system, not just
  "an attacker could theoretically use this to attack something else later."

## CWEs

Pick by the precise technical mechanism, not the closest-sounding label.
CWE-204 (Observable Response Discrepancy, a message/content oracle) and
CWE-208 (Observable Timing Discrepancy) are both "enumeration" bugs in
casual conversation but are different root causes; don't default to
whichever sounds closer. Check whether a similar past advisory in this repo
used a particular CWE pairing worth following as precedent (this repo has
done that: an oracle-endpoint-plus-weak-rate-limiting bug used CWE-204
paired with CWE-307).

## Title

Avoid restating a CWE's official name as the title (e.g. don't write "via
observable response discrepancy" just because that's literally what
CWE-204 is called; it reads as jargon lifted from a taxonomy rather than a
plain description of the bug). Match the plain subject/verb/object pattern
used throughout this repo's real titles, e.g. "Unauthenticated X via Y
endpoint" or "Missing Z allows W". Use the project's own domain vocabulary
for entities: this app calls a customer record a "client" (`Box\Mod\Client`)
rather than a generic "user" or "account", so prefer that term where it
fits naturally.

## Applying changes to the live advisory

Two places need updating, and it's easy to only remember one:

1. The **description body** (the Markdown sections above).
2. A **separate structured field**, `vulnerabilities[0]`:
   `package.ecosystem` / `package.name` / `vulnerable_version_range` /
   `patched_versions`. This is what machine-readable consumers (OSV,
   Dependabot-style tooling) actually key off, and it's easy to leave
   silently inheriting whatever the original reporter typed if you only
   ever edit the prose. Explicitly review and PATCH it too, every time,
   even if the values turn out unchanged from the reporter's submission.
   That should be a deliberate confirmation, not an accident of omission.

**Ecosystem enum gotcha:** FOSSBilling is an application, not a
package-registry library, so the correct `package.ecosystem` value is the
literal string `other`. The write API rejects an empty string outright
(422: `` `` is not a possible value ``); the valid enum is `rubygems, npm,
pip, maven, nuget, composer, go, rust, erlang, actions, pub, other, swift`.
`package.name` stays `FOSSBilling` (not blank); every published advisory
checked uses that name.

## Worked example (provenance for the rules above)

An earlier advisory triaged through this process is where most of these
rules were established (kept unnamed here since it was still unpublished at
the time of writing; see this repo's private working notes from that triage
if you need the specifics):

- The Affected Versions git-archaeology rule came from tracing a reported
  bug back through an old rename commit, and separately discovering (via
  `git log -S` on the relevant symbol) that an unrelated PR had added a
  mitigation to sibling code in the same class but missed the exact path
  being reported, which became both the version-range justification and the
  strongest paragraph in Details.
- The "verify every factual claim" rule came from initially writing a
  Workarounds bullet that described a config toggle's UI label from memory;
  it was wrong, and grepping the actual Twig template caught it.
- The CVSS-v4-only and ecosystem-enum facts above came from directly
  inspecting other published advisories' API responses rather than assuming.

## Revisions

- v1: initial template derived from 6 published advisories.
- v1.1: "Affected Versions"/"Patched Versions" corrected to single-line,
  fact-only fields (no inline narrative; narrative belongs in Details).
- v1.2: confirmed CVSS is never restated in the body.
- v1.3: added the Voice section after feedback that a draft read as
  slightly over-technical; reworded jargon labels ("Sink" → "the existence
  check") and dropped internal-analyst-note phrasing.
- v1.4: Impact rewritten to lead with plain-language real-world effect
  instead of a CIA-triad-labeled bullet list.
- v1.5: confirmed git/code-history verification of Affected Versions is
  required, not optional, to a reasonable (not unlimited) degree.
- v1.6: added em-dash/AI-tic avoidance to Voice. Added the structured
  `vulnerabilities` API field as a required, separate PATCH step from the
  description prose, plus the `ecosystem: "other"` enum gotcha.
- v1.7: caught the skill itself violating its own em-dash/filler-word rule
  throughout (SKILL.md and this file). Rewritten. Lesson: apply the Voice
  section to the skill's own prose, not just to advisory drafts it produces.
