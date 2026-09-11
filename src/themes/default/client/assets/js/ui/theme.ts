/**
 * Huraga's theme entry point: re-exports the shared controller. The companion
 * FOUC-prevention partial (partials/theme_init.html.twig) applies the stored
 * choice before paint; this module handles post-paint toggles.
 */
export { initThemeToggle as default } from '../../../../../../../frontend/core/theme-toggle.mts';
