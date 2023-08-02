# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 0.5.x   | :white_check_mark: |
| 0.4.x   | :x: |
| 0.3.x   | :x: |
| 0.2.x   | :x: |
| 0.1.x   | :x: |

## Reporting Vulnerabilities

To report a vulnerability, please make a submission on [Huntr.dev](https://huntr.dev/bounties/disclose/?target=https://github.com/FOSSBilling/FOSSBilling).
Their website should provide guidance on how to create a comprehensive vulnerability report. It's crucial to submit vulnerabilities through their platform to keep them private and prevent exploitation while a patch is being developed.

If you have a bug or a suggestion that is not related to an exploit, it should be reported on our [GitHub](https://github.com/FOSSBilling/FOSSBilling/issues/new/choose). 

A well-written vulnerability report should include the following information:
 - Identification of the file(s) affected by the exploit
 - Description of how the vulnerability can be exploited
 - Potential ramifications of the vulnerability
 - A proof of concept exploit (if possible)
 - Insights into a possible solution

Submitting a proper vulnerability report on Huntr.dev may entitle you to a cash reward. Additionally, if you provide a patch, you may also be eligible for a reward.

### Non-Qualifying Vulnerabilities
Reports covering any of the following topics will be rejected and do not qualify for bounties.
Such reports may reduce your credibility as a researcher on the Huntr.dev platform or potentially cause you to be blocked from reporting vulnerabilities against FOSSBilling.

- Reports describing the lack of granular permissions within FOSSBilling. This is a known limitation and the permission system will be completely replaced before FOSSBilling is considered production-ready (version 1.0.0).
- Reports from automated tools or scanners
- Theoretical attacks without proof of exploitability
- Attacks that are the result of a third party library should be reported to the library maintainers
- Social engineering
- Reflected file download
- Physical attacks
- Weak SSL/TLS/SSH algorithms or protocols
- Attacks involving physical access to a user’s device, or involving a device or network that’s already seriously compromised (eg man-in-the-middle).
- The user attacks themselves
- Anything in `/tests`
- Anything in `/cypress`
