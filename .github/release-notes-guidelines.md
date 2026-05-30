# FOSSBilling Release Notes Guidelines

Release notes should help administrators, developers, theme authors, and module maintainers decide how quickly to update and what to check after updating.

## Structure

- Start with `## VERSION (YYYY-MM-DD)` for published releases when the date is known. Draft notes may use `## VERSION`.
- Add a short italic introduction when the release has security fixes, breaking changes, or a broad theme worth calling out.
- Use these sections when they apply, in this order:
  - `### ⚠️ Potentially Breaking Changes`
  - `### 🔐 Security`
  - `### 📈 Enhancements`
  - `### ➕ New Features`
  - `### 🐛 Bug Fixes`
  - `### 📝 Changes`
  - `### 📦 Dependencies`
- Omit empty sections.
- Keep each bullet focused on user impact. Combine related pull requests into one bullet when that makes the notes easier to read.
- Include PR or issue references in parentheses where they help trace the change, for example `(#1234)`.

## Tone and Content

- Write in clear, direct language for FOSSBilling users, not as a raw commit log.
- Prefer outcome-focused wording: say what changed and why it matters.
- Call out upgrade risks for custom modules, themes, API integrations, payment flows, cron, installation, and security settings.
- Treat security wording carefully. Do not publish exploit details unless the security advisory is already public.
- Avoid duplicate bullets across sections. If a change fits multiple sections, place it where users are most likely to look.
- Exclude purely internal test, lint, spelling, and CI noise unless it affects release artifacts or user-visible behavior.
- Do not invent changes or overstate impact. When the available context is ambiguous, keep the wording conservative.

## Workflow Configuration

The `copilot` release-note mode uses Copilot CLI with a bring-your-own-key model provider. Configure these repository or organization variables:

- `COPILOT_PROVIDER_BASE_URL`: the provider endpoint, for example an OpenAI-compatible base URL.
- `COPILOT_PROVIDER_TYPE`: optional provider type, usually `openai`, `azure`, or `anthropic`.
- `COPILOT_MODEL`: the model identifier or deployment name.

Configure this repository or organization secret when the provider requires authentication:

- `COPILOT_PROVIDER_API_KEY`: the provider API key.

The selected model must support streaming and tool calling.
