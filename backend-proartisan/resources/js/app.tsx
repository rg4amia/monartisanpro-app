import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import '../css/app.css';

createInertiaApp({
    title: (title) => title || 'ProsArtisan Backoffice',
    resolve: (name) => {
        const pages = import.meta.glob(['./pages/**/*.tsx', '!./pages/**/*.{test,spec}.tsx']);
        const path = Object.keys(pages).find((p) => p.toLowerCase().endsWith(`/pages/${name.toLowerCase()}.tsx`));
        if (!path) {
            throw new Error(`Page not found: ${name}`);
        }
        return typeof pages[path] === 'function' ? (pages[path] as any)() : pages[path];
    },
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    progress: {
        color: '#4B5563',
    },
});
