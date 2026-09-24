import { useEffect, useState } from 'react';
import type { ThemeMode } from '../shared';

export interface ExchangeRates {
    usdToXof: number;
    eurToXof: number;
    eurToUsd: number;
}

const THEME_STORAGE_KEY = 'prosartisan_admin_theme';

// Taux de repli si l'API de change est injoignable.
const FALLBACK_RATES: ExchangeRates = { usdToXof: 605.5, eurToXof: 655.96, eurToUsd: 1.085 };

/** Thème clair/sombre persisté par navigateur (confort, pas une donnée métier). */
export function useThemeMode() {
    const [themeMode, setThemeMode] = useState<ThemeMode>(() => {
        try {
            return (window.localStorage.getItem(THEME_STORAGE_KEY) as ThemeMode) || 'light';
        } catch {
            return 'light';
        }
    });

    useEffect(() => {
        try {
            window.localStorage.setItem(THEME_STORAGE_KEY, themeMode);
        } catch {
            // Stockage indisponible (navigation privée) : le thème reste en mémoire.
        }
    }, [themeMode]);

    const toggleTheme = () => setThemeMode((current) => (current === 'light' ? 'dark' : 'light'));

    return { themeMode, toggleTheme };
}

/** Connectivité réelle du navigateur, plus un mode hors-ligne simulable depuis les paramètres. */
export function useOnlineStatus() {
    const [isOnline, setIsOnline] = useState(() => (typeof navigator !== 'undefined' ? navigator.onLine : true));
    const [isOfflineSimulated, setIsOfflineSimulated] = useState(false);

    useEffect(() => {
        const handleOnline = () => setIsOnline(true);
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, []);

    return {
        offlineActive: !isOnline || isOfflineSimulated,
        isOfflineSimulated,
        toggleOfflineSimulated: () => setIsOfflineSimulated((prev) => !prev),
    };
}

/** Taux de change EUR/USD/XOF affichés dans l'en-tête de la console. */
export function useExchangeRates() {
    const [exchangeRates, setExchangeRates] = useState<ExchangeRates | null>(null);

    useEffect(() => {
        fetch('https://open.er-api.com/v6/latest/EUR')
            .then((res) => res.json())
            .then((data) => {
                if (data && data.rates) {
                    const eurToXof = data.rates.XOF || 655.957;
                    const eurToUsd = data.rates.USD || 1.09;
                    const usdToXof = eurToXof / eurToUsd;
                    setExchangeRates({
                        usdToXof: Math.round(usdToXof * 100) / 100,
                        eurToXof: Math.round(eurToXof * 100) / 100,
                        eurToUsd: Math.round(eurToUsd * 10000) / 10000,
                    });
                }
            })
            .catch(() => setExchangeRates(FALLBACK_RATES));
    }, []);

    return exchangeRates;
}
