import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { api, getApiBaseUrl, getAuthApiBaseUrl } from './api';

function mockFetchOnce(response: Partial<Response> & { json?: () => Promise<unknown> }) {
    const mock = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({}),
        ...response,
    });
    vi.stubGlobal('fetch', mock);
    return mock;
}

describe('getApiBaseUrl / getAuthApiBaseUrl', () => {
    afterEach(() => {
        vi.unstubAllEnvs();
    });

    it('utilise NEXT_PUBLIC_API_URL quand elle est définie', () => {
        vi.stubEnv('NEXT_PUBLIC_API_URL', 'https://staging.prosartisan.ci/api/v1/vitrine');
        expect(getApiBaseUrl()).toBe('https://staging.prosartisan.ci/api/v1/vitrine');
    });

    it('retombe sur l\'origine de la fenêtre en son absence', () => {
        vi.stubEnv('NEXT_PUBLIC_API_URL', '');
        expect(getApiBaseUrl()).toBe(`${window.location.origin}/api/v1/vitrine`);
    });

    it('dérive l\'URL d\'API d\'authentification en retirant le segment /vitrine', () => {
        vi.stubEnv('NEXT_PUBLIC_API_URL', 'https://prosartisan.ci/api/v1/vitrine');
        expect(getAuthApiBaseUrl()).toBe('https://prosartisan.ci/api/v1');
    });
});

describe('api — contenu public avec repli hors-ligne', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('retourne les données de l\'API quand la requête réussit', async () => {
        mockFetchOnce({
            json: async () => ({
                success: true,
                data: [{ id: 99, titre: 'Slide de test', sous_titre: null, image_url: '', cta_texte: null, cta_lien: null }],
            }),
        });

        const slides = await api.getSlides();

        expect(slides).toHaveLength(1);
        expect(slides[0].id).toBe(99);
    });

    it('retombe sur le contenu par défaut quand l\'API répond en échec HTTP', async () => {
        mockFetchOnce({ ok: false, status: 500 });

        const slides = await api.getSlides();

        expect(slides.length).toBeGreaterThan(0);
        expect(slides[0].titre).toContain('artisans qualifiés');
    });

    it('retombe sur le contenu par défaut quand l\'API est injoignable', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        const slides = await api.getSlides();

        expect(slides.length).toBeGreaterThan(0);
    });

    it('retombe sur le contenu par défaut quand la réponse ne signale pas de succès', async () => {
        mockFetchOnce({ json: async () => ({ success: false }) });

        const settings = await api.getSettings();

        expect(settings.contact_email_vitrine).toBe('info@prosartisan.net');
    });
});

describe('api.logWhatsappClick', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('journalise le clic avec la page d\'origine et la source', async () => {
        const fetchMock = mockFetchOnce({ json: async () => ({ success: true }) });

        await api.logWhatsappClick('/artisans');

        expect(fetchMock).toHaveBeenCalledWith(
            expect.stringContaining('/whatsapp-click'),
            expect.objectContaining({
                method: 'POST',
                keepalive: true,
                body: JSON.stringify({ page: '/artisans', source: 'floating_button' }),
            }),
        );
    });

    it('ne lève jamais d\'exception si la journalisation échoue', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        await expect(api.logWhatsappClick('/artisans')).resolves.toBeUndefined();
    });
});

describe('api.sendContact', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('retourne le message de succès du serveur', async () => {
        mockFetchOnce({ json: async () => ({ success: true, message: 'Message envoyé.' }) });

        const result = await api.sendContact({
            nom: 'Awa Traoré',
            email: 'awa@example.com',
            sujet: 'Devis',
            message: 'Bonjour',
        });

        expect(result).toEqual({ success: true, message: 'Message envoyé.' });
    });

    it('retourne un message d\'échec explicite si le serveur est injoignable', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        const result = await api.sendContact({
            nom: 'Awa Traoré',
            email: 'awa@example.com',
            sujet: 'Devis',
            message: 'Bonjour',
        });

        expect(result.success).toBe(false);
        expect(result.message).toContain('Impossible de joindre le serveur');
    });
});

describe('api — normalisation du numéro fournisseur (format +225)', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        window.localStorage.clear();
    });

    it('ajoute le préfixe +225 à un numéro local à 10 chiffres', async () => {
        const fetchMock = mockFetchOnce({ json: async () => ({ success: true }) });

        await api.supplierSendOtp('0700000001');

        const body = JSON.parse((fetchMock.mock.calls[0][1] as RequestInit).body as string);
        expect(body.phone).toBe('+2250700000001');
    });

    it('ajoute uniquement le "+" à un numéro déjà préfixé par 225', async () => {
        const fetchMock = mockFetchOnce({ json: async () => ({ success: true }) });

        await api.supplierSendOtp('2250700000001');

        const body = JSON.parse((fetchMock.mock.calls[0][1] as RequestInit).body as string);
        expect(body.phone).toBe('+2250700000001');
    });

    it('laisse inchangé un numéro déjà au format international', async () => {
        const fetchMock = mockFetchOnce({ json: async () => ({ success: true }) });

        await api.supplierSendOtp('+225 07 00 00 00 01');

        const body = JSON.parse((fetchMock.mock.calls[0][1] as RequestInit).body as string);
        expect(body.phone).toBe('+2250700000001');
    });

    it('transmet les paramètres anti-robot lors de l\'envoi OTP', async () => {
        const fetchMock = mockFetchOnce({ json: async () => ({ success: true }) });

        await api.supplierSendOtp('0700000001', {
            token: 'test_token',
            answer: '14',
            trap: '',
        });

        const body = JSON.parse((fetchMock.mock.calls[0][1] as RequestInit).body as string);
        expect(body.bot_token).toBe('test_token');
        expect(body.bot_answer).toBe('14');
        expect(body.bot_trap).toBe('');
    });

    it('récupère un défi anti-robot via getSecurityChallenge', async () => {
        mockFetchOnce({
            json: async () => ({
                success: true,
                challenge: {
                    token: 'tok_challenge',
                    question: '5 + 3 = ?',
                    a: 5,
                    b: 3,
                },
            }),
        });

        const challenge = await api.getSecurityChallenge('send_otp');

        expect(challenge?.token).toBe('tok_challenge');
        expect(challenge?.question).toBe('5 + 3 = ?');
    });

    it('retourne hasCompletedProfile et sauvegarde le token à la vérification OTP', async () => {
        mockFetchOnce({
            json: async () => ({ success: true, token: 'tok_abc', user: { id: 1 }, has_completed_profile: true }),
        });

        const result = await api.supplierVerifyOtp('0700000001', '1234');

        expect(result).toEqual({ token: 'tok_abc', user: { id: 1 }, hasCompletedProfile: true });
    });
});

describe('api — session fournisseur expirée (401)', () => {
    beforeEach(() => {
        window.localStorage.setItem('supplier_token', 'tok_expired');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
        window.localStorage.clear();
    });

    it('purge le token et redirige vers la page de connexion sur un 401', async () => {
        mockFetchOnce({ ok: false, status: 401, json: async () => ({ message: 'Session expirée.' }) });
        const replaceSpy = vi.fn();
        Object.defineProperty(window, 'location', {
            value: { ...window.location, replace: replaceSpy },
            writable: true,
        });

        await expect(api.getSupplierDashboard()).rejects.toThrow('Session expirée.');

        expect(window.localStorage.getItem('supplier_token')).toBeNull();
        expect(replaceSpy).toHaveBeenCalledWith('/supplier/login');
    });
});
