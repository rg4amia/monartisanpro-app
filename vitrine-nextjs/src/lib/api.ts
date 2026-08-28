export interface Slide {
    id: number;
    titre: string;
    sous_titre: string | null;
    image_url: string;
    cta_texte: string | null;
    cta_lien: string | null;
}

export interface Artisan {
    id: number;
    name: string;
    trade: string | null;
    score_prosartisan: number;
    kyc_selfie_path: string | null;
    city?: string | null;
}

export interface ArtisanDuMois {
    id: number;
    mois: string;
    texte_editorial: string;
    photo_url: string;
    artisan: Artisan;
}

export interface Article {
    id: number;
    titre: string;
    slug: string;
    contenu: string;
    image_url: string | null;
    categorie: 'actualite' | 'evenement' | 'temoignage' | 'partenariat';
    publie_at: string;
}

export interface Video {
    id: number;
    titre: string;
    description: string | null;
    video_url: string;
    thumbnail_url: string | null;
    categorie: string;
}

export interface Formation {
    id: number;
    titre: string;
    description: string;
    image_url: string | null;
    date_debut: string;
    date_fin: string | null;
    lieu: string;
    formateur: string | null;
    places_total: number | null;
    places_restantes: number | null;
    tarif: number;
    lien_inscription: string | null;
}

export interface Recrutement {
    id: number;
    titre: string;
    description: string;
    metier: string;
    lieu: string;
    type_contrat: 'cdi' | 'cdd' | 'stage' | 'freelance' | 'apprentissage';
    date_limite: string | null;
    contact_email: string | null;
}

export interface Popup {
    id: number;
    titre: string;
    contenu: string | null;
    image_url: string | null;
    lien_cta: string | null;
    texte_cta: string | null;
}

export function getApiBaseUrl(): string {
    if (process.env.NEXT_PUBLIC_API_URL) {
        return process.env.NEXT_PUBLIC_API_URL;
    }
    if (typeof window !== 'undefined') {
        return `${window.location.origin}/api/v1/vitrine`;
    }
    return 'https://prosartisan.net/api/v1/vitrine';
}

export function getAuthApiBaseUrl(): string {
    return getApiBaseUrl().replace('/vitrine', '');
}

// Mock fallbacks if the API is offline
const MOCK_SLIDES: Slide[] = [
    {
        id: 1,
        titre: "Des artisans qualifiés pour tous vos travaux en Côte d'Ivoire",
        sous_titre: "Électricité, plomberie, maçonnerie, menuiserie... Connectez-vous avec des pros de confiance agréés par notre label de qualité unique.",
        image_url: "https://images.unsplash.com/photo-1581092921461-eab62e97a780?auto=format&fit=crop&w=1200&q=80",
        cta_texte: "Trouver un artisan",
        cta_lien: "/artisans"
    },
    {
        id: 2,
        titre: "Équipez vos chantiers en toute sécurité avec notre séquestre intelligent",
        sous_titre: "Acomptes bloqués et libérés jalon par jalon. Paiements garantis Wave & Orange Money pour artisans et fournisseurs.",
        image_url: "https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=1200&q=80",
        cta_texte: "Nos Services",
        cta_lien: "/services"
    }
];

const MOCK_ARTISANS: Artisan[] = [
    { id: 1, name: "Kouamé Bah", trade: "Électricien bâtiment", score_prosartisan: 980, kyc_selfie_path: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80", city: "Abidjan (Yopougon)" },
    { id: 2, name: "Mariam Koné", trade: "Plombière sanitaire", score_prosartisan: 920, kyc_selfie_path: "https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=300&q=80", city: "Abidjan (Cocody)" },
    { id: 3, name: "Jean-Pierre Kouadio", trade: "Maçon coffreur", score_prosartisan: 890, kyc_selfie_path: "https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=300&q=80", city: "Abidjan (Koumassi)" },
    { id: 4, name: "Awa Touré", trade: "Menuisière ébéniste", score_prosartisan: 940, kyc_selfie_path: "https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=300&q=80", city: "Abidjan (Marcory)" }
];

const MOCK_ARTISAN_DU_MOIS: ArtisanDuMois = {
    id: 1,
    mois: "2026-08",
    texte_editorial: "Kouamé Bah s'est distingué ce mois-ci par sa réactivité exceptionnelle sur les chantiers de Yopougon et Cocody, ainsi que par un score ProsArtisan parfait de 980/1000 basé sur 14 avis clients.",
    photo_url: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=300&q=80",
    artisan: MOCK_ARTISANS[0]
};

const MOCK_ARTICLES: Article[] = [
    {
        id: 1,
        titre: "Lancement officiel de ProsArtisan en Côte d'Ivoire",
        slug: "lancement-de-prosartisan-plateforme-de-confiance",
        contenu: "<p>Nous sommes fiers de vous annoncer le lancement officiel de <strong>ProsArtisan</strong>, la première marketplace ivoirienne sécurisée par séquestre bancaire pour la mise en relation avec des artisans qualifiés.</p><p>Notre objectif est de professionnaliser le secteur informel du bâtiment en Côte d'Ivoire, en offrant des outils digitaux innovants (J-Codes, QR codes quincaillerie, validation GPS anti-fraude) et des solutions de micro-crédit d'urgence pour les artisans partenaires.</p>",
        image_url: "https://images.unsplash.com/photo-1531403009284-440f080d1e12?auto=format&fit=crop&w=800&q=80",
        categorie: "actualite",
        publie_at: "2026-08-27T12:00:00Z"
    },
    {
        id: 2,
        titre: "Plus de 50 quincailleries agréées rejoignent le réseau ProsArtisan",
        slug: "partenariat-quincailleries-agreees-abidjan",
        contenu: "<p>Afin de fluidifier l'approvisionnement en matériaux sur vos chantiers, nous avons noué des partenariats avec plus de 50 quincailleries à Yopougon, Cocody, Koumassi et Marcory.</p><p>Désormais, les artisans ProsArtisan peuvent générer des J-Codes pour récupérer instantanément le ciment, le fer ou la peinture nécessaires, réglés via le portefeuille matériaux sécurisé.</p>",
        image_url: "https://images.unsplash.com/photo-1513828729020-56f2d0d7e7a7?auto=format&fit=crop&w=800&q=80",
        categorie: "partenariat",
        publie_at: "2026-08-22T10:00:00Z"
    }
];

const MOCK_VIDEOS: Video[] = [
    {
        id: 1,
        titre: "Comment commander un artisan sur ProsArtisan ?",
        description: "Découvrez le parcours client en vidéo : soumission du besoin, diagnostic Gemini IA, validation du devis par séquestre Wave et livraison des jalons.",
        video_url: "https://www.youtube.com/embed/dQw4w9WgXcQ",
        thumbnail_url: "https://images.unsplash.com/photo-1541534741688-6078c6bfb5c5?auto=format&fit=crop&w=600&q=80",
        categorie: "capsule"
    },
    {
        id: 2,
        titre: "Témoignage de Seydou, maçon à Adjamé",
        description: "Seydou nous raconte comment l'accès au micro-crédit d'urgence et aux J-Codes matériaux a transformé son activité quotidienne et fidélisé ses clients.",
        video_url: "https://www.youtube.com/embed/dQw4w9WgXcQ",
        thumbnail_url: "https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=600&q=80",
        categorie: "temoignage"
    }
];

const MOCK_FORMATIONS: Formation[] = [
    {
        id: 1,
        titre: "Normes électriques de sécurité NF C 15-100 en Côte d'Ivoire",
        description: "Une session intensive de 2 jours destinée aux électriciens du bâtiment souhaitant labelliser leurs compétences et rejoindre le réseau d'artisans prioritaires ProsArtisan.",
        image_url: "https://images.unsplash.com/photo-1621905251189-08b45d6a269e?auto=format&fit=crop&w=800&q=80",
        date_debut: "2026-09-12",
        date_fin: "2026-09-13",
        lieu: "Maison des Artisans, Treichville, Abidjan",
        formateur: "M. Charles Koffi, Ingénieur électricien",
        places_total: 20,
        places_restantes: 12,
        tarif: 25000,
        lien_inscription: "https://prosartisan.ci/inscription-formation-1"
    },
    {
        id: 2,
        titre: "Gestion de budget et création de devis ProsArtisan",
        description: "Apprenez à utiliser l'application mobile ProsArtisan pour estimer vos matériaux, créer des devis équilibrés et optimiser vos jalons financiers.",
        image_url: "https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=800&q=80",
        date_debut: "2026-09-27",
        date_fin: "2026-09-27",
        lieu: "Siège ProsArtisan, Plateau, Abidjan",
        formateur: "Cabinet d'accompagnement PME CI",
        places_total: 30,
        places_restantes: 30,
        tarif: 0,
        lien_inscription: "https://prosartisan.ci/inscription-formation-2"
    }
];

const MOCK_RECRUTEMENTS: Recrutement[] = [
    {
        id: 1,
        titre: "Recrutement de 15 Plombiers qualifiés pour chantiers résidentiels",
        description: "Dans le cadre de projets immobiliers à Cocody Angré, nous recherchons des plombiers qualifiés ayant au moins 3 ans d'expérience. Inscription et KYC ProsArtisan obligatoires.",
        metier: "Plombier",
        lieu: "Abidjan - Cocody",
        type_contrat: "freelance",
        date_limite: "2026-09-15",
        contact_email: "recrutement@prosartisan.ci"
    }
];

const MOCK_POPUP: Popup = {
    id: 1,
    titre: "Bénéficiez de 5% de remise sur votre premier diagnostic de chantier !",
    contenu: "Utilisez le code promo PROMO5 lors de l'estimation IA de vos travaux. Valable pour toute première commande validée avant la fin du mois.",
    image_url: "https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=400&q=80",
    lien_cta: "/artisans",
    texte_cta: "Activer la remise"
};

const MOCK_SETTINGS: Record<string, string> = {
    vitrine_hero_title: "La confiance au cœur de l'artisanat ivoirien",
    vitrine_hero_subtitle: "Mise en relation sécurisée, artisans qualifiés et livraison de chantiers simplifiée.",
    contact_phone_vitrine: "+225 07 00 00 00 00",
    contact_email_vitrine: "contact@prosartisan.ci"
};

function getAuthToken(): string | null {
    if (typeof window !== 'undefined') {
        return localStorage.getItem('supplier_token');
    }
    return null;
}

async function fetchAuthApi<T>(
    endpoint: string,
    method: 'GET' | 'POST' | 'PUT' | 'DELETE' = 'GET',
    body?: any
): Promise<T> {
    const token = getAuthToken();
    const headers: Record<string, string> = {
        'Accept': 'application/json',
    };

    if (token) {
        headers['Authorization'] = `Bearer ${token}`;
    }

    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
    }

    const config: RequestInit = {
        method,
        headers,
    };

    if (body) {
        config.body = body instanceof FormData ? body : JSON.stringify(body);
    }

    const response = await fetch(`${getAuthApiBaseUrl()}${endpoint}`, config);

    if (response.status === 401) {
        if (typeof window !== 'undefined' && endpoint !== '/auth/verify-otp') {
            localStorage.removeItem('supplier_token');
            window.location.href = '/supplier/login';
        }
        const json = await response.json().catch(() => ({}));
        throw new Error(json.message || 'Identifiants ou code OTP invalide.');
    }

    const json = await response.json().catch(() => ({}));
    if (!response.ok || !json.success) {
        let errorMsg = json.message || 'Une erreur est survenue';
        if (json.errors && typeof json.errors === 'object') {
            const firstKey = Object.keys(json.errors)[0];
            if (firstKey && Array.isArray(json.errors[firstKey]) && json.errors[firstKey][0]) {
                errorMsg = json.errors[firstKey][0];
            }
        }
        throw new Error(errorMsg);
    }

    return json as T;
}

async function fetchFromApi<T>(endpoint: string, fallback: T): Promise<T> {
    try {
        const response = await fetch(`${getApiBaseUrl()}${endpoint}`, {
            next: { revalidate: 60 } // Cache 60 seconds
        });
        if (!response.ok) {
            throw new Error(`API error: ${response.statusText}`);
        }
        const json = await response.json();
        if (json.success && json.data !== undefined) {
            return json.data as T;
        }
        return fallback;
    } catch (e) {
        console.warn(`API query failed on ${endpoint}, returning fallback:`, e);
        return fallback;
    }
}

export const api = {
    async getSlides(): Promise<Slide[]> {
        return fetchFromApi<Slide[]>('/slides', MOCK_SLIDES);
    },
    async getArtisanDuMois(): Promise<ArtisanDuMois | null> {
        return fetchFromApi<ArtisanDuMois | null>('/artisan-du-mois', MOCK_ARTISAN_DU_MOIS);
    },
    async getArticles(category?: string): Promise<Article[]> {
        const query = category ? `?categorie=${category}` : '';
        const data = await fetchFromApi<{ data: Article[] } | Article[]>('/articles' + query, MOCK_ARTICLES);
        if (Array.isArray(data)) return data;
        return data.data || MOCK_ARTICLES;
    },
    async getArticle(slug: string): Promise<Article | null> {
        return fetchFromApi<Article | null>(`/articles/${slug}`, MOCK_ARTICLES.find(a => a.slug === slug) || null);
    },
    async getVideos(category?: string): Promise<Video[]> {
        const query = category ? `?categorie=${category}` : '';
        const data = await fetchFromApi<{ data: Video[] } | Video[]>('/videos' + query, MOCK_VIDEOS);
        if (Array.isArray(data)) return data;
        return data.data || MOCK_VIDEOS;
    },
    async getFormations(): Promise<Formation[]> {
        return fetchFromApi<Formation[]>('/formations', MOCK_FORMATIONS);
    },
    async getRecrutements(): Promise<Recrutement[]> {
        return fetchFromApi<Recrutement[]>('/recrutements', MOCK_RECRUTEMENTS);
    },
    async getPopup(): Promise<Popup | null> {
        return fetchFromApi<Popup | null>('/popup', MOCK_POPUP);
    },
    async getSettings(): Promise<Record<string, string>> {
        return fetchFromApi<Record<string, string>>('/settings', MOCK_SETTINGS);
    },
    async getArtisansStars(): Promise<Artisan[]> {
        const data = await fetchFromApi<{ data: Artisan[] } | Artisan[]>('/artisans-stars', MOCK_ARTISANS);
        if (Array.isArray(data)) return data;
        return data.data || MOCK_ARTISANS;
    },
    async getArtisans(query?: { metier?: string; ville?: string; note_min?: number }): Promise<Artisan[]> {
        let params = '';
        if (query) {
            const parts = [];
            if (query.metier) parts.push(`metier=${encodeURIComponent(query.metier)}`);
            if (query.ville) parts.push(`ville=${encodeURIComponent(query.ville)}`);
            if (query.note_min) parts.push(`note_min=${query.note_min}`);
            if (parts.length > 0) params = '?' + parts.join('&');
        }
        const data = await fetchFromApi<{ data: Artisan[] } | Artisan[]>('/artisans' + params, MOCK_ARTISANS);
        if (Array.isArray(data)) return data;
        return data.data || MOCK_ARTISANS;
    },
    async sendContact(data: { nom: string; email: string; sujet: string; message: string }): Promise<{ success: boolean; message: string }> {
        try {
            const response = await fetch(`${getApiBaseUrl()}/contact`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await response.json();
            return {
                success: json.success || false,
                message: json.message || 'Envoi effectué.'
            };
        } catch (e) {
            console.error('Contact submit error:', e);
            return {
                success: true,
                message: 'Votre message a été envoyé avec succès (mode simulé).'
            };
        }
    },
    // Espace Fournisseur APIs
    async supplierSendOtp(phone: string): Promise<boolean> {
        const clean = phone.replace(/\s+/g, '');
        const formatted = clean.startsWith('+') ? clean : (clean.startsWith('225') ? `+${clean}` : `+225${clean}`);
        const res = await fetchAuthApi<{ success: boolean }>('/auth/send-otp', 'POST', { phone: formatted, role: 'fournisseur' });
        return res.success;
    },
    async supplierVerifyOtp(phone: string, otp: string): Promise<{ token: string; user: any }> {
        const clean = phone.replace(/\s+/g, '');
        const formatted = clean.startsWith('+') ? clean : (clean.startsWith('225') ? `+${clean}` : `+225${clean}`);
        const res = await fetchAuthApi<{ success: boolean; token: string; user: any }>('/auth/verify-otp', 'POST', { phone: formatted, otp: otp.trim(), otpCode: otp.trim() });
        return { token: res.token, user: res.user };
    },
    async getSupplierDashboard(): Promise<any> {
        const res = await fetchAuthApi<{ success: boolean; data: any }>('/supplier/dashboard');
        return res.data;
    },
    async getSupplierProducts(): Promise<any[]> {
        const res = await fetchAuthApi<{ success: boolean; data: any[] }>('/supplier-products');
        return res.data;
    },
    async createSupplierProduct(data: any): Promise<any> {
        return fetchAuthApi<any>('/supplier-products', 'POST', data);
    },
    async updateSupplierProduct(id: number, data: any): Promise<any> {
        return fetchAuthApi<any>(`/supplier-products/${id}`, 'PUT', data);
    },
    async deleteSupplierProduct(id: number): Promise<any> {
        return fetchAuthApi<any>(`/supplier-products/${id}`, 'DELETE');
    },
    async getSupplierOrders(): Promise<any[]> {
        const res = await fetchAuthApi<{ success: boolean; data: any[] }>('/supplier/orders');
        return res.data;
    },
    async markOrderPrepared(id: number): Promise<any> {
        return fetchAuthApi<any>(`/orders/${id}/prepared`, 'POST');
    },
    async verifyOrderPickup(id: number, code: string): Promise<any> {
        return fetchAuthApi<any>(`/orders/${id}/verify-pickup`, 'POST', { code });
    },
    async getSupplierLitiges(): Promise<any> {
        const res = await fetchAuthApi<{ success: boolean; data: any }>('/supplier/litiges');
        return res.data;
    },
    async uploadSupplierImage(file: File): Promise<string> {
        const formData = new FormData();
        formData.append('file', file);
        const res = await fetchAuthApi<{ success: boolean; url: string }>('/upload', 'POST', formData);
        return res.url;
    }
};
