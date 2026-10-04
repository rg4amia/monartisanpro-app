<?php

namespace App\Services\Notifications;

use InvalidArgumentException;

/**
 * Catalogue des événements de notification (Chantier 14, lot B).
 *
 * Le code décide QUAND un message part (appel de `NotificationService::notify`
 * avec la clé de l'événement) ; le catalogue fixe ce qu'il dit par défaut et
 * sur quels canaux. Les administrateurs pourront surcharger textes et canaux
 * depuis le backoffice (table `notification_templates`, lot C) ; sans
 * surcharge, les valeurs ci-dessous s'appliquent.
 *
 * Champs d'un événement :
 * - `label`      : libellé affiché dans le backoffice ;
 * - `domain`     : regroupement (clé de `DOMAINS`) ;
 * - `audience`   : destinataire (clé de `AUDIENCES`) ;
 * - `type`       : type historique enregistré sur la notification, lu par le
 *                  mobile pour ouvrir l'écran concerné et classer la liste ;
 * - `variables`  : variables `{nom}` utilisables dans les textes ;
 * - `required`   : variables qu'un texte personnalisé doit conserver ;
 * - `title` / `body` : texte push et in-app par défaut ;
 * - `sms_body`   : texte SMS par défaut (absent = « titre : corps ») ;
 * - `sms`        : SMS envoyé par défaut ;
 * - `in_app` / `push` : canal actif par défaut (absent = oui) ;
 * - `locked`     : canaux imposés, que le backoffice ne peut pas changer ;
 * - `direct`     : message envoyé hors de `NotificationService::notify()`
 *                  (le code OTP part par `OtpService`), dont seul le texte SMS
 *                  est tiré du catalogue.
 *
 * Une alerte du domaine « securite » garde toujours le push ou le SMS
 * (`NotificationTemplateService::validate`).
 *
 * SMS par défaut (décision du 29/09/2026) : OTP, paiements reçus, fraude visant
 * l'utilisateur, ouverture d'un litige ; côté administrateurs, fraude et
 * courses bloquées uniquement. Tout le reste : push seul.
 */
class NotificationCatalog
{
    public const DOMAINS = [
        'missions' => 'Missions et devis',
        'etapes' => 'Étapes et paiements de chantier',
        'jcode' => 'Bons matériel (J-Code)',
        'commandes' => 'Commandes de matériaux',
        'livraisons' => 'Livraisons et courses',
        'litiges' => 'Litiges et jury',
        'compte' => 'Compte et vérification d\'identité',
        'securite' => 'Sécurité et fraude',
        'finances' => 'Retraits, versements et crédit',
        'recrutement' => 'Recrutement',
        'discussion' => 'Discussion de chantier',
        'parrainage' => 'Parrainage',
        'annuaire' => 'Annuaire des artisans',
    ];

    public const AUDIENCES = [
        'client' => 'Client',
        'artisan' => 'Artisan',
        'fournisseur' => 'Fournisseur',
        'livreur' => 'Livreur',
        'referent' => 'Référent',
        'admin' => 'Administrateurs',
        'partie' => 'Client ou artisan de la mission',
        'recruteur' => 'Recruteur (client ou fournisseur)',
        'utilisateur' => 'Tout utilisateur',
        'beneficiaire' => 'Bénéficiaire d\'un virement',
    ];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return self::EVENTS;
    }

    public static function has(string $event): bool
    {
        return isset(self::EVENTS[$event]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $event): array
    {
        if (! isset(self::EVENTS[$event])) {
            throw new InvalidArgumentException("Événement de notification inconnu : {$event}");
        }

        return self::EVENTS[$event] + [
            'required' => [],
            'sms_body' => null,
            'variables' => [],
            'in_app' => true,
            'push' => true,
            'locked' => [],
            'direct' => false,
        ];
    }

    /**
     * Valeur d'exemple d'une variable, pour l'aperçu et l'envoi de test du
     * backoffice (jamais pour un envoi réel).
     */
    public static function example(string $variable): string
    {
        return self::EXAMPLES[$variable] ?? 'exemple';
    }

    private const EXAMPLES = [
        'adresse_client' => 'Cocody Angré, 8e tranche',
        'adresse_fournisseur' => 'Quincaillerie du Plateau',
        'artisan' => 'Koffi Yao',
        'avertissement' => 'Dernier rappel avant restriction du compte.',
        'bonus' => " (dont bonus d'attente 500 FCFA)",
        'client' => 'Awa Traoré',
        'client_id' => '1287',
        'code' => '482915',
        'commande' => '1042',
        'commandes' => '1042, #1043',
        'compte' => '1287',
        'contexte' => 'paiement de l\'étape 2',
        'decision' => 'remboursement partiel du client',
        'distance' => '240',
        'etape' => '2',
        'expediteur' => 'Koffi Yao',
        'extrait' => 'Je passe demain à 9 h pour la dalle.',
        'fournisseur' => 'Quincaillerie du Plateau',
        'jour' => '3',
        'jours' => '5',
        'jures' => '1',
        'litige' => '57',
        'livreur' => 'Moussa Diallo',
        'livreur_id' => '311',
        'max' => '100',
        'minutes' => '5',
        'mission' => '318',
        'montant' => '12 500',
        'motif' => 'retard de livraison',
        'nom' => 'Awa Traoré',
        'offre' => 'Maçon pour chantier à Yopougon',
        'paiement' => '905',
        'rappel' => '2',
        'reaffectations' => '3',
        'recruteur' => 'Awa Traoré',
        'reference' => 'RL-2026-0042',
        'relances' => '5',
        'restant' => '37 500',
        'role' => 'artisan',
        'score' => '800',
        'statut' => 'retenue',
        'taille' => '3',
        'tarif' => '10 000',
        'telephone' => '+2250700000000',
        'tentative' => '2',
    ];

    private const EVENTS = [
        // ─── Discussion de chantier ─────────────────────────────────────────
        'chat.nouveau_message' => [
            'label' => 'Nouveau message dans la discussion du chantier',
            'domain' => 'discussion', 'audience' => 'partie', 'type' => 'chat_message',
            'variables' => ['expediteur' => 'Nom de l\'expéditeur', 'extrait' => 'Début du message (ou « Message vocal », « Photo de chantier »)'],
            'title' => 'Nouveau message de {expediteur}',
            'body' => '{extrait}',
            'sms' => false,
        ],

        // ─── Missions et devis ──────────────────────────────────────────────
        'mission.demande_devis.artisan' => [
            'label' => 'Demande de devis reçue',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'mission',
            'variables' => ['client' => 'Nom du client'],
            'title' => 'Nouvelle demande de devis',
            'body' => 'Le client {client} vous a envoyé une demande de devis.',
            'sms' => false,
        ],
        'mission.demande_acceptee.client' => [
            'label' => 'Demande de devis acceptée par l\'artisan',
            'domain' => 'missions', 'audience' => 'client', 'type' => 'mission',
            'title' => 'Demande de devis acceptée',
            'body' => "L'artisan a accepté votre demande et prépare le devis.",
            'sms' => false,
        ],
        'mission.demande_refusee.client' => [
            'label' => 'Demande de devis refusée par l\'artisan',
            'domain' => 'missions', 'audience' => 'client', 'type' => 'mission',
            'title' => 'Demande de devis refusée',
            'body' => "L'artisan a refusé votre demande de devis. Vous pouvez sélectionner un autre artisan.",
            'sms' => false,
        ],
        'mission.demande_retiree.artisan' => [
            'label' => 'Demande de devis retirée à l\'artisan',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'mission',
            'title' => 'Demande de devis retirée',
            'body' => 'Une demande de devis ne vous est plus destinée : le client a choisi un autre artisan ou le délai de réponse est dépassé.',
            'sms' => false,
        ],
        'mission.relance_demande.artisan' => [
            'label' => 'Relance : demande de devis sans réponse',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'mission',
            'variables' => ['heures' => 'Heures restantes pour répondre'],
            'title' => 'Une demande de devis vous attend',
            'body' => 'Il vous reste environ {heures} h pour accepter ou refuser une demande de devis. Passé ce délai, elle vous sera retirée.',
            'sms' => false,
        ],
        'mission.sans_reponse.client' => [
            'label' => 'Demande de devis restée sans réponse de l\'artisan',
            'domain' => 'missions', 'audience' => 'client', 'type' => 'mission',
            'variables' => ['heures' => 'Délai de réponse (heures)'],
            'title' => 'Artisan sans réponse',
            'body' => "L'artisan n'a pas répondu sous {heures} h. Vous pouvez sélectionner un autre artisan.",
            'sms' => false,
        ],
        'mission.validation_finale.client' => [
            'label' => 'Fin de chantier à valider par le client',
            'domain' => 'missions', 'audience' => 'client', 'type' => 'mission',
            'variables' => ['mission' => 'Numéro de la mission', 'heures' => 'Délai de validation (heures)'],
            'title' => 'Validez la fin du chantier',
            'body' => 'Toutes les étapes de la mission #{mission} sont payées. Validez la fin du chantier ; sans réponse sous {heures} h, la mission sera clôturée automatiquement.',
            'sms' => false,
        ],
        'mission.cloturee.artisan' => [
            'label' => 'Mission clôturée (validation finale)',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'mission',
            'variables' => ['mission' => 'Numéro de la mission'],
            'title' => 'Chantier clôturé',
            'body' => 'La fin du chantier de la mission #{mission} est validée. La mission est clôturée.',
            'sms' => false,
        ],
        'mission.cloturee_auto.client' => [
            'label' => 'Mission clôturée automatiquement',
            'domain' => 'missions', 'audience' => 'client', 'type' => 'mission',
            'variables' => ['mission' => 'Numéro de la mission'],
            'title' => 'Mission clôturée',
            'body' => 'Sans réponse de votre part, la mission #{mission} a été clôturée automatiquement. Vous pouvez noter l\'artisan.',
            'sms' => false,
        ],
        'mission.annulee.artisan' => [
            'label' => 'Mission annulée par le client',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'mission',
            'variables' => ['mission' => 'Numéro de la mission'],
            'title' => 'Mission annulée',
            'body' => 'Le client a annulé la mission #{mission}.',
            'sms' => false,
        ],
        'mission.annulee_remboursee.client' => [
            'label' => 'Mission annulée : remboursement et pénalité',
            'domain' => 'missions', 'audience' => 'client', 'type' => 'mission',
            'variables' => ['mission' => 'Numéro de la mission', 'montant' => 'Montant remboursé', 'penalite' => 'Pénalité retenue'],
            'title' => 'Mission annulée',
            'body' => 'La mission #{mission} est annulée. {montant} FCFA vous sont remboursés, après une pénalité de {penalite} FCFA.',
            'sms' => false,
        ],
        'devis.recu.client' => [
            'label' => 'Devis reçu de l\'artisan',
            'domain' => 'missions', 'audience' => 'client', 'type' => 'devis',
            'variables' => ['artisan' => 'Nom de l\'artisan', 'mission' => 'Numéro de la mission'],
            'title' => 'Nouveau devis reçu',
            'body' => "L'artisan {artisan} vous a transmis un devis pour la mission #{mission}.",
            'sms' => false,
        ],
        'devis.avenant_recu.client' => [
            'label' => 'Avenant de devis reçu de l\'artisan',
            'domain' => 'missions', 'audience' => 'client', 'type' => 'devis_avenant',
            'variables' => ['artisan' => 'Nom de l\'artisan', 'mission' => 'Numéro de la mission'],
            'title' => 'Nouvel avenant de devis reçu',
            'body' => "L'artisan {artisan} a soumis un avenant pour la mission #{mission}.",
            'sms' => false,
        ],
        'devis.accepte.artisan' => [
            'label' => 'Devis accepté et payé par le client',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['mission' => 'Numéro de la mission'],
            'title' => 'Devis validé et fonds séquestrés !',
            'body' => 'Votre devis pour la mission #{mission} a été approuvé. Les fonds sont disponibles et sécurisés en séquestre.',
            'sms' => false,
        ],
        'devis.avenant_accepte.artisan' => [
            'label' => 'Avenant accepté et payé par le client',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['mission' => 'Numéro de la mission'],
            'title' => 'Avenant validé et fonds séquestrés !',
            'body' => 'Votre avenant pour la mission #{mission} a été approuvé. Les fonds supplémentaires sont disponibles et sécurisés en séquestre.',
            'sms' => false,
        ],
        'devis.refuse.artisan' => [
            'label' => 'Devis refusé par le client',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'devis',
            'variables' => ['mission' => 'Numéro de la mission'],
            'title' => 'Devis refusé',
            'body' => 'Le client a refusé votre devis pour la mission #{mission}.',
            'sms' => false,
        ],
        'mission.validee_referent.artisan' => [
            'label' => 'Mission validée sur site par le référent',
            'domain' => 'missions', 'audience' => 'artisan', 'type' => 'validation',
            'variables' => ['mission' => 'Numéro de la mission'],
            'title' => 'Mission validée par le référent',
            'body' => 'La mission #{mission} a été validée sur site par le référent. Les paiements en attente ont été libérés.',
            'sms' => false,
        ],

        // ─── Étapes et paiements de chantier ────────────────────────────────
        'jalon.a_valider.client' => [
            'label' => 'Étape soumise, à valider par le client',
            'domain' => 'etapes', 'audience' => 'client', 'type' => 'validation',
            'variables' => ['etape' => 'Numéro de l\'étape'],
            'title' => 'Jalon à valider',
            'body' => "L'artisan a soumis le jalon #{etape}. Validez avec le code OTP.",
            'sms' => false,
        ],
        'jalon.controle_securite.artisan' => [
            'label' => 'Contrôle de sécurité avant paiement d\'une étape',
            'domain' => 'securite', 'audience' => 'artisan', 'type' => 'fraud_alert',
            'variables' => ['etape' => 'Numéro de l\'étape'],
            'title' => 'Contrôle de sécurité en cours',
            'body' => "La validation du jalon #{etape} fait l'objet d'une vérification de sécurité avant libération des fonds.",
            'sms' => true,
        ],
        'jalon.paye.artisan' => [
            'label' => 'Étape validée : paiement de l\'artisan',
            'domain' => 'etapes', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['etape' => 'Numéro de l\'étape'],
            'title' => 'Paiement reçu !',
            'body' => 'Le jalon #{etape} a été validé. Paiement en cours.',
            'sms' => true,
        ],
        'jalon.libere_auto.artisan' => [
            'label' => 'Étape libérée automatiquement après 72 h',
            'domain' => 'etapes', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['etape' => 'Numéro de l\'étape'],
            'title' => 'Paiement automatique reçu',
            'body' => "Le jalon #{etape} a été libéré automatiquement (le client n'a pas répondu sous 72h).",
            'sms' => true,
        ],
        'jalon.preuves_acceptees.artisan' => [
            'label' => 'Preuves d\'étape acceptées : paiement de l\'artisan',
            'domain' => 'etapes', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['etape' => 'Numéro de l\'étape'],
            'title' => 'Preuves acceptées !',
            'body' => 'Le client a accepté vos preuves pour le jalon #{etape}. Paiement en cours.',
            'sms' => true,
        ],

        // ─── Bons matériel (J-Code) ─────────────────────────────────────────
        'jcode.materiaux_arrives.client' => [
            'label' => 'Matériaux réceptionnés par l\'artisan',
            'domain' => 'jcode', 'audience' => 'client', 'type' => 'materials',
            'variables' => ['artisan' => 'Nom de l\'artisan', 'mission' => 'Numéro de la mission'],
            'title' => 'Vos matériaux sont arrivés !',
            'body' => "L'artisan {artisan} a reçu les matériaux pour votre mission #{mission}. Photo géolocalisée disponible.",
            'sms' => false,
        ],
        'jcode.materiaux_livres.artisan' => [
            'label' => 'Bon matériel entièrement servi',
            'domain' => 'jcode', 'audience' => 'artisan', 'type' => 'validation',
            'variables' => ['code' => 'Code du bon (PA-XXXX)'],
            'title' => 'Matériaux entièrement livrés',
            'body' => 'Le fournisseur a validé votre J-Code {code}. Tous les matériaux sont livrés. Paiement J+1 garanti.',
            'sms' => false,
        ],
        'jcode.materiaux_partiels.artisan' => [
            'label' => 'Bon matériel partiellement servi',
            'domain' => 'jcode', 'audience' => 'artisan', 'type' => 'validation',
            'variables' => ['code' => 'Code du bon (PA-XXXX)', 'montant' => 'Montant servi (FCFA)', 'restant' => 'Solde restant (FCFA)'],
            'title' => 'Matériaux partiellement livrés',
            'body' => 'Le fournisseur a servi une partie de votre J-Code {code} ({montant} FCFA). Solde restant : {restant} FCFA.',
            'sms' => false,
        ],
        'jcode.paiement_fournisseur' => [
            'label' => 'Paiement du fournisseur pour un bon servi',
            'domain' => 'jcode', 'audience' => 'fournisseur', 'type' => 'payment',
            'variables' => ['montant' => 'Montant net reçu (FCFA)', 'code' => 'Code du bon (PA-XXXX)'],
            'required' => ['montant'],
            'title' => 'Paiement J-Code recu',
            'body' => 'Vous avez recu {montant} FCFA pour le J-Code {code}.',
            'sms' => true,
        ],
        'jcode.fraude_gps.admin' => [
            'label' => 'Scan de J-Code hors de la zone de la boutique',
            'domain' => 'securite', 'audience' => 'admin', 'type' => 'alert',
            'variables' => ['code' => 'Code du bon', 'distance' => 'Distance mesurée (m)', 'max' => 'Distance maximale (m)'],
            'title' => 'Tentative de fraude J-Code',
            'body' => 'J-Code {code} scanné à {distance} m de la boutique (max {max} m).',
            'sms' => true,
        ],

        // ─── Commandes de matériaux ─────────────────────────────────────────
        'commande.nouvelle.fournisseur' => [
            'label' => 'Commande payée à préparer',
            'domain' => 'commandes', 'audience' => 'fournisseur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Montant de la commande (avec devise)'],
            'title' => 'Nouvelle commande reçue',
            'body' => "La commande #{commande} d'un montant de {montant} a été payée et est en attente de préparation.",
            'sms' => false,
        ],
        'commande.paiement_confirme.client' => [
            'label' => 'Paiement de commande confirmé',
            'domain' => 'commandes', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['montant' => 'Montant payé (avec devise)', 'commandes' => 'Numéro(s) de commande'],
            'title' => 'Paiement commande confirmé',
            'body' => 'Votre paiement de {montant} pour la commande #{commandes} est sécurisé en compte séquestre.',
            'sms' => false,
        ],
        'commande.annulee_non_payee.client' => [
            'label' => 'Commande annulée faute de paiement',
            'domain' => 'commandes', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commandes' => 'Numéro(s) de commande'],
            'title' => 'Commande annulée',
            'body' => "Votre commande #{commandes} n'a pas été réglée dans le délai imparti : elle est annulée et les articles sont remis en vente.",
            'sms' => false,
        ],
        'commande.paiement_apres_annulation.admin' => [
            'label' => 'Paiement arrivé après l\'annulation d\'une commande',
            'domain' => 'commandes', 'audience' => 'admin', 'type' => 'payment',
            'variables' => ['paiement' => 'Numéro du paiement', 'montant' => 'Montant (FCFA)', 'commandes' => 'Numéros des commandes annulées'],
            'title' => 'Paiement reçu pour une commande annulée',
            'body' => "Le paiement #{paiement} ({montant} FCFA) est arrivé après l'annulation des commandes #{commandes} et le stock ne permet plus de les honorer : remboursement du client à effectuer.",
            'sms' => false,
        ],
        'commande.prete_retrait.client' => [
            'label' => 'Commande prête au retrait (code de retrait)',
            'domain' => 'commandes', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'code' => 'Code de retrait'],
            'required' => ['code'],
            'title' => 'Commande prête pour retrait',
            'body' => 'Votre commande #{commande} est prête. Code de retrait à présenter au comptoir : {code}.',
            'sms' => false,
        ],
        'commande.a_remettre.fournisseur' => [
            'label' => 'Commande à remettre au client (code de retrait)',
            'domain' => 'commandes', 'audience' => 'fournisseur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'code' => 'Code de retrait'],
            'required' => ['code'],
            'title' => 'Commande à remettre au client',
            'body' => 'La commande #{commande} attend son retrait. Code à vérifier auprès du client : {code}.',
            'sms' => false,
        ],
        'commande.retiree.client' => [
            'label' => 'Commande retirée en magasin',
            'domain' => 'commandes', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande'],
            'title' => 'Commande récupérée',
            'body' => 'Votre commande #{commande} a été retirée en magasin. Merci de votre confiance !',
            'sms' => false,
        ],
        'commande.retrait_valide.fournisseur' => [
            'label' => 'Retrait validé : fournisseur crédité',
            'domain' => 'commandes', 'audience' => 'fournisseur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande'],
            'title' => 'Retrait validé',
            'body' => 'Le retrait de la commande #{commande} a été validé. Votre compte a été crédité.',
            'sms' => true,
        ],
        'commande.litige.fournisseur' => [
            'label' => 'Litige ouvert sur une commande',
            'domain' => 'litiges', 'audience' => 'fournisseur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'motif' => 'Motif du litige'],
            'title' => 'Litige ouvert sur la commande',
            'body' => 'Un litige a été ouvert par le client sur la commande #{commande} : {motif}.',
            'sms' => true,
        ],
        'commande.litige.livreur' => [
            'label' => 'Litige ouvert sur une livraison',
            'domain' => 'litiges', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande'],
            'title' => 'Litige ouvert sur la livraison',
            'body' => 'Un litige a été signalé pour la livraison de la commande #{commande}.',
            'sms' => true,
        ],
        'commande.litige_clos.client' => [
            'label' => 'Litige de commande clos (client)',
            'domain' => 'litiges', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'issue' => 'Décision rendue'],
            'title' => 'Litige clos sur votre commande',
            'body' => 'Le litige de la commande #{commande} est clos : {issue}.',
            'sms' => false,
        ],
        'commande.litige_clos.fournisseur' => [
            'label' => 'Litige de commande clos (fournisseur)',
            'domain' => 'litiges', 'audience' => 'fournisseur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'issue' => 'Décision rendue'],
            'title' => 'Litige clos sur la commande',
            'body' => 'Le litige de la commande #{commande} est clos : {issue}.',
            'sms' => false,
        ],
        'commande.litige_clos.livreur' => [
            'label' => 'Litige de commande clos (livreur)',
            'domain' => 'litiges', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'issue' => 'Décision rendue'],
            'title' => 'Litige clos sur la livraison',
            'body' => 'Le litige de la commande #{commande} est clos : {issue}.',
            'sms' => false,
        ],
        'commande.litige_rembourse.client' => [
            'label' => 'Litige de commande : client remboursé',
            'domain' => 'litiges', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Montant remboursé'],
            'title' => 'Votre réclamation est acceptée',
            'body' => 'Le litige de la commande #{commande} est clos en votre faveur : {montant} vous sont remboursés sur votre Mobile Money.',
            'sms' => false,
        ],
        'litige_commande.dette_creee.responsable' => [
            'label' => 'Remboursement dû après un litige de commande',
            'domain' => 'litiges', 'audience' => 'utilisateur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Montant dû'],
            'title' => 'Remboursement dû',
            'body' => 'Litige de la commande #{commande} : vous devez {montant} à ProsArtisan. Votre compte est bloqué jusqu\'au règlement ; la somme sera aussi prélevée sur vos prochains gains.',
            'sms' => false,
        ],
        'litige_commande.dette_prelevee.responsable' => [
            'label' => 'Prélèvement sur une dette de litige',
            'domain' => 'litiges', 'audience' => 'utilisateur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Montant prélevé', 'restant' => 'Restant dû'],
            'title' => 'Remboursement partiel enregistré',
            'body' => 'Litige de la commande #{commande} : {montant} ont été remboursés. Reste dû : {restant}.',
            'sms' => false,
        ],
        'litige_commande.dette_soldee.responsable' => [
            'label' => 'Dette de litige soldée',
            'domain' => 'litiges', 'audience' => 'utilisateur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande'],
            'title' => 'Remboursement soldé',
            'body' => 'La somme due pour le litige de la commande #{commande} est entièrement remboursée. Votre compte est débloqué.',
            'sms' => false,
        ],
        'litige_commande.dette_annulee.responsable' => [
            'label' => 'Dette de litige annulée',
            'domain' => 'litiges', 'audience' => 'utilisateur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande'],
            'title' => 'Remboursement annulé',
            'body' => 'ProsArtisan a annulé la somme due pour le litige de la commande #{commande}. Votre compte est débloqué.',
            'sms' => false,
        ],

        // ─── Livraisons et courses ──────────────────────────────────────────
        'course.disponible.livreur' => [
            'label' => 'Nouvelle course disponible',
            'domain' => 'livraisons', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['montant' => 'Prix estimé de la course (FCFA)', 'fournisseur' => 'Nom de la quincaillerie', 'commande' => 'Numéro de la commande'],
            'title' => 'Course de livraison disponible',
            'body' => 'Une nouvelle livraison de {montant} FCFA est disponible chez {fournisseur} (Commande #{commande}).',
            'sms' => false,
        ],
        'course.acceptee.livreur' => [
            'label' => 'Course acceptée par le livreur',
            'domain' => 'livraisons', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['adresse_fournisseur' => 'Adresse de la quincaillerie'],
            'title' => 'Course acceptée',
            'body' => 'Rendez-vous chez {adresse_fournisseur} pour récupérer la marchandise. Demandez le code de prise en charge au fournisseur.',
            'sms' => false,
        ],
        'course.livreur_en_route.fournisseur' => [
            'label' => 'Livreur en route vers la boutique (code de prise en charge)',
            'domain' => 'livraisons', 'audience' => 'fournisseur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'code' => 'Code de prise en charge'],
            'required' => ['code'],
            'title' => 'Livreur en route',
            'body' => 'Un livreur vient récupérer la commande #{commande}. Code de prise en charge à lui communiquer après contrôle : {code}.',
            'sms' => false,
        ],
        'course.livreur_en_route.client' => [
            'label' => 'Livreur en route vers la boutique',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['livreur' => 'Nom du livreur'],
            'title' => 'Livreur en route',
            'body' => 'Le livreur {livreur} a accepté votre livraison et se rend chez le fournisseur.',
            'sms' => false,
        ],
        'course.colis_recupere.livreur' => [
            'label' => 'Colis récupéré : adresse de livraison',
            'domain' => 'livraisons', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['adresse_client' => 'Adresse de livraison'],
            'title' => 'Colis récupéré',
            'body' => 'Colis récupéré. Livrez à : {adresse_client}. Le client vous remettra son code de réception une fois le colis en main.',
            'sms' => false,
        ],
        'course.colis_recupere.client' => [
            'label' => 'Colis récupéré par le livreur (code de réception)',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['livreur' => 'Nom du livreur', 'code' => 'Code de réception'],
            'required' => ['code'],
            'title' => 'Colis récupéré par le livreur',
            'body' => 'Le livreur {livreur} a récupéré votre colis chez le fournisseur. Code de réception secret : {code}.',
            'sms' => false,
        ],
        'course.livree.client' => [
            'label' => 'Commande livrée (course déjà réglée)',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'livreur' => 'Nom du livreur'],
            'title' => 'Livraison effectuée',
            'body' => 'Votre commande #{commande} a été livrée par {livreur}.',
            'sms' => false,
        ],
        'course.livree_a_regler.client' => [
            'label' => 'Commande livrée, course à régler',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Montant de la course (avec devise)', 'bonus' => 'Mention du bonus d\'attente (peut être vide)'],
            'required' => ['montant'],
            'title' => 'Livraison effectuée — course à régler',
            'body' => "Votre commande #{commande} a été livrée. Montant de la course : {montant}{bonus}. Réglez-la depuis l'application (Wave ou Orange Money).",
            'sms' => false,
        ],
        'course.terminee_creditee.livreur' => [
            'label' => 'Course terminée : gains crédités',
            'domain' => 'livraisons', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Montant de la course (avec devise)', 'bonus' => 'Mention du bonus d\'attente (peut être vide)'],
            'required' => ['montant'],
            'title' => 'Course terminée',
            'body' => 'Montant de la course #{commande} : {montant}{bonus}. Vos gains ont été crédités.',
            'sms' => true,
        ],
        'course.terminee_attente_paiement.livreur' => [
            'label' => 'Course terminée : gains crédités au paiement du client',
            'domain' => 'livraisons', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Montant de la course (avec devise)', 'bonus' => 'Mention du bonus d\'attente (peut être vide)'],
            'title' => 'Course terminée',
            'body' => 'Montant de la course #{commande} : {montant}{bonus}. Vos gains seront crédités dès le paiement du client.',
            'sms' => false,
        ],
        'course.reglee.livreur' => [
            'label' => 'Course réglée par le client : gains crédités',
            'domain' => 'livraisons', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Gains nets (FCFA)'],
            'required' => ['montant'],
            'title' => 'Course réglée',
            'body' => 'Le client a réglé la course #{commande}. {montant} FCFA ont été crédités sur votre portefeuille.',
            'sms' => true,
        ],
        'course.retiree.livreur' => [
            'label' => 'Course retirée au livreur (inactivité)',
            'domain' => 'livraisons', 'audience' => 'livreur', 'type' => 'fraud_alert',
            'variables' => ['commande' => 'Numéro de la commande', 'motif' => 'Motif du retrait'],
            'title' => 'Course retirée',
            'body' => 'Votre course #{commande} vous a été retirée pour {motif}. Veuillez être plus réactif.',
            'sms' => false,
        ],
        'course.changement_livreur.client' => [
            'label' => 'Nouveau livreur recherché',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande'],
            'title' => 'Changement de livreur',
            'body' => 'Un nouveau livreur est recherché pour votre commande #{commande}. Nous nous excusons pour le délai.',
            'sms' => false,
        ],
        'course.reaffectation.admin' => [
            'label' => 'Course réaffectée automatiquement',
            'domain' => 'livraisons', 'audience' => 'admin', 'type' => 'fraud_alert',
            'variables' => ['commande' => 'Numéro de la commande', 'tentative' => 'Numéro de la tentative', 'livreur_id' => 'Identifiant du livreur retiré', 'motif' => 'Motif'],
            'title' => 'Réaffectation livreur automatique',
            'body' => 'La commande #{commande} a été réaffectée (tentative {tentative}). Livreur retiré : #{livreur_id} — Motif : {motif}.',
            'sms' => false,
        ],
        'livraison.escalade_sans_livreur.admin' => [
            'label' => 'Commande sans livreur après les réaffectations',
            'domain' => 'livraisons', 'audience' => 'admin', 'type' => 'fraud_alert',
            'variables' => ['commande' => 'Numéro de la commande', 'reaffectations' => 'Nombre de réaffectations'],
            'title' => 'Commande sans livreur — escalade requise',
            'body' => 'La commande #{commande} a atteint {reaffectations} réaffectations sans succès. Intervention manuelle requise.',
            'sms' => true,
        ],
        'livraison.confirmation_requise.livreur' => [
            'label' => 'Relance du livreur sans signal GPS en cours de livraison',
            'domain' => 'livraisons', 'audience' => 'livreur', 'type' => 'delivery_alert',
            'variables' => ['commande' => 'Numéro de la commande', 'minutes' => 'Minutes sans signal'],
            'title' => 'Confirmation de livraison requise',
            'body' => "Commande #{commande} : votre position GPS n'a pas été actualisée depuis plus de {minutes} minutes. Veuillez confirmer que vous êtes en route vers le chantier.",
            // Règle d'or 53 : relance SMS/push du livreur en transit sans signal.
            'sms' => true,
        ],
        'livraison.livreur_immobile.admin' => [
            'label' => 'Livreur sans signal GPS en cours de livraison',
            'domain' => 'livraisons', 'audience' => 'admin', 'type' => 'delivery_stalled',
            'variables' => ['livreur_id' => 'Identifiant du livreur', 'livreur' => 'Nom du livreur', 'commande' => 'Numéro de la commande', 'minutes' => 'Minutes sans signal'],
            'title' => 'Livreur immobile en cours de livraison',
            'body' => "Le livreur #{livreur_id} ({livreur}) avec les matériaux de la commande #{commande} n'a pas actualisé sa position depuis {minutes} minutes. Séquestre préservé.",
            'sms' => true,
        ],
        'course.rappel_paiement.client' => [
            'label' => 'Rappel de course à régler',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['rappel' => 'Numéro du rappel', 'max' => 'Nombre maximal de rappels', 'commande' => 'Numéro de la commande', 'montant' => 'Montant de la course (avec devise)', 'avertissement' => 'Avertissement selon le rappel (peut être vide)'],
            'required' => ['montant'],
            'title' => 'Course à régler — rappel {rappel}/{max}',
            'body' => "La course de votre commande #{commande} ({montant}) reste à régler depuis l'application (Wave ou Orange Money). Régler une course en dehors de ProsArtisan enfreint les conditions d'utilisation. {avertissement}",
            'sms' => false,
        ],
        'course.compte_restreint.client' => [
            'label' => 'Compte restreint pour course impayée',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['commande' => 'Numéro de la commande', 'montant' => 'Montant de la course (avec devise)'],
            'title' => 'Compte restreint',
            'body' => "La course de votre commande #{commande} ({montant}) n'a pas été réglée malgré nos rappels. Vous ne pouvez plus commander ni publier de mission jusqu'à son paiement depuis l'application.",
            'sms' => false,
        ],
        'course.impayee.admin' => [
            'label' => 'Client restreint pour course impayée',
            'domain' => 'livraisons', 'audience' => 'admin', 'type' => 'delivery_fare_unpaid',
            'variables' => ['client' => 'Nom du client', 'client_id' => 'Identifiant du client', 'commande' => 'Numéro de la commande', 'montant' => 'Montant de la course (avec devise)', 'relances' => 'Nombre de relances'],
            'title' => 'Client restreint pour course impayée',
            'body' => '{client} (#{client_id}) : course de la commande #{commande} ({montant}) impayée après {relances} relances.',
            'sms' => false,
        ],
        'course.restriction_levee_paiement.client' => [
            'label' => 'Restriction levée après paiement de la course',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'title' => 'Restriction levée',
            'body' => 'Merci pour votre paiement. Vous pouvez de nouveau commander et publier des missions.',
            'sms' => false,
        ],
        'course.restriction_levee_support.client' => [
            'label' => 'Restriction levée par le support',
            'domain' => 'livraisons', 'audience' => 'client', 'type' => 'payment',
            'title' => 'Restriction levée',
            'body' => 'La restriction de votre compte a été levée par le support ProsArtisan.',
            'sms' => false,
        ],

        // ─── Litiges et jury ────────────────────────────────────────────────
        'litige.ouvert.partie' => [
            'label' => 'Litige ouvert sur une mission (autre partie)',
            'domain' => 'litiges', 'audience' => 'partie', 'type' => 'litige',
            'title' => 'Alerte litige',
            'body' => 'Un litige a été ouvert sur votre mission. Les fonds sont gelés jusqu’à décision.',
            'sms' => true,
        ],
        'litige.ouvert.admin' => [
            'label' => 'Nouveau litige à instruire',
            'domain' => 'litiges', 'audience' => 'admin', 'type' => 'litige',
            'variables' => ['litige' => 'Numéro du litige', 'mission' => 'Numéro de la mission'],
            'title' => 'Nouveau litige à instruire',
            'body' => 'Le litige #{litige} a été ouvert sur la mission #{mission}.',
            'sms' => false,
        ],
        'litige.pret_arbitrage.admin' => [
            'label' => 'Litige prêt pour arbitrage',
            'domain' => 'litiges', 'audience' => 'admin', 'type' => 'litige',
            'variables' => ['litige' => 'Numéro du litige'],
            'title' => 'Litige prêt pour arbitrage',
            'body' => 'Le litige #{litige} dispose désormais des preuves des deux parties.',
            'sms' => false,
        ],
        'litige.visite_referent_faite.admin' => [
            'label' => 'Visite du Référent enregistrée sur une mission en litige',
            'domain' => 'litiges', 'audience' => 'admin', 'type' => 'litige',
            'variables' => ['mission' => 'Numéro de la mission'],
            'title' => 'Visite du Référent enregistrée',
            'body' => 'Le Référent a visité le chantier de la mission #{mission}. Le litige peut être arbitré.',
            'sms' => false,
        ],
        'litige.decision.partie' => [
            'label' => 'Décision de litige rendue',
            'domain' => 'litiges', 'audience' => 'partie', 'type' => 'litige',
            'variables' => ['litige' => 'Numéro du litige', 'decision' => 'Décision rendue'],
            'title' => 'Décision de litige rendue',
            'body' => 'Le litige #{litige} a été traité: {decision}.',
            'sms' => false,
        ],
        'litige.statut.admin' => [
            'label' => 'Changement de statut d\'un litige',
            'domain' => 'litiges', 'audience' => 'admin', 'type' => 'litige',
            'variables' => ['litige' => 'Numéro du litige', 'statut' => 'Nouveau statut'],
            'title' => 'Litige mis à jour',
            'body' => 'Le litige #{litige} est désormais au statut {statut}.',
            'sms' => false,
        ],
        'litige.visite_referent.referent' => [
            'label' => 'Visite terrain du référent requise',
            'domain' => 'litiges', 'audience' => 'referent', 'type' => 'litige',
            'variables' => ['litige' => 'Numéro du litige'],
            'title' => 'Visite référent requise',
            'body' => 'Le litige #{litige} nécessite une visite terrain avant clôture.',
            'sms' => false,
        ],
        'jury.convocation.artisan' => [
            'label' => 'Convocation comme juré',
            'domain' => 'litiges', 'audience' => 'artisan', 'type' => 'jury_assignment',
            'variables' => ['litige' => 'Numéro du litige'],
            'title' => 'Arbitrage ProsArtisan requis',
            'body' => 'Vous avez été sélectionné comme juré pour évaluer de manière anonyme le litige #{litige}.',
            'sms' => false,
        ],
        'jury.remplacement.artisan' => [
            'label' => 'Convocation comme juré remplaçant',
            'domain' => 'litiges', 'audience' => 'artisan', 'type' => 'jury_assignment',
            'variables' => ['litige' => 'Numéro du litige'],
            'title' => 'Arbitrage ProsArtisan requis (Remplacement)',
            'body' => 'Vous avez été sélectionné en remplacement comme juré pour évaluer de manière anonyme le litige #{litige}.',
            'sms' => false,
        ],
        'jury.impossible.admin' => [
            'label' => 'Jury impossible à réunir',
            'domain' => 'litiges', 'audience' => 'admin', 'type' => 'litige',
            'variables' => ['litige' => 'Numéro du litige', 'jures' => 'Nombre de jurés éligibles', 'taille' => 'Taille du jury', 'score' => 'Score minimal requis'],
            'title' => 'Jury ProsArtisan impossible à réunir',
            'body' => "Le litige #{litige} ne compte que {jures} juré(s) éligible(s) sur {taille} (même métier, score ≥ {score}, KYC actif) : aucun jury n'a été convoqué, l'arbitrage revient à l'administrateur.",
            'sms' => false,
        ],
        'jury.jure_non_remplace.admin' => [
            'label' => 'Juré défaillant non remplacé',
            'domain' => 'litiges', 'audience' => 'admin', 'type' => 'litige',
            'variables' => ['litige' => 'Numéro du litige', 'score' => 'Score minimal requis'],
            'title' => 'Juré non remplacé',
            'body' => "Un juré du litige #{litige} n'a pas voté dans les 48 h et aucun artisan éligible (même métier, score ≥ {score}, KYC actif) ne peut le remplacer : le collège reste incomplet, arbitrage administrateur à prévoir si le consensus n'est pas atteint.",
            'sms' => false,
        ],
        'jury.sans_consensus.admin' => [
            'label' => 'Jury sans consensus',
            'domain' => 'litiges', 'audience' => 'admin', 'type' => 'litige',
            'variables' => ['litige' => 'Numéro du litige'],
            'title' => 'Arbitrage Jury sans consensus',
            'body' => "Le jury du litige #{litige} n'a pas atteint de majorité qualifiée 2/3. Dossier escaladé à l'administrateur.",
            'sms' => false,
        ],

        // ─── Compte et vérification d'identité ──────────────────────────────
        'kyc.documents_attendus.utilisateur' => [
            'label' => 'Compte créé : pièces KYC à fournir',
            'domain' => 'compte', 'audience' => 'utilisateur', 'type' => 'kyc',
            'title' => 'Compte en attente de validation',
            'body' => "Votre compte est en attente de validation KYC. Veuillez uploader vos documents (CNI et Selfie) dans l'application.",
            'sms' => false,
        ],
        'kyc.en_examen.utilisateur' => [
            'label' => 'Pièces KYC en cours d\'examen',
            'domain' => 'compte', 'audience' => 'utilisateur', 'type' => 'kyc',
            'title' => 'Compte en attente de validation',
            'body' => 'Votre compte est en attente de validation KYC. Nos équipes étudient actuellement vos pièces justificatives.',
            'sms' => false,
        ],
        'kyc.valide_auto.utilisateur' => [
            'label' => 'Identité vérifiée automatiquement',
            'domain' => 'compte', 'audience' => 'utilisateur', 'type' => 'kyc',
            'title' => 'Compte validé',
            'body' => 'Votre identité a été vérifiée automatiquement. Vous pouvez désormais effectuer vos transactions en toute sécurité.',
            'sms' => false,
        ],
        'kyc.valide.utilisateur' => [
            'label' => 'Dossier KYC validé par un administrateur',
            'domain' => 'compte', 'audience' => 'utilisateur', 'type' => 'kyc',
            'title' => 'Statut KYC mis à jour',
            'body' => 'Votre dossier KYC est validé. Vous pouvez maintenant effectuer des transactions.',
            'sms' => false,
        ],
        'kyc.rejete.utilisateur' => [
            'label' => 'Dossier KYC rejeté par un administrateur',
            'domain' => 'compte', 'audience' => 'utilisateur', 'type' => 'kyc',
            'variables' => ['motif' => 'Motif du rejet saisi par l\'administrateur'],
            'required' => ['motif'],
            'title' => 'Statut KYC mis à jour',
            'body' => 'Votre dossier KYC a été rejeté. Motif : {motif} Envoyez de nouvelles pièces depuis l\'application.',
            'sms' => false,
        ],
        'kyc.nouveau_profil.admin' => [
            'label' => 'Nouveau profil à vérifier',
            'domain' => 'compte', 'audience' => 'admin', 'type' => 'admin_alert',
            'variables' => ['nom' => 'Nom de l\'utilisateur', 'role' => 'Rôle'],
            'title' => 'Nouveau profil en attente KYC',
            'body' => 'Le profil de {nom} ({role}) nécessite une vérification KYC.',
            'sms' => false,
        ],
        'fournisseur.agree' => [
            'label' => 'Boutique agréée',
            'domain' => 'compte', 'audience' => 'fournisseur', 'type' => 'fournisseur',
            'title' => 'Décision sur votre agrément',
            'body' => 'Votre boutique est agréée. Vous pouvez scanner les J-Codes.',
            'sms' => false,
        ],
        'fournisseur.agrement_suspendu' => [
            'label' => 'Agrément de la boutique suspendu',
            'domain' => 'compte', 'audience' => 'fournisseur', 'type' => 'fournisseur',
            'title' => 'Décision sur votre agrément',
            'body' => 'Votre agrément fournisseur est suspendu. Contactez le support.',
            'sms' => false,
        ],

        // ─── Sécurité et fraude ─────────────────────────────────────────────
        'securite.contournement_bannissement.admin' => [
            'label' => 'Contournement de bannissement détecté',
            'domain' => 'securite', 'audience' => 'admin', 'type' => 'fraud_alert',
            'variables' => ['compte' => 'Identifiant du compte bloqué', 'telephone' => 'Téléphone du compte'],
            'title' => 'Tentative de contournement de bannissement détectée',
            'body' => 'Le compte #{compte} ({telephone}) a été bloqué automatiquement : appareil déjà associé à un compte précédemment banni.',
            'sms' => true,
        ],

        'auth.otp' => [
            'label' => 'Code de vérification (OTP) : connexion, étape, changement de numéro',
            'domain' => 'securite', 'audience' => 'utilisateur', 'type' => 'otp',
            'variables' => ['code' => 'Code à usage unique', 'minutes' => 'Durée de validité (minutes)'],
            'required' => ['code', 'minutes'],
            'title' => 'Code de vérification ProsArtisan',
            'body' => 'Votre code de vérification ProsArtisan est: {code}.',
            // L'avertissement est la seule parade au hameçonnage par téléphone,
            // où un faux support réclame le code reçu.
            'sms_body' => 'Votre code de vérification ProsArtisan est: {code}. Valide {minutes} minutes. Ne le communiquez jamais : ProsArtisan ne vous le demandera pas.',
            'sms' => true,
            'in_app' => false,
            'push' => false,
            // Règle d'or 39 : le code part uniquement par SMS (route `otp`).
            'locked' => ['in_app' => false, 'push' => false, 'sms' => true],
            'direct' => true,
        ],
        'securite.changement_appareil.artisan' => [
            'label' => 'Connexion depuis un nouvel appareil (score gelé)',
            'domain' => 'securite', 'audience' => 'artisan', 'type' => 'security_alert',
            'title' => 'Alerte sécurité : Changement d\'appareil suspect',
            'body' => 'Un changement suspect d\'appareil (IMEI) a été détecté. Votre Score ProsArtisan est gelé par mesure de sécurité.',
            'sms' => true,
        ],

        // ─── Retraits, versements et crédit ─────────────────────────────────
        'retrait_livreur.verse' => [
            'label' => 'Retrait des gains livreur versé',
            'domain' => 'finances', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['reference' => 'Référence du retrait', 'montant' => 'Montant net versé (FCFA)'],
            'required' => ['montant'],
            'title' => 'Retrait versé',
            'body' => 'Votre retrait {reference} de {montant} FCFA a été versé.',
            'sms' => true,
        ],
        'retrait_livreur.refuse' => [
            'label' => 'Retrait des gains livreur refusé',
            'domain' => 'finances', 'audience' => 'livreur', 'type' => 'payment',
            'variables' => ['reference' => 'Référence du retrait', 'motif' => 'Motif du refus'],
            'title' => 'Retrait refusé',
            'body' => 'Votre demande de retrait {reference} a été refusée : {motif}. Le montant reste disponible sur votre portefeuille.',
            'sms' => false,
        ],
        'versement.echec.beneficiaire' => [
            'label' => 'Virement Mobile Money non abouti (fonds sur le portefeuille)',
            'domain' => 'finances', 'audience' => 'beneficiaire', 'type' => 'payment',
            'variables' => ['montant' => 'Montant du virement (FCFA)', 'contexte' => 'Objet du virement'],
            'title' => 'Virement Mobile Money en attente',
            'body' => "Le virement de {montant} FCFA ({contexte}) n'a pas abouti. Les fonds restent sur votre portefeuille et le virement sera relancé automatiquement. Vérifiez votre numéro de paiement.",
            'sms' => false,
        ],
        'versement.echec.rembourse' => [
            'label' => 'Remboursement Mobile Money non abouti',
            'domain' => 'finances', 'audience' => 'client', 'type' => 'payment',
            'variables' => ['montant' => 'Montant du virement (FCFA)', 'contexte' => 'Objet du virement'],
            'title' => 'Virement Mobile Money en attente',
            'body' => "Le virement de {montant} FCFA ({contexte}) n'a pas abouti. La somme vous reste due et le virement sera relancé automatiquement. Vérifiez votre numéro de paiement.",
            'sms' => false,
        ],
        'credit.accorde.artisan' => [
            'label' => 'Micro-crédit accordé et débloqué',
            'domain' => 'finances', 'audience' => 'artisan', 'type' => 'credit',
            'variables' => ['montant' => 'Montant du crédit (FCFA)'],
            'required' => ['montant'],
            'title' => 'Demande de crédit soumise',
            'body' => 'Votre demande de crédit de {montant} FCFA a été approuvée et débloquée sous 2h sur votre compte Mobile Money.',
            'sms' => true,
        ],
        'credit.retenue_jalon.artisan' => [
            'label' => 'Retenue de remboursement du micro-crédit sur une étape',
            'domain' => 'finances', 'audience' => 'artisan', 'type' => 'credit',
            'variables' => ['montant' => 'Montant retenu (FCFA)', 'etape' => 'Numéro de l\'étape', 'restant' => 'Solde restant dû (FCFA)'],
            'title' => 'Amortissement micro-crédit',
            'body' => 'Une retenue de {montant} FCFA a été prélevée sur votre jalon #{etape} au titre du remboursement de votre micro-crédit. Solde restant : {restant} FCFA.',
            'sms' => false,
        ],
        'credit.remboursement.artisan' => [
            'label' => 'Remboursement anticipé du micro-crédit',
            'domain' => 'finances', 'audience' => 'artisan', 'type' => 'credit',
            'variables' => ['montant' => 'Montant remboursé (FCFA)', 'restant' => 'Solde restant dû (FCFA)'],
            'title' => 'Remboursement micro-crédit validé',
            'body' => 'Votre remboursement de {montant} FCFA a été enregistré avec succès. Solde restant dû : {restant} FCFA.',
            'sms' => false,
        ],

        // ─── Recrutement ────────────────────────────────────────────────────
        'recrutement.candidature.recruteur' => [
            'label' => 'Nouvelle candidature à une offre',
            'domain' => 'recrutement', 'audience' => 'recruteur', 'type' => 'recruitment',
            'variables' => ['artisan' => 'Nom de l\'artisan', 'offre' => 'Titre de l\'offre'],
            'title' => 'Nouvelle candidature',
            'body' => '{artisan} a postulé à votre offre « {offre} ».',
            'sms' => false,
        ],
        'recrutement.note_vocale_retenue.artisan' => [
            'label' => 'Note vocale de candidature non transmise',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'recruitment',
            'variables' => ['offre' => 'Titre de l\'offre'],
            'title' => 'Note vocale non transmise',
            'body' => "Votre note vocale pour « {offre} » contenait des coordonnées : elle n'a pas été transmise au recruteur. Votre candidature reste valable.",
            'sms' => false,
        ],
        'recrutement.candidature_maj.artisan' => [
            'label' => 'Candidature retenue ou écartée',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'recruitment',
            'variables' => ['offre' => 'Titre de l\'offre', 'statut' => 'Nouveau statut de la candidature'],
            'title' => 'Candidature mise à jour',
            'body' => 'Votre candidature à « {offre} » a été {statut}.',
            'sms' => false,
        ],
        'recrutement.demande_rappel.artisan' => [
            'label' => 'Demande de rappel d\'un recruteur',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'recruitment',
            'variables' => ['recruteur' => 'Nom du recruteur', 'offre' => 'Titre de l\'offre', 'telephone' => 'Téléphone du recruteur'],
            'required' => ['telephone'],
            'title' => 'Demande de rappel',
            'body' => '{recruteur} recrute pour « {offre} » et souhaite que vous le rappeliez au {telephone}.',
            'sms' => false,
        ],
        'recrutement.offre_publiee.recruteur' => [
            'label' => 'Offre validée et publiée',
            'domain' => 'recrutement', 'audience' => 'recruteur', 'type' => 'recruitment',
            'variables' => ['offre' => 'Titre de l\'offre'],
            'title' => 'Offre publiée',
            'body' => 'Votre offre « {offre} » a été validée et est maintenant visible par les artisans.',
            'sms' => false,
        ],
        'recrutement.offre_rejetee.recruteur' => [
            'label' => 'Offre rejetée sans motif',
            'domain' => 'recrutement', 'audience' => 'recruteur', 'type' => 'recruitment',
            'variables' => ['offre' => 'Titre de l\'offre'],
            'title' => 'Offre rejetée',
            'body' => "Votre offre « {offre} » a été rejetée par l'équipe ProsArtisan.",
            'sms' => false,
        ],
        'recrutement.offre_rejetee_motif.recruteur' => [
            'label' => 'Offre rejetée avec motif',
            'domain' => 'recrutement', 'audience' => 'recruteur', 'type' => 'recruitment',
            'variables' => ['offre' => 'Titre de l\'offre', 'motif' => 'Motif du rejet'],
            'title' => 'Offre rejetée',
            'body' => 'Votre offre « {offre} » a été rejetée : {motif}',
            'sms' => false,
        ],
        'recrutement.proposition.artisan' => [
            'label' => 'Proposition d\'engagement',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'recruitment',
            'variables' => ['recruteur' => 'Nom du recruteur', 'offre' => 'Titre de l\'offre', 'tarif' => 'Tarif journalier (FCFA)', 'jours' => 'Nombre de jours'],
            'title' => 'Proposition de mission',
            'body' => '{recruteur} vous propose un engagement sur « {offre} » — {tarif} FCFA/jour sur {jours} jour(s). Acceptez-le pour démarrer.',
            'sms' => false,
        ],
        'recrutement.engagement_accepte.recruteur' => [
            'label' => 'Engagement accepté, séquestre à payer',
            'domain' => 'recrutement', 'audience' => 'recruteur', 'type' => 'recruitment',
            'variables' => ['artisan' => 'Nom de l\'artisan'],
            'title' => 'Engagement accepté',
            'body' => '{artisan} a accepté votre proposition. Payez le séquestre pour démarrer la mission.',
            'sms' => false,
        ],
        'recrutement.engagement_accepte_finance.recruteur' => [
            'label' => 'Engagement accepté, séquestre déjà couvert',
            'domain' => 'recrutement', 'audience' => 'recruteur', 'type' => 'recruitment',
            'variables' => ['artisan' => 'Nom de l\'artisan'],
            'title' => 'Engagement accepté',
            'body' => "{artisan} a accepté votre proposition. Le séquestre déjà payé couvre l'intégralité de la mission : elle démarre immédiatement.",
            'sms' => false,
        ],
        'recrutement.engagement_refuse.recruteur' => [
            'label' => 'Engagement refusé',
            'domain' => 'recrutement', 'audience' => 'recruteur', 'type' => 'recruitment',
            'variables' => ['artisan' => 'Nom de l\'artisan'],
            'title' => 'Engagement refusé',
            'body' => "{artisan} a refusé la proposition d'engagement.",
            'sms' => false,
        ],
        'recrutement.trop_percu.recruteur' => [
            'label' => 'Trop-perçu du séquestre remboursé',
            'domain' => 'recrutement', 'audience' => 'recruteur', 'type' => 'payment',
            'variables' => ['montant' => 'Montant remboursé (FCFA)'],
            'required' => ['montant'],
            'title' => 'Trop-perçu remboursé',
            'body' => "Le trop-perçu de {montant} FCFA sur le séquestre d'accès aux candidatures a été crédité sur votre portefeuille.",
            'sms' => true,
        ],
        'recrutement.sequestre_deja_regle.artisan' => [
            'label' => 'Séquestre déjà réglé à l\'acceptation',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['offre' => 'Titre de l\'offre'],
            'title' => 'Séquestre payé',
            'body' => 'Le séquestre de votre mission « {offre} » est déjà réglé. Vous pouvez commencer à travailler.',
            'sms' => false,
        ],
        'recrutement.sequestre_paye.artisan' => [
            'label' => 'Séquestre payé par le recruteur',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['offre' => 'Titre de l\'offre'],
            'title' => 'Séquestre payé',
            'body' => 'Le recruteur a payé le séquestre de votre mission « {offre} ». Vous pouvez commencer à travailler.',
            'sms' => false,
        ],
        'recrutement.journee_payee.artisan' => [
            'label' => 'Journée de travail payée',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['jour' => 'Numéro du jour', 'montant' => 'Montant versé (FCFA)'],
            'required' => ['montant'],
            'title' => 'Journée payée',
            'body' => 'Le jour {jour} a été validé : {montant} FCFA versés sur votre portefeuille.',
            'sms' => true,
        ],
        'recrutement.mission_terminee.artisan' => [
            'label' => 'Dernière journée payée, mission terminée',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'payment',
            'variables' => ['jour' => 'Numéro du jour', 'montant' => 'Montant versé (FCFA)'],
            'required' => ['montant'],
            'title' => 'Mission terminée — dernier paiement reçu',
            'body' => 'Le jour {jour} a été validé : {montant} FCFA versés sur votre portefeuille. Cette mission est maintenant terminée.',
            'sms' => true,
        ],
        'recrutement.contrat_prolonge.artisan' => [
            'label' => 'Engagement prolongé',
            'domain' => 'recrutement', 'audience' => 'artisan', 'type' => 'recruitment',
            'variables' => ['jours' => 'Jours ajoutés', 'offre' => 'Titre de l\'offre'],
            'title' => 'Contrat prolongé',
            'body' => 'Le recruteur a ajouté {jours} jour(s) à votre mission « {offre} ». Le paiement du complément est en attente.',
            'sms' => false,
        ],

        // ─── Parrainage ─────────────────────────────────────────────────────
        'parrainage.recompense' => [
            'label' => 'Récompense de parrainage (code promo)',
            'domain' => 'parrainage', 'audience' => 'client', 'type' => 'referral_reward',
            'variables' => ['code' => 'Code promo offert'],
            'required' => ['code'],
            'title' => 'Récompense de parrainage',
            'body' => 'Félicitations ! Votre filleul a financé sa première mission. Voici votre code promo : {code}',
            'sms' => false,
        ],
        // Chantier 15 — annuaire artisans du site vitrine.
        'annuaire.disponibilite_validee.artisan' => [
            'label' => 'Disponibilité validée et publiée dans l\'annuaire',
            'domain' => 'annuaire', 'audience' => 'artisan', 'type' => 'directory',
            'title' => 'Disponibilité publiée',
            'body' => 'Votre disponibilité a été validée : elle est désormais affichée dans l\'annuaire ProsArtisan.',
            'sms' => false,
        ],
        'annuaire.disponibilite_refusee.artisan' => [
            'label' => 'Disponibilité refusée par un administrateur',
            'domain' => 'annuaire', 'audience' => 'artisan', 'type' => 'directory',
            'variables' => ['motif' => 'Motif du refus'],
            'required' => ['motif'],
            'title' => 'Disponibilité non publiée',
            'body' => 'Votre disponibilité n\'a pas été validée : {motif}. Votre disponibilité précédente reste affichée.',
            'sms' => false,
        ],
        'annuaire.retire.artisan' => [
            'label' => 'Fiche retirée de l\'annuaire',
            'domain' => 'annuaire', 'audience' => 'artisan', 'type' => 'directory',
            'variables' => ['motif' => 'Motif du retrait'],
            'required' => ['motif'],
            'title' => 'Fiche retirée de l\'annuaire',
            'body' => 'Votre fiche n\'apparaît plus dans l\'annuaire ProsArtisan : {motif}. Votre compte et vos missions ne sont pas concernés.',
            'sms' => false,
        ],
        'annuaire.reactive.artisan' => [
            'label' => 'Fiche de nouveau visible dans l\'annuaire',
            'domain' => 'annuaire', 'audience' => 'artisan', 'type' => 'directory',
            'title' => 'Fiche de nouveau visible',
            'body' => 'Votre fiche apparaît de nouveau dans l\'annuaire ProsArtisan.',
            'sms' => false,
        ],
    ];
}
