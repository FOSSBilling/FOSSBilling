# Security Policy

## Reporting Vulnerabilities

FOSSBilling accepts vulnerability reports directly through GitHub, to open a new one simply [click here](https://github.com/FOSSBilling/FOSSBilling/security/advisories/new).

If you are looking for existing advisories, those can be found [here on GitHub](https://github.com/FOSSBilling/FOSSBilling/security/advisories).

If you have a bug or a suggestion that is not related to an exploit, it should be reported [via a new issue](https://github.com/FOSSBilling/FOSSBilling/issues/new/choose). (But please check if someone has already submitted it first!)

A well-written vulnerability report should include the following information:

- Identification of the file(s) affected by the exploit
- Description of how the vulnerability can be exploited
- Potential ramifications of the vulnerability
- A proof of concept exploit (if possible)
- Insights into a possible solution

### Non-Qualifying Vulnerabilities

Reports covering any of the following topics will be rejected by the FOSSBilling team:

- Reports from automated tools or scanners.
- Theoretical attacks without proof of exploitability.
- Attacks that are the result of a third party library should be reported to the library maintainers.
- Social engineering.
- Reflected file download.
- Physical attacks.
- Weak SSL/TLS/SSH algorithms or protocols.
- Attacks involving physical access to a user’s device, or involving a device or network that’s already seriously compromised (eg man-in-the-middle).
- The user attacks themselves.
- Anything in `/tests-legacy` or `tests`.

### A special note on AI tooling

While you are more than welcome to use AI as an additional tool for security research and development, we will never accept something that is fully AI due to the substantial issues in quality control and accuracy.
AI is a tool and does not replace actual skill and understanding.

**If you are unable to perform security research / development without the assistance of an AI, then please do not attempt to do so for the FOSSBilling project.**
