import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

// When sharing the Vite dev server through a tunnel (VS Code port forwarding),
// set TUNNEL_VITE_HOST to the PUBLIC forwarded host for port 5173 — no scheme,
// e.g. TUNNEL_VITE_HOST=abc123-5173.usw2.devtunnels.ms npm run dev
// This makes the injected asset URLs + the HMR websocket target the public tunnel
// instead of localhost, so a remote browser can actually load them.
const tunnelHost = process.env.TUNNEL_VITE_HOST;

export default defineConfig({
    server: {
        // Bind every interface so the tunnel (and other devices) can reach Vite.
        host: '0.0.0.0',
        ...(tunnelHost
            ? {
                  // Absolute origin for asset URLs Laravel injects from `public/hot`.
                  origin: `https://${tunnelHost}`,
                  // The 8000-tunnel page and the 5173-tunnel assets are different
                  // sub-domains, so the dev server must allow cross-origin loads.
                  cors: true,
                  hmr: {
                      host: tunnelHost,
                      protocol: 'wss',
                      clientPort: 443,
                  },
              }
            : {}),
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
