## Version 0.2.0 (upcoming)

### Security
 - Created a new CSRF token system to prevent CSRF attacks.

## Other
 - Cleaned up old references

## Version 0.1.1 (12-3-2022)
This release is a quick hotfix to resolve some minor issues reported with version [0.1.0](https://github.com/FOSSBilling/FOSSBilling/releases/tag/0.1.0)
### New Features
- Added an "about" tab

### Bug Fixes
- Fix issues with the "email" domain registrar adapter.
- Fixed the income chart
- Fixed typos
- Fix misbehaving `isPreviewVersion()`
- Fixed wrong source for the staff login logo
- Use DejaVu Sans for PDF generation, this fixes issues with some Unicode characters
- Corrected some of the icons in the dashboard

## Version 0.1.0 (12-2-2022)
Note: this changelog is compared to BoxBilling version 4.22.1.5
### Security
 - Don't send the admin password in plain text email.
 - Prevent cron from paying deposit invoices with credits
 - Use the cryptographically secure `random_int()`
 - Properly define password requirements and enforce it
 - Various security improvements
 - Sanitize and validate email addresses
 - Removed obsolete file manager. It had security vulnerabilities and many bugs.
 - Default config for nginx will now properly block direct access to sensitive files.

### Bug Fixes
- Fixed database port not being used during installation
- Fixed database can't contain a hyphen
- Fixed issues with Centova Cast module
- Fixed issues with Plesk module
- Fixed issues with the SolusVM module
- Fixed bugs with the PDF generator
- Improved support for SVG images with PDF generation
- Fixed error with service domain manage page
- Changed storage engine to InnoDB
- General bugfixes and improved compatibility with the latest PHP versions
- Fixed issue with the admin theme not changing
- Fixed issues when trying to update a client that didn't have all the information set
- Fixed issues with custom pages on nginx
- Fixed issues when validating international domains
- Fixed port selection with the Virtualmin manager
- Fixed issues that could potentially cause FOSSBilling to infinitely attempt to resend emails if there is an error.
- Fixed issues with both the VestaCP and HestiaCP integrations.
- Prevent domain orders from being completed without selecting the "years"
- Removed the "API" tab from the staff members list due to bugs and security concerns.

### Breaking Changes
- Dropped the forum module
- Dropped the "BoxBilling" and "Bootstrap" themes
- Rename templates to native Twig extension (`.html.twig` instead of `.phtml`)
- Migrated to Twig version 3
- Removed the "bb" prefix from folders and path variables.
- The SolusVM and Centova Cast have been removed from the core software.

### Refactors
- Replaced TFPDF with dompdf for PDF generation
- Refactor the OrderButton module to use more theme assets instead of overriding
- Completely new admin theme
- Completely rewritten the Plesk integration.

### New Features
- Introduced the ability for FOSSBilling to migrate configuration files. - This can be manually run from the "Update FOSSBilling" screen
- Created a new `validateAndSanitizeEmail` tool.
- FOSSBilling will automatically execute cron when you log into the admin panel (as long as it hasn't been executed in at least 15 minutes. Can be disabled via the `disable_auto_cron` option in the config file)
- FOSSBilling will log a stack trace when an exception is thrown with debugging on. (`log_stacktrace` and `stacktrace_length` in the config file)
- FOSSBilling has a new maintenance mode which can be configured and enabled via the config file.
- FOSSBilling can now switch between release and preview branches for the automatic update tool.
- FOSSBilling will display a helpful message if you are using Apache without a .htaccess file.
- Added support for strikethrough in markdown. (`~~strikethrough~~`)
- Added the custom invoice text to the PDF invoice.
- Very basic support for an extension store inside of FOSSBilling.
- Added a new setting for a dark variant of your companies logo that will be used with dark mode. 

### Other
- Lots of dependency updates
- Add 4 new events
- Added HTTPS support to the DirectAdmin module
- Pointed the update checker to the new repository
- Code style improvements
- Replaced references to BoxBilling
- Improve nginx config
- Various Changes to Defaults
- Improved docker support
- Default to Huraga Green
- Replaced PT Sans with IBM Plex Sans
- Renamed "blog" to "news"
- Added toggles for the sidebar links to news and knowledge base
- Rewrote `emptyFolder()` to be cleaner and simpler.