import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// Les appels /api sont redirigés vers le serveur Laravel (php artisan serve)
export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
})
