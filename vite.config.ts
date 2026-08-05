import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
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
        // public/hot and the tunnel config both hardcode 5173; if another dev
        // server already holds the port (e.g. on [::1] only), fail loudly
        // instead of silently moving to 5174 while the browser hits the wrong
        // project.
        port: 5173,
        strictPort: true,
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
            : {
                  // No tunnel: keep binding 0.0.0.0 (LAN devices can still reach
                  // the dev server directly), but write a dialable address to
                  // public/hot — 0.0.0.0 is a listen address, browsers refuse it
                  // (ERR_ADDRESS_INVALID). The Apache-served page (:80/:8000) and
                  // the assets (:5173) are cross-origin, hence cors.
                  origin: 'http://localhost:5173',
                  cors: true,
                  hmr: { host: 'localhost' },
              }),
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            // No `fonts` providers here on purpose: fonts are vendored in
            // resources/fonts + fonts.css because the CRM must build and run
            // on-prem with no network access.
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
