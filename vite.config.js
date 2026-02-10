// vite.config.js (in plugin root)
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import path from 'path'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/index.css',   // ← important: your entry
        'resources/js/index.js',     // if you use JS/Alpine/etc.
      ],
      refresh: true,                 // reload on blade/php changes
    }),

    tailwindcss(),   // handles Tailwind v4 processing nicely
  ],

  build: {
    outDir: path.resolve(__dirname, 'resources/dist'),
    emptyOutDir: true,
    manifest: false,
    rollupOptions: {
      output: {
        // For JS entry points (your 'index.js' becomes filament-blackbox.js)
        entryFileNames: 'filament-blackbox.js',

        // For CSS (and other assets like fonts/images if any)
        // We check the original name and force fixed name only for the main CSS
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'index.css') {
            return 'filament-blackbox.css'
          }
          // Other assets (if you add fonts/images later) keep default hashed name
          return 'assets/[name]-[hash][extname]'
        },

        // Optional: if you have dynamic chunks / vendor split, keep them hashed
        chunkFileNames: 'assets/[name]-[hash].js',
      },
    },
  },
})