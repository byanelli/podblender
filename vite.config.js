import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        react(),
    ],
    // Bind to IPv4 loopback. The default ("localhost") resolves to the IPv6
    // loopback on macOS, which makes Vite serve modules at http://[::1]:5173 —
    // unreachable from other devices through ngrok, and spotty in some
    // browsers. 127.0.0.1 also matches where the dev proxy in
    // app/Http/Controllers/ViteDevProxy forwards to.
    server: {
        host: '127.0.0.1',
    },
});
