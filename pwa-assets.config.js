import { defineConfig, minimal2023Preset as preset } from '@vite-pwa/assets-generator/config';

/**
 * PWA icon generator config.
 *
 * Generates all the PNG sizes needed for installable PWAs:
 *   - Android home-screen icons (192, 512)
 *   - iOS apple-touch-icon (180)
 *   - Maskable icon for Android adaptive icons (512)
 *   - favicon.ico
 *
 * Run with: `npm run pwa:icons` (added to package.json)
 *
 * Source: public/source-icon.svg — replace this SVG with your school's
 * official seal/crest if you have one and re-run.
 */
export default defineConfig({
    headLinkOptions: { preset: '2023' },
    preset,
    images: ['public/source-icon.svg'],
    // Where the generated PNGs land. Vite-plugin-pwa references them via
    // /build/pwa-*.png (see vite.config.js manifest icons array).
    outputDir: 'public/build',
});
