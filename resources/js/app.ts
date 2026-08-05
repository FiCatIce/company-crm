import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Company CRM';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                // R-B: every authenticated page shares ONE persistent shell so
                // the sidebar (and the CallDock for CTI 4c) survives Inertia
                // navigations. Pages must NOT wrap <AppLayout> themselves —
                // that double-wraps the sidebar; ShellGuardTest enforces it.
                // Breadcrumbs flow through useBreadcrumbs(), not props.
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();
