import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// En développement, on proxifie /api vers l'API Symfony locale.
export default defineConfig({
  plugins: [vue()],
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
