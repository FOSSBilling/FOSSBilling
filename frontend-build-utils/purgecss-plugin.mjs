import { PurgeCSS } from 'purgecss';
import { readFile, writeFile } from 'fs/promises';
import path from 'path';

/**
 * PurgeCSS plugin for esbuild
 * Removes unused CSS from bundled files
 *
 * @param {string} themePath - Path to the theme directory
 * @param {boolean} enabled - Whether PurgeCSS is enabled (usually only in production)
 * @param {boolean} client - Set to true to check client-side module templates. When set to false, admin side will be checked instead.
 * @returns {Promise<void>}
 */
export async function purgeCssFile(cssFilePath, themePath, enabled = false, client = false) {
  if (!enabled) {
    return; // Skip in development
  }

  try {
    const css = await readFile(cssFilePath, 'utf8');
    const modulesPath = path.resolve(import.meta.dirname, '../src/modules');
    let contentPaths;
    
    if (client) {
      contentPaths = [
        `${themePath}/html/**/*.twig`,
        `${themePath}/assets/**/*.js`,
        `${modulesPath}/*/html_client/**/*.twig`,
      ];
    } else {
      contentPaths = [
        `${themePath}/html/**/*.twig`,
        `${themePath}/assets/**/*.js`,
        `${modulesPath}/*/html_admin/**/*.twig`,
      ];
    }

    const purgeCSSResult = await new PurgeCSS().purge({
      content: contentPaths,
      css: [{
        raw: css,
        extension: 'css'
      }],
      // Safelist classes that are added dynamically or used by JavaScript
      safelist: {
        standard: [
          /^fi-/, // Flag icon classes (dynamically generated)
          /^toast/, // Toast notification classes
          /^modal/, // Modal classes
          /^dropdown/, // Dropdown classes
          /^collapse/, // Collapse classes
          /^alert/, // Alert classes
          /^spinner/, // Spinner classes
          /^active$/, // Active state
          /^show$/, // Show state
          /^fade$/, // Fade transition
          /^nav-/, // Navigation classes
          /^data-bs-/, // Bootstrap data attributes
          /^btn-/, // Button variants
          /^card-/, // Cards
          /^badge-/, // Badges
          /^form-/, // Form controls
          /^text-/, // Text utilities
          /^bg-/, // Background utilities
          /^d-/, // Display utilities
          /^m-/, // Margin utilities
          /^p-/, // Padding utilities
          /^w-/, // Width utilities
          /^h-/, // Height utilities
          /^border-/, // Border utilities
          /^flex-/, // Flex utilities
          /^justify-/, // Justify utilities
          /^align-/, // Align utilities
          /^offcanvas-/, // Offcanvas components
          /^accordion-/, // Accordion components
          /^carousel-/, // Carousel components
        ],
        deep: [
          /tom-select/, // Tom Select plugin styles
          /ts-/, // Tom Select classes
        ],
        greedy: [
          /^theme-/, // Theme-specific classes
        ]
      },
      // Custom extractor for Twig templates and JavaScript
      defaultExtractor: content => {
        // Match class names in various formats:
        // - class="foo bar"
        // - className="foo"
        // - class: 'foo'
        // - classList.add('foo')
        // - Twig variables like {{ someVar }}
        const classMatches = content.match(/[A-Za-z0-9-_:/]+/g) || [];
        return classMatches;
      },
    });

    if (purgeCSSResult && purgeCSSResult[0]) {
      await writeFile(cssFilePath, purgeCSSResult[0].css);
      console.log(`✓ PurgeCSS applied to ${cssFilePath.split('/').pop()}`);
    }
  } catch (error) {
    console.warn(`⚠ PurgeCSS failed for ${cssFilePath}:`, error.message);
    // Don't fail the build, just warn
  }
}
