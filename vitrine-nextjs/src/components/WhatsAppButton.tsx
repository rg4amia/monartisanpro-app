'use client';

import { useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import { api } from '@/lib/api';

function WhatsAppIcon(props: React.SVGProps<SVGSVGElement>) {
    return (
        <svg viewBox="0 0 24 24" fill="currentColor" {...props}>
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.87 9.87 0 0 0 4.74 1.2h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.12h-.01a8.2 8.2 0 0 1-4.18-1.14l-.3-.18-3.12.82.83-3.04-.2-.31a8.19 8.19 0 0 1-1.26-4.36c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.18 8.18 0 0 1 2.41 5.83c0 4.55-3.7 8.2-8.25 8.2zm4.52-6.16c-.25-.12-1.47-.72-1.69-.81-.23-.08-.39-.12-.56.13-.17.25-.64.81-.78.97-.14.17-.29.19-.54.06-.25-.12-1.05-.39-1.99-1.23-.74-.66-1.24-1.47-1.38-1.72-.14-.25-.02-.38.11-.51.11-.11.25-.29.37-.43.12-.14.16-.25.25-.41.08-.17.04-.31-.02-.44-.06-.12-.56-1.35-.77-1.85-.2-.48-.41-.42-.56-.43h-.48c-.17 0-.44.06-.67.31-.23.25-.87.85-.87 2.08 0 1.22.89 2.4 1.02 2.57.12.17 1.75 2.67 4.24 3.74.59.26 1.05.41 1.41.52.59.19 1.13.16 1.56.1.48-.07 1.47-.6 1.67-1.18.21-.58.21-1.08.14-1.18-.06-.11-.23-.17-.48-.29z" />
        </svg>
    );
}

export default function WhatsAppButton() {
    const pathname = usePathname();
    const [settings, setSettings] = useState<Record<string, string>>({});

    useEffect(() => {
        let mounted = true;
        api.getSettings()
            .then((data) => {
                if (mounted && data && typeof data === 'object') {
                    setSettings(data);
                }
            })
            .catch(() => {});
        return () => {
            mounted = false;
        };
    }, []);

    const enabled = settings['whatsapp_widget_enabled'] !== '0';
    const phoneRaw = settings['whatsapp_widget_phone'] || '';
    const phone = phoneRaw.replace(/[^0-9]/g, '');
    const message = settings['whatsapp_widget_message'] || "Bonjour ProsArtisan, je souhaite être mis en relation avec un artisan.";

    if (!enabled || !phone) {
        return null;
    }

    const href = `https://api.whatsapp.com/send/?phone=${phone}&text=${encodeURIComponent(message)}&type=phone_number&app_absent=0`;

    const handleClick = () => {
        api.logWhatsappClick(pathname || '/');
    };

    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            onClick={handleClick}
            aria-label="Discuter avec ProsArtisan sur WhatsApp"
            className="fixed bottom-5 right-5 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-black/20 transition hover:scale-105 hover:bg-[#20bd5a] sm:bottom-6 sm:right-6"
        >
            <WhatsAppIcon className="h-7 w-7" />
            <span className="sr-only">Discuter sur WhatsApp</span>
        </a>
    );
}
