import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { renderToString } from 'react-dom/server';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.tsx');
            const path = Object.keys(pages).find((p) => p.toLowerCase().endsWith(`/pages/${name.toLowerCase()}.tsx`));
            if (!path) {
                throw new Error(`Page not found: ${name}`);
            }
            return typeof pages[path] === 'function' ? (pages[path] as any)() : pages[path];
        },
        setup: ({ App, props }) => <App {...props} />,
    }),
);
