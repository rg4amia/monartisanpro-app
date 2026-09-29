// Outils des messages push & SMS (Chantier 14, lot C). Reprend les règles de
// `NotificationTemplateService` côté serveur, qui reste seul juge à
// l'enregistrement : ce calcul ne sert qu'à guider la saisie.

/** Alphabet GSM 03.38, table de base : 160 caractères par SMS. */
const GSM_BASIC =
    "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

/** Table d'extension GSM : ces caractères comptent double. */
const GSM_EXTENDED = '^{}\\[~]|€';

const PLACEHOLDER = /\{([a-z_]+)\}/g;

export interface SmsSegments {
    encoding: 'gsm' | 'unicode';
    length: number;
    segments: number;
}

/** GSM-7 : 160 caractères (153 par segment au-delà) ; Unicode : 70 (67). */
export function smsSegments(text: string): SmsSegments {
    const chars = Array.from(text);
    let length = 0;

    for (const char of chars) {
        if (GSM_BASIC.includes(char)) {
            length += 1;
        } else if (GSM_EXTENDED.includes(char)) {
            length += 2;
        } else {
            const unicodeLength = chars.length;
            return {
                encoding: 'unicode',
                length: unicodeLength,
                segments: Math.max(1, unicodeLength <= 70 ? 1 : Math.ceil(unicodeLength / 67)),
            };
        }
    }

    return { encoding: 'gsm', length, segments: Math.max(1, length <= 160 ? 1 : Math.ceil(length / 153)) };
}

/** Variables `{nom}` présentes dans un texte, sans doublon. */
export function placeholders(text: string): string[] {
    return Array.from(new Set(Array.from(text.matchAll(PLACEHOLDER), (match) => match[1])));
}

/** Remplace les variables en une passe ; une variable inconnue reste visible. */
export function renderTemplate(text: string, values: Record<string, string>): string {
    return text.replace(PLACEHOLDER, (whole, name: string) => (name in values ? values[name] : whole));
}
