import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { renderToString } from 'react-dom/server';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => title || 'ProsArtisan Backoffice',
        resolve: (name) => {
            const pages = import.meta.glob(['./pages/**/*.tsx', '!./pages/**/*.{test,spec}.tsx']);
            const path = Object.keys(pages).find((p) => p.toLowerCase().endsWith(`/pages/${name.toLowerCase()}.tsx`));
            if (!path) {
                throw new Error(`Page not found: ${name}`);
            }
            return typeof pages[path] === 'function' ? (pages[path] as any)() : pages[path];
        },
        setup: ({ App, props }) => <App {...props} />,
    }),
);
