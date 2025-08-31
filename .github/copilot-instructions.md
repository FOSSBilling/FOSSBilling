# FOSSBilling AI Coding Agent Instructions

## Project Overview

FOSSBilling is a PHP billing and client management solution with a modular architecture. It uses modern PHP 8.2+ with Symfony components, Twig templating, RedBeanPHP ORM, and Webpack Encore for frontend asset management.

## Architecture Patterns

### Module System
- **Location**: `src/modules/` (50+ modules)
- **Two types**: Service modules (products to sell) vs Extension modules (general functionality)
- **Structure**: Each module has `Service.php`, `Api/` folder with role-based classes (`Admin.php`, `Client.php`, `Guest.php`)
- **API Pattern**: All API classes extend `\Api_Abstract` and use dependency injection via `$this->di`

### Dependency Injection
- **Container**: Pimple DI container configured in `src/di.php` (814 lines)
- **Service Access**: `$this->di['mod_service']('ModuleName')` or `$this->di['mod_service']('ModuleName', 'ServiceClass')`
- **Interface**: Classes implement `FOSSBilling\InjectionAwareInterface`

### Database Layer
- **ORM**: RedBeanPHP (`gabordemooij/redbean`)
- **Access**: `$this->di['db']` for database operations
- **Models**: Located in `src/library/Model/`

## Development Workflows

### Frontend Asset Building
```bash
# Build all assets (themes + modules)
npm run build

# Build specific components
npm run build-admin_default    # Admin theme
npm run build-huraga          # Client theme  
npm run build-wysiwyg         # WYSIWYG module

# Individual workspace builds
npm run build -w admin_default
npm run build -w huraga
npm run build -w wysiwyg
```

### Testing
```bash
# Run PHPUnit tests
./src/vendor/bin/phpunit

# Test suites: Library (tests-legacy/library/), Modules (tests-legacy/modules/)
```

### Code Quality
```bash
# Format code (PSR-12)
./src/vendor/bin/php-cs-fixer fix

# Static analysis
./src/vendor/bin/phpstan analyse

# Automated refactoring
./src/vendor/bin/rector
```

## Key File Patterns

### Module API Structure
```php
// All module APIs follow this pattern:
namespace Box\Mod\ModuleName\Api;

class Admin extends \Api_Abstract {
    // Admin-only endpoints
}

class Client extends \Api_Abstract {  
    // Client user endpoints
}

class Guest extends \Api_Abstract {
    // Public endpoints
}
```

### Service Class Pattern
```php
// Module services implement dependency injection:
class Service implements FOSSBilling\InjectionAwareInterface {
    protected ?\Pimple\Container $di = null;
    
    public function setDi(\Pimple\Container $di): void {
        $this->di = $di;
    }
}
```

### Configuration
- **Main config**: `src/config.php` (copy from `src/config-sample.php`)
- **DI setup**: `src/di.php` - Central dependency injection configuration
- **Entry points**: `src/index.php` (web), `src/console.php` (CLI), `src/cron.php` (scheduled tasks)

## Frontend Architecture

### Webpack Configuration
- **Workspaces**: npm workspaces for themes and modules
- **Themes**: `src/themes/admin_default/`, `src/themes/huraga/`
- **Modules**: `src/modules/Wysiwyg/` (has frontend assets)
- **Build**: Each has own `webpack.config.js` using Symfony Webpack Encore

### Tech Stack
- **CSS Framework**: Tabler.io (Bootstrap 5 based)
- **JS Libraries**: Tom Select, Autosize, Flag Icons
- **Build Tools**: Webpack Encore, Sass, PostCSS with Autoprefixer

## Critical Development Notes

- **Beta Status**: Breaking changes possible, not SemVer compliant yet
- **PHP Version**: Requires 8.2+ with specific extensions (curl, intl, mbstring, pdo, zlib)
- **Module Types**: Understand Service vs Extension modules before creating new functionality
- **API Access**: Use `$this->di['mod_service']('ModuleName')` pattern for cross-module calls
- **Testing**: Run both library and module test suites before submitting changes
- **Asset Building**: Always rebuild frontend assets when modifying themes/modules

## Common Tasks

### Adding New Module Functionality
1. Identify if it's a Service module (product) or Extension module (feature)
2. Create API classes for appropriate roles (Admin/Client/Guest)
3. Implement Service class with dependency injection
4. Add tests in `tests-legacy/modules/ModuleName/`

### Debugging
- **Debug bar**: php-debugbar included for development
- **Logging**: Monolog configured, check `src/data/log/`
- **Error handling**: Whoops error handler in development mode
