## Version 0.2.4 (12-16-2022)

### Security
 - Added a new security mode and settings
   - These settings are located in the `config.php` file and allow you to fine tune some security related options.
   - The default settings are what we recommend

### Bug fixes 
 - We've replaced the old `gettext` back end for translations. Translations should now work correctly for everyone.
 - Fixed issue with HestiaCP.
 - Cleanly handle no template being passed to the `renderString` function in the system module.
 - Fixed some issues with the client lookup
 - The API should now return HTTP status codes depending on the result.
 - Fixed some missing icons with the custom pages module.
 - The auto updater will now destroy the current session, this should help prevent and odd issues after updates.
 - Fixed the missing CSRF token on the EU tax sync button.
 - Removed the option to ping sitemaps top Bing as they do not accept them anymore.
 - PDF invoices will now hide company / client details that are not set, rather than an empty line.

### New Features
 - Set the default currency during installation
 - We've improved the "showcase" feature with Huraga, it now accepts markdown input and has multiple sizing options.
 - We've added some new events to be used in our demo module. (with a FOSSBilling demo coming soon)

### Other
 - Updated some dependencies
 - Significantly cleaned up the Huraga theme's dependencies, shrinking the overall theme size by about 5Mb.

## Version 0.2.3 (12-8-2022)

### Bug fixes 
 - Fixed some minor issues with the admin theme styling
 - Hide the settings button for themes that don't have settings
 - Another fix to the CSRF protection

## Version 0.2.2 (12-7-2022)

### Bug fixes 
 - Fixed more issues relating to the CSRF protection, including the checkout screen.

## Version 0.2.1 (12-7-2022)
This is a hotfix to fix issues introduced by the new security features added in 0.2.0. (has changes from PR#545)

## Version 0.2.0 (12-7-2022)
This release adds protection against CSRF attacks. This change will break outdated modules.
It's highly discouraged to disable this protection, but if needed you can edit the `CSRFPrevention` value in your `config.php` file and set it to false.

### Breaking Changes / Security
 - Implemented a token system to protect against CSRF attacks. outdated modules and themes will no longer work with this protection enabled.

### Bug fixes 
 - Fixed subscriptions with the PayPal payment adapter. 
 - Properly fixed issues with the VestaCP and HestiaCP server managers.
 - The localization files have been synced with the source code and we've pre-translated a few popular languages using machine learning.
 - Fixed issues when trying to click the filter icon in the admin dashboard.

### New Features
 - Sever managers can now specify their own input fields, making the setup process a bit more intuitive. 

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