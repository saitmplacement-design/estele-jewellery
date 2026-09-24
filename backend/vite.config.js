import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

// Build CSS/JS from resources/ → public/theme/ so Laravel serves them via asset('theme/...')
// Run: npm run build  (inside backend/)
export default defineConfig({
  plugins: [tailwindcss()],
  // Vite's default publicDir is public/, which sits ABOVE outDir here — every
  // build copied all of public/ (index.php, .htaccess, Filament assets, even a
  // stale local public/build) into public/theme/. Only app.css/app.js belong
  // in public/theme, so nothing is copied.
  publicDir: false,
  build: {
    outDir: 'public/theme',
    emptyOutDir: true,
    manifest: false,
    // CSS minification disabled: Lightning CSS collapses compound selectors
    // that share suffixes (e.g. .page-loader.is-active and .carousel__dot.is-active)
    // into a bare .is-active, silently breaking all is-active toggles sitewide.
    cssMinify: false,
    rollupOptions: {
      input: {
        app: 'resources/js/app.js',
      },
      output: {
        entryFileNames: 'app.js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
