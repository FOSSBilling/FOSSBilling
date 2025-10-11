# FOSSBilling Project

## Project Overview

FOSSBilling is a free and open-source billing and client management solution designed for hosting businesses and other online service providers. It automates invoicing, payment processing, and client management while being extensible and easily integrable with server management software and payment gateways. The project is primarily written in PHP with modern frontend technologies.

### Key Technologies

* **Backend:** PHP 8.2+ with dependencies managed by Composer. Key libraries include:
  * [Symfony Components](https://symfony.com/): Console, cache, filesystem, HTTP client, and other core functionalities. See `composer.json` for a list of imported components.
    * Prefer Symfony components wherever you can.
  * [Twig](https://twig.symfony.com/): Template engine for rendering views
    * API endpoints are injected as parameters to Twig. See the "Interacting with the FOSSBilling API" section.
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
  * [Pimple](https://github.com/silexphp/Pimple): Dependency injection container, see `/src/di.php`.
* **Frontend:** Modern JavaScript and CSS with npm package management. Key dependencies include:
  * [Tabler.io](https://tabler.io): CSS framework for responsive design, based on [Bootstrap 5](https://getbootstrap.com/)
  * [Tom Select](https://tom-select.js.org/): Enhanced select boxes with search and tagging
  * [Autosize](http://www.jacklmoore.com/autosize/): Automatic textarea resizing
  * [Flag Icons](https://flagicons.lipis.dev/): Country flag icon library
  * jQuery is being slowly phased out. Avoid it and use vanilla JavaScript for new pieces of code.
* **Build Tools:**
  * [Symfony Webpack Encore](https://symfony.com/doc/current/frontend.html): Asset management and building
  * [Sass](https://sass-lang.com/): CSS preprocessing
  * [PostCSS](https://postcss.org/) with Autoprefixer: CSS post-processing
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
* **Themes:** Located in `src/themes/` for customizing the user interface
* **Libraries:** Core libraries and third-party integrations in `src/library/`
* **Configuration:** Environment-specific configurations and dependency injection setup

The application uses a modern PHP architecture with dependency injection, event-driven components, and a clean separation between business logic and presentation layers.

## Building and Running

### Prerequisites

* **PHP 8.2 or higher** with required extensions:
  * curl, intl, mbstring, pdo, zlib
* **Composer** for PHP dependency management
* **Node.js and npm** for frontend asset management
* **MySQL/MariaDB** database server

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

FOSSBilling uses Symfony Webpack Encore for asset compilation. Build frontend assets for themes and modules:

```bash
npm run build
```

This command builds assets for:

* `admin_default` theme
* `huraga` theme  
* `Wysiwyg` module

You can also build specific components:

```bash
# Build only themes
npm run build-themes

# Build only modules
npm run build-modules

# Build specific theme
npm run build-admin_default
npm run build-huraga

# Build specific module
npm run build-wysiwyg
```

### Testing

**PHPUnit Tests:** Run the PHP test suite using PHPUnit:

```bash
./src/vendor/bin/phpunit
```

The project has two test suites:

* **Library Tests:** Located in `tests-legacy/library/` for testing core library functionality
* **Module Tests:** Located in `tests-legacy/modules/` for testing individual modules

### Code Quality Tools

**PHP-CS-Fixer:** Format PHP code according to PSR-12 standards:

```bash
./src/vendor/bin/php-cs-fixer fix
```

**Rector:** Modernize and refactor PHP code automatically:

```bash
./src/vendor/bin/rector
```

**PHPStan:** Run static analysis to catch potential issues:

```bash
./src/vendor/bin/phpstan analyse
```

## Development Conventions

### Coding Standards

* **PHP:** Follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard
  * Use PHP-CS-Fixer to automatically format code: `./vendor/bin/php-cs-fixer fix`
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
├── data/                      # Runtime data (cache, logs, uploads)
├── install/                   # Installation scripts and assets
└── vendor/                    # Composer dependencies

tests/                         # Modern test structure
tests-legacy/                  # Legacy PHPUnit tests
```

### Front-end Guidelines

* Reuse svg files in the `admin_default` theme. Icons are compiled from the `src/themes/admin_default/assets/icons` directory and can be referenced from within the Twig template like so:

  ```html
  <svg class="icon">
    <use xlink:href="#icon-name" />
  </svg>
  ```

* Check the available Twig filters and functions in `src/library/Box/TwigExtensions.php`. Use these where applicable.

### Interacting with the FOSSBilling API

* API is injected directly into the Twig templates. You do not need to use fetch/AJAX to read from the API.
  * When applicable, APIs are injected as Twig parameters `admin`, `client`, and `guest`.
  * Guest API is always available. Admin and client APIs are injected if an admin or a client is logged in.
  * To access data, use this format: `{{ role.module_endpoint(optional_parameters) }}`. A few examples:
    * `{{ admin.support_ticket_get_list({ 'status': 'active' }) }}` => Reads into /api/admin/support/ticket_get_list?status=active
    * `{{ client.order_get({ 'id': 1 }) }}` => Reads into /api/client/order/get?id=1
    * `{{ guest.system_version }}` => Reads into /api/guest/system/version
* If you need to interact with the API for means other than reading data, use the FOSSBilling API Wrapper.
  * It is located in /src/library/Api/API.js
  * It has easy methods for interacting with the API
  * It automatically appends the CSRFToken parameter to the request data, you don't need to define it additionally.
* If you are using an HTML form to put/update data, you should use the `api-form` convention.
  * For details on how it works, examine the /src/themes/admin_default/assets/js/fossbilling.js file, especially the `apiForm` method.
  * You can read other modules' html_admin templates to see how it's used
  * The `api-form` can handle most of the create/update tasks, so try using it before resorting to manual API calls.
* You also should use `api-link` wherever you can when making simple API calls. Read other modules' html_admin templates to see how it's used

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
* **`phpunit.xml.dist`:** PHPUnit testing configuration
* **`phpstan.neon`:** PHPStan static analysis configuration
* **`.php-cs-fixer.dist.php`:** PHP-CS-Fixer coding standards configuration
* **`rector.php`:** Rector refactoring rules configuration
* **`src/config-sample.php`:** Sample application configuration file
* **`src/di.php`:** Dependency injection container setup

## Additional Resources

* **Documentation:** [fossbilling.org/docs](https://fossbilling.org/docs)
* **Issues:** [GitHub Issues](https://github.com/FOSSBilling/FOSSBilling/issues)

## Important Notes

* **PHP Version:** Requires PHP 8.2 or higher
* **Database:** Requires MySQL/MariaDB database server
* **License:** Apache License 2.0
