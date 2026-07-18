import './bootstrap';
import '../css/app.css';

import { createInertiaApp, type ResolvedComponent } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent<ResolvedComponent>(
            `./Pages/${name}.tsx`,
            import.meta.glob<ResolvedComponent>('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: 'oklch(0.80 0.148 72)',
    },
});
