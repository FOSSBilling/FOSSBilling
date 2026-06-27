# Frontend and Theme Builds

FOSSBilling builds shared browser assets from `frontend/` and theme assets from each theme workspace.

## Build Commands

- `npm run build` checks frontend source, builds core assets, and builds all themes.
- `npm run build:production` runs the same build with `NODE_ENV=production`.
- `npm run check` runs lightweight JavaScript syntax checks and validates theme icon manifests.
- `npm run build-admin_default` and `npm run build-huraga` build a single theme.

Generated assets are written to `src/public/assets` and `src/themes/*/assets/build`. These paths are ignored by Git and are copied into release artifacts by the Docker build.

## Theme Dependencies

Themes own the UI packages they consume. Shared tooling in `frontend/tools` can help compile assets, generate sprites, and post-process CSS, but it should not make a theme depend on a specific UI package.

Examples:

- `admin_default` owns Tabler, Coloris, Chart.js, Litepicker, Tom Select, Flag Icons, and its Tabler icon package.
- `huraga` owns Bootstrap, Tom Select, Flag Icons, and its Tabler icon package.
- `svg-sprite` is a root development dependency because the shared sprite helper imports it.

## Icons

Theme icon sprites are generated from each theme's `icon-manifest.json`.

- Add static SVG icon usage to the relevant theme manifest.
- Mark icons as `"dynamic": true` when the icon name is resolved at runtime, such as admin navigation icons from module metadata.
- Put theme-specific SVG files in the theme's `custom-icons` directory.
- Reference generated symbols with inline sprite IDs, for example `<use href="#home" />`.

Run `npm run check-icons` before changing icon usage.

## CSS

Theme vendor CSS belongs in `src/themes/*/assets/css/vendor.css`. Theme-specific styles belong in the theme Sass entrypoint.

PurgeCSS runs only for production builds. A PurgeCSS failure fails production builds so release CSS problems are caught early.
