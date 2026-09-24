import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

// Build CSS/JS from resources/ → public/theme/ so Laravel serves them via asset('theme/...')
// Run: npm run build  (inside backend/)
export default defineConfig({
  plugins: [tailwindcss()],
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
