import { describe, expect, it } from 'vitest';

import { placeholders, renderTemplate, smsSegments } from './sms';

describe('smsSegments', () => {
    it('compte 160 caractères GSM par SMS, puis 153 par segment', () => {
        expect(smsSegments('a'.repeat(160))).toEqual({ encoding: 'gsm', length: 160, segments: 1 });
        expect(smsSegments('a'.repeat(161))).toEqual({ encoding: 'gsm', length: 161, segments: 2 });
    });

    it('bascule en Unicode dès un caractère hors alphabet GSM', () => {
        // « é » et « à » sont GSM, « ç » ne l'est pas.
        expect(smsSegments('Paiement validé à Abidjan').encoding).toBe('gsm');
        expect(smsSegments('Paiement reçu')).toEqual({ encoding: 'unicode', length: 13, segments: 1 });
        expect(smsSegments('ç' + 'a'.repeat(70)).segments).toBe(2);
    });

    it('compte double les caractères de la table d\'extension', () => {
        expect(smsSegments('€').length).toBe(2);
    });
});

describe('placeholders et renderTemplate', () => {
    it('extrait les variables sans doublon', () => {
        expect(placeholders('Commande #{commande} : {code} — {code}')).toEqual(['commande', 'code']);
    });

    it('remplace les variables connues et laisse visibles les inconnues', () => {
        expect(renderTemplate('Commande #{commande} pour {client}', { commande: '1042' })).toBe('Commande #1042 pour {client}');
    });
});
