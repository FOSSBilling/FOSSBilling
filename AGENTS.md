# FOSSBilling Project

## Project Overview

FOSSBilling is a free and open-source billing and client management solution designed for hosting businesses and other online service providers. It automates invoicing, payment processing, and client management while being extensible and easily integrable with server management software and payment gateways. The project is primarily written in PHP with modern frontend technologies.

### Key Technologies

* **Backend:** PHP 8.3+ with dependencies managed by Composer. Key libraries include:
  * [Symfony Components](https://symfony.com/): Console, cache, filesystem, HTTP client, and other core functionalities. See `composer.json` for a list of imported components.
    * Prefer Symfony components wherever you can.
    * Use `Filesystem`, `Path`, and `Finder` for filesystem operations instead of native PHP functions (e.g., `$filesystem->exists()` instead of `file_exists()`, `Finder` instead of `glob()`).
  * [Twig](https://twig.symfony.com/): Template engine for rendering views
    * API endpoints are injected as parameters to Twig. See the "Interacting with the FOSSBilling API" section.
    * Twig environments are created via `TwigFactory` at `src/library/FOSSBilling/Twig/TwigFactory.php`.
    * Three environment types: **admin**, **client**, and **email** (sandboxed for security).
    * Email templates use a sandboxed environment (`EmailPolicy.php`) that restricts allowed tags/filters/functions.
  * [RedBeanPHP](https://redbeanphp.com/): ORM for database interactions in legacy modules.
  * [Doctrine DBAL/ORM](https://doctrine-project.org/): ORM and DBAL for modern modules.
    * FOSSBilling is in the process of migrating modules and core parts from RedBeanPHP to Doctrine one by one.
    * The entity manager is available as `$di['em']`. It comes from the EntityManagerFactory in `/src/library/FOSSBilling/Doctrine/EntityManagerFactory.php`.
    * Entities and repositories reside under `/src/modules/*/Entity/{Entity}.php` and `/src/modules/*/Repository/{EntityRepository}.php`.
    * The FOSSBilling project is in the process of gradually phasing out RedBeanPHP in favor of Doctrine ORM.
    * When writing new pieces of code, avoid RedBeanPHP.
    * If you are assisting with the migration from RedBeanPHP to Doctrine, do your best to keep compatibility with the existing table structure.
    * When refactoring API endpoints, check how the `$di['pager']` works in `src/library/FOSSBilling/Pagination.php`. `paginateDoctrineQuery()` is the replacement for `getPaginatedResultSet()`.
  * [Monolog](https://github.com/Seldaek/monolog): Logging framework. Used via `$di['logger']` (`/src/library/FOSSBilling/Monolog.php`).
  * [dompdf](https://github.com/dompdf/dompdf): PDF generation for invoices and documents
  * [Pimple](https://github.com/silexphp/Pimple): Dependency injection container, see `src/di.php`.
* **Frontend:** Modern JavaScript and CSS with npm package management. Key dependencies include:
  * [Tabler.io](https://tabler.io): CSS framework for the admin theme, based on [Bootstrap 5](https://getbootstrap.com/)
  * [Bootstrap 5](https://getbootstrap.com/): CSS framework used directly by the Huraga client theme
  * [CKEditor 5](https://ckeditor.com/ckeditor-5/): Shared rich text editor built into the core public assets
  * [Tom Select](https://tom-select.js.org/): Enhanced select boxes with search and tagging
  * [Autosize](http://www.jacklmoore.com/autosize/): Automatic textarea resizing
  * [Flag Icons](https://flagicons.lipis.dev/): Country flag icon library
  * Use vanilla JavaScript for all JS code.
* **Build Tools:**
  * [esbuild](https://esbuild.github.io/): Fast JavaScript/CSS bundler and minifier
  * [Sass](https://sass-lang.com/): CSS preprocessing
  * [PostCSS](https://postcss.org/) with Autoprefixer: CSS post-processing
  * [svg-sprite](https://github.com/svg-sprite/svg-sprite): SVG sprite generation for icons
* **Testing:**
  * [PHPUnit](https://phpunit.de/): Unit and integration testing framework
* **Code Quality & Analysis:**
  * [PHP-CS-Fixer](https://cs.symfony.com/): PSR-12 coding standards enforcement
  * [Rector](https://getrector.org/): Automated PHP code refactoring and modernization
  * [PHPStan](https://phpstan.org/): Static analysis for PHP code

### Architecture

FOSSBilling follows a modular architecture with clear separation of concerns:

* **Core Application:** Located in `src/` directory containing the main application logic
* **Modules:** Located in `src/modules/` - Two types of modules exist:
  * **Service Modules:** Represent products that can be sold (e.g., hosting packages, downloadable products). These modules' names must start with "Service", such as "Servicehosting".
  * **Extension Modules:** Extend FOSSBilling with additional functionality
  * Module templates are organized in `templates/admin/`, `templates/client/`, and `templates/email/` subdirectories.
* **Themes:** Located in `src/themes/` for customizing the user interface
* **Libraries:** Core libraries and third-party integrations in `src/library/`
* **Public assets:** Located in `src/public/` for public core assets, branding assets, and gateway icons. Generated core browser assets are built into `src/public/assets`.
* **Configuration:** Environment-specific configurations and dependency injection setup

The application uses a modern PHP architecture with dependency injection, event-driven components, and a clean separation between business logic and presentation layers.

## Building and Running

### Prerequisites

* **PHP 8.3 or higher** with required extensions:
  * curl, intl, mbstring, pdo, zlib
* **Composer** for PHP dependency management
* **Node.js and npm** for frontend asset management
  * Docker and DDEV use Node.js 24, and `package.json` requires npm 11 or newer.
* **MySQL/MariaDB** database server

**Important:** If PHP is not installed or configured on the system, try using `ddev` to manage the development environment and run PHP/Composer commands.
DDEV is configured with `docroot: src`, Node.js 24, MariaDB 10.11, and `data/uploads` as its upload directory. Its post-start hook installs Composer/npm dependencies and rebuilds frontend assets when needed.

### Dependencies

Install PHP dependencies with Composer:

```bash
composer install
```

Install Node.js dependencies with npm:

```bash
npm install
```

### Building Frontend Assets

FOSSBilling uses esbuild for fast asset compilation. Build core frontend assets and theme assets:

```bash
npm run build
```

This command builds assets for:

* Core public browser assets in `src/public/assets`
* `admin_default` theme
* `huraga` theme

The core build script is `frontend/esbuild.mjs`. Theme build scripts are defined in each theme's `package.json` and use local `esbuild.mjs` files for configuration:

```bash
# Build only themes
npm run build-themes

# Build specific theme (uses workspace scripts)
npm run build-admin_default
npm run build-huraga
```

**Theme Structure:**

* **Core frontend**: Source lives in `frontend/` and builds the browser API wrapper, shared runtime, Markdown CSS, and editor assets into `src/public/assets`
* **Shared build helpers**: Theme esbuild scripts import common loaders, Sass/PostCSS helpers, asset copying, and PurgeCSS helpers from `frontend/tools/esbuild-helpers.mjs`
* **admin_default**: Uses `esbuild.mjs` with SVG sprite generation, SCSS compilation, and multiple asset types
* **huraga**: Uses simplified `esbuild.mjs` for theme JS/CSS bundling

**Development Mode:**

```bash
# Theme development builds
cd src/themes/admin_default && npm run dev

# Watch mode for active Huraga development
cd src/themes/huraga && npm run dev
```

### Testing

**PHPUnit Tests:** Run the PHP test suite using PHPUnit:

```bash
composer test
```

The project has two test suites:

* **Library Tests:** Located in `tests-legacy/library/` for testing core library functionality
* **Module Tests:** Located in `tests-legacy/modules/` for testing individual modules

`phpunit.xml.dist` currently runs the legacy PHPUnit suites by default. The `tests/` directory contains newer test and end-to-end test structure, including Cypress tests under `tests/e2e`.

### Code Quality Tools

**PHP-CS-Fixer:** Format PHP code according to PSR-12 standards:

```bash
composer cs:fix
```

**Rector:** Modernize and refactor PHP code automatically:

```bash
composer rector:fix
```

**PHPStan:** Run static analysis to catch potential issues:

```bash
composer phpstan
```

## Development Conventions

### Coding Standards

* **PHP:** Follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard
  * Use PHP-CS-Fixer to automatically format code: `composer cs:fix` or `./src/vendor/bin/php-cs-fixer fix`
  * Static analysis with PHPStan helps catch potential issues
* **Frontend:** Modern JavaScript (ES6+) and Sass for styling. Avoid jQuery.
* **Commit Messages:** Follow the project's commit message conventions detailed in `CONTRIBUTING.md`

### Project Structure

```text
src/
├── config-sample.php          # Sample configuration file
├── di.php                     # Dependency injection configuration
├── index.php                  # Main entry point
├── console.php                # CLI application entry point
├── cron.php                   # Scheduled tasks entry point
├── library/                   # Core libraries and third-party integrations
├── modules/                   # Application modules (50+ modules)
├── themes/                    # UI themes (admin_default, huraga)
├── public/                    # Public core assets, branding, and gateway icons
├── data/                      # Runtime data (cache, logs, uploads)
├── install/                   # Installation scripts and assets
└── vendor/                    # Composer dependencies

frontend/                      # Core frontend source and shared build tooling
tests/                         # Modern and end-to-end test structure
tests-legacy/                  # Legacy PHPUnit tests
```

### Front-end Guidelines

* `admin_default` theme icons are compiled from the `src/themes/admin_default/assets/icons` directory and can be referenced from within the Twig template like so:

  ```html
  <svg class="icon">
    <use xlink:href="#icon-name" />
  </svg>
  ```

* Check the available Twig filters and functions in `src/library/FOSSBilling/Twig/Extension/FOSSBillingExtension.php` and `src/library/FOSSBilling/Twig/Extension/LegacyExtension.php`. Use these where applicable.
  * `ApiExtension.php` provides `fb_api`, `fb_api_form`, `fb_api_link` helpers and `|api_url` filter.
  * Twig extensions use PHP 8 attributes (`#[AsTwigFunction]`, `#[AsTwigFilter]`).
  * Use `|asset_url` for current-theme assets and `|public_asset_url` for shared core public assets in `src/public/assets`.
  * Use `{{ wysiwyg('.selector') }}` for rich text editors. This loads the shared CKEditor bundle from `src/public/assets/editor`.
* Global Twig variables include: `app_area` ('admin' or 'client'), `current_theme`, `guest`, `CSRFToken`, `request`, `FOSSBillingVersion`.
* Email templates are rendered in a sandboxed Twig environment. Use `|markdown_to_html` filter for markdown content. The sandbox restricts available tags/filters - see `EmailPolicy.php` for allowed operations.

### Interacting with the FOSSBilling API

* API is injected directly into the Twig templates. You do not need to use fetch/AJAX to read from the API.
  * When applicable, APIs are injected as Twig parameters `admin`, `client`, and `guest`.
  * Guest API is always available. Admin and client APIs are injected if an admin or a client is logged in.
  * To access data, use this format: `{{ role.module_endpoint(optional_parameters) }}`. A few examples:
    * `{{ admin.support_ticket_get_list({ 'status': 'active' }) }}` => Reads into /api/admin/support/ticket_get_list?status=active
    * `{{ client.order_get({ 'id': 1 }) }}` => Reads into /api/client/order/get?id=1
    * `{{ guest.system_version }}` => Reads into /api/guest/system/version
* If you need to interact with the API for means other than reading data, use the FOSSBilling API wrapper provided by `frontend/core/api.js` and loaded from `public/assets/js/api.js`. It provides `FOSSBilling.api.admin`, `FOSSBilling.api.client`, and `FOSSBilling.api.guest` namespaces with `get`, `post`, `put`, `delete`, `patch` methods. CSRFToken is automatically appended.
* For HTML forms that submit to the API, use the `fb_api_form()` Twig function. Example: `<form {{ fb_api_form({message: 'Saved'|trans}) }}>`
* For links that trigger API calls, use the `fb_api_link()` Twig function. Example: `<a href="..." {{ fb_api_link({modal: {type: 'confirm'}}) }}>`
* `fb_api_form()` and `fb_api_link()` output `data-fb-api` attributes. The core API bundle automatically binds matching forms and links when it loads.
* Use the `|api_url` filter to generate API URLs. Example: `{{ 'client/delete'|api_url({id: 1}) }}`

### Contributing Workflow

1. **Read Documentation:** Start with `CONTRIBUTING.md` for detailed guidelines
2. **Development Setup:** Install dependencies and build assets
3. **Code Quality:** Run tests and code quality tools before submitting
4. **Module Development:** Understand the distinction between Service modules and Extension modules
5. **Testing:** Write tests for new functionality using PHPUnit

## Key Files and Directories

* **`README.md`:** Project overview, installation instructions, and general information
* **`CONTRIBUTING.md`:** Comprehensive contribution guidelines and development workflow
* **`composer.json`:** PHP dependencies, scripts, and project metadata
* **`package.json`:** Node.js dependencies, build scripts, and workspace configuration
* **`frontend/`:** Core frontend source and shared esbuild helper code
* **`src/public/`:** Public shared assets, default branding, and gateway icons
* **`phpunit.xml.dist`:** PHPUnit testing configuration
* **`phpstan.neon`:** PHPStan static analysis configuration
* **`.php-cs-fixer.dist.php`:** PHP-CS-Fixer coding standards configuration
* **`rector.php`:** Rector refactoring rules configuration
* **`src/config-sample.php`:** Sample application configuration file
* **`src/di.php`:** Dependency injection container setup

## Additional Resources

* **Documentation:** [FOSSBilling Docs](https://docs.fossbilling.org/)
* **Issues:** [GitHub Issues](https://github.com/FOSSBilling/FOSSBilling/issues)

## Important Notes

* **PHP Version:** Requires PHP 8.3 or higher
* **Database:** Requires MySQL/MariaDB database server
* **License:** Apache License 2.0
