# Plan — Chantier 41 : fichiers des utilisateurs privés par défaut

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle sur le serveur et sur appareil à faire) |
| Créé le | 2026-10-06 |
| Mis à jour le | 2026-10-06 |
| Auteur | Claude Code (Opus 5.5) |
| Analyses liées | `../audits/2026-10-06-audit-etat-du-projet-apres-chantier-39.md` (anomalie 4), `../audits/2026-10-06-audit-securite-api-et-deploiement.md` (constat 8) |
| Commits | — |

## Objectif

Appliquer la décision produit du 06/10/2026 : **seules les images de catalogue et les réalisations d'un artisan sont publiques ; tout le reste est privé.** Les fichiers que les utilisateurs envoient ne doivent plus vivre à une adresse permanente, sans que les versions installées de l'application aient à changer.

## Périmètre

- Inclus : photos de retrait et de livraison d'une commande, reçus de bons matériels, photos d'étape, de bon matériel et de litige, preuves du coffre, médias de discussion de chantier, photos et vidéos d'une demande de mission ; `composer audit` ; routage des liens signés en production.
- Exclu :
  - les contenus publiés par ProsArtisan : images et vidéos de la vitrine, annonces vocales du module Communication du backoffice — **publics, décision du 06/10/2026** ;
  - les réalisations d'un artisan : aucun envoi de ce type n'existe aujourd'hui dans l'application ;
  - l'application mobile : aucun changement nécessaire.

## Étapes

1. **Mécanisme commun** — `App\Support\PrivateMedia` : enregistrement sur le disque `local` sous `media/`, nom aléatoire de quarante caractères, extension tirée du contenu ; adresse canonique sans signature ; lien signé de 120 minutes (`prosartisan.private_media.url_ttl_minutes`) ; route `media.private.file` servie par `PrivateMediaController`.
2. **Conversions Eloquent** — `PrivateMediaUrl`, `PrivateMediaUrlList`, `PrivateMediaPayload` : adresse canonique en base, lien signé neuf à chaque lecture. En écriture, une adresse privée n'entre que si sa signature est encore valide ou si elle est déjà celle enregistrée.
3. **Colonnes raccordées** — `orders.pickup_photo_url` et `delivery_photo_url`, `jcodes.photo_materiaux_url`, `jcode_redemptions.recu_photo_url`, `jalons.photos_json`, `missions.photos_json`, `litige_evidences.media_url`, `mission_messages.media_url`, `mission_realtime_events.payload_json`.
4. **Envois raccordés** — `DeliveryTrackingController` (retrait, livraison), `JCodeController` (reçu), `PhotoService` (étape, bon matériel, litige), `MissionChatController`, `UploadController`, `EvidenceVaultService`.
5. **Téléversement générique** — privé, sauf l'image de catalogue d'un fournisseur (`usage=catalogue`, ou rôle fournisseur pour les versions installées). Un client qui annonce l'usage catalogue reste en privé.
6. **Lecture par le serveur** — `GeminiService` (analyse des photos d'étape, télé-expertise) et `EvidenceVaultService` (empreinte) lisent le disque privé, avec repli sur le disque public pour l'existant.
7. **Routage de production** — préfixes `/receipts/`, `/recruitment/` et `/media/` ajoutés à la règle 2 du `.htaccess` racine.
8. **Dépendances PHP** — `laravel/framework` 12.64.0 → 12.69.3, `league/flysystem` 3.35.2 → 3.36.0 et leurs dépendances.

## Règles d'or concernées

- 40 : un document sensible ne vit pas à une adresse publique permanente.
- 36 : une adresse privée n'est acceptée que sur une signature valide ; un lien ne se rafraîchit pas en le renvoyant.
- 117 : extension d'enregistrement tirée du contenu, jamais du nom transmis.
- 29 : une photo dont le lien est refusé n'est pas remplacée ; elle est retirée.
- 55 : aucune migration ; les fichiers existants sur le disque public restent lisibles à leur ancienne adresse.

## Vérification

- `Chantier41PrivateMediaTest.php` (8 tests) : lien signé servi, adresse canonique refusée (403), expiration, écriture refusée sans signature valide, image de catalogue restée publique, chemin remontant les dossiers jamais servi.
- Suite Pest complète : 1 400 tests. `Chantier37CriticalApiFixesTest` et `Chantier12JuryAndVaultTest` lisent désormais le disque privé.
- `composer audit --locked` : aucune alerte.
- **Sur le serveur, après déploiement** :
  - `curl -I https://prosartisan.net/media/prive/chat/1/x.jpg` répond 403 (réponse de Laravel, pas la page de l'hébergeur) ;
  - `curl -H 'Accept: application/json' https://prosartisan.net/receipts/transactions/999999` répond en JSON.
- **Sur appareil** : envoyer une photo dans une discussion et la revoir ; créer une demande de mission avec photo ; valider un retrait avec photo ; ouvrir un reçu PDF.

## Écarts

- Aucun fichier n'est à déplacer en production : les dossiers concernés du disque public n'existaient pas (contrôle du 06/10/2026). Sur un poste de développement, les anciens fichiers restent à leur adresse publique.
- Un lien gardé en cache par l'application expire au bout de deux heures : les photos d'une liste relue hors connexion après ce délai ne s'affichent plus tant que la liste n'est pas rechargée. Durée réglable par `PRIVATE_MEDIA_URL_TTL_MINUTES`.
- Le lien signé posté par l'application à la création d'une mission doit être encore valide : au-delà de deux heures entre l'envoi de la photo et la validation de la demande, la photo est écartée.
- La distinction catalogue / mission repose, pour les versions installées, sur le rôle de l'expéditeur. Une prochaine version de l'application devrait envoyer `usage`.
