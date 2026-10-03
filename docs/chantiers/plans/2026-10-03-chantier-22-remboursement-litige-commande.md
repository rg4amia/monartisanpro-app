# Plan — Chantier 22 : gel, remboursement et recouvrement d'un litige de commande

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude Code |
| Analyses liées | `2026-10-03-chantier-21-complements-historiques.md` (écart « clôture sans mouvement de fonds ») |
| Commits | — |

## Objectif

Donner un effet financier au litige de commande : geler la somme contestée, rembourser le client quand sa réclamation est acceptée, faire supporter le remboursement au responsable, et recouvrer la somme s'il l'a déjà retirée.

## État actuel (lecture du code)

- Le fournisseur est crédité au retrait de la marchandise (prix des articles moins 5 % de commission), donc avant la livraison.
- Le client peut ouvrir un litige pendant 30 minutes après la livraison.
- Le litige ne gèle rien : le fournisseur peut retirer la somme, le virement automatique du lendemain part.
- La clôture (Chantier 21) consigne la décision sans déplacer de fonds.
- La course est payée à part par le client après la livraison ; le livreur est crédité moins 10 % de commission.

## Décisions prises (03/10/2026)

1. **Gel** : le montant de la commande contestée est gelé chez le fournisseur dès l'ouverture du litige. Si la somme a déjà été retirée :
   - le responsable est notifié qu'il doit rembourser la somme litigieuse ;
   - la somme est prélevée sur ses fonds en séquestre et sur ses prochains gains ;
   - toute nouvelle assignation et toute autre action de son profil sont bloquées jusqu'au remboursement.
2. **Montant** : fixé par l'administrateur, plafonné au prix des articles ; remboursement partiel possible ; les frais de service de 3 % ne sont pas remboursés.
3. **Responsable** : désigné par l'administrateur à la clôture, fournisseur ou livreur.
4. **Course** : annulée ou remboursée quand la faute est au livreur ; due dans les autres cas.

## Interprétations confirmées (03/10/2026)

- **« L'artisan »** dans la décision 1 désigne le responsable du litige : le fournisseur ou le livreur désigné à la clôture.
- **ProsArtisan avance le remboursement** du client tout de suite, puis récupère la somme auprès du responsable.
- **Règlement direct** par Wave ou Orange Money validé, pour que le responsable puisse se débloquer lui-même.

## Périmètre

- Inclus : gel, remboursement, dette du responsable, recouvrement, blocage du profil, règlement direct de la dette, écrans du backoffice et de l'application, documentation.
- Exclu : litiges de mission (déjà traités par `LitigeService`) ; modification du délai de 30 minutes ; pénalités de score.

## Étapes

### Lot A — Gel à l'ouverture du litige

1. `order_disputes` : colonnes `frozen_amount`, `frozen_user_id`.
2. À l'ouverture, le montant net crédité au fournisseur pour cette commande est gelé : `SupplierCashoutService::getAvailableBalance` le soustrait du solde retirable, et le virement automatique du lendemain ne le verse pas.
3. Le gel est limité à ce que le portefeuille contient encore : la part déjà retirée n'est pas gelée, elle sera recouvrée (lot C).
4. À la clôture, le gel est levé (réclamation rejetée) ou consommé par le remboursement (réclamation acceptée).

### Lot B — Clôture avec remboursement

5. La clôture « réclamation acceptée » demande à l'administrateur :
   - le **montant remboursé**, de 1 FCFA au prix des articles (`orders.subtotal`), établi et plafonné par le serveur ;
   - le **responsable** : fournisseur ou livreur (le livreur n'est proposé que si la commande en a un).
6. Le client est remboursé par le circuit des versements existant (`MobileMoneyPayoutService`, contexte « remboursement au client ») : débit au seul virement réussi, relance automatique, choix du moyen de réception par le client.
7. Les frais de service de 3 % restent acquis à ProsArtisan. La commission de 5 % prélevée sur le fournisseur lui est rendue au prorata du montant remboursé.
8. Livreur responsable : la course de cette commande est annulée si elle n'est pas encore payée, remboursée au client si elle l'est ; le gain du livreur sur cette course est repris.
9. Clôture « réclamation rejetée » : inchangée, le gel est levé.

### Lot C — Dette du responsable et recouvrement

10. Table `order_dispute_debts` : litige, débiteur, montant dû, montant recouvré, état (`en_cours`, `soldee`), et `order_dispute_debt_entries` (historique append-only de chaque recouvrement).
11. À la clôture, la part du remboursement que le portefeuille du responsable ne couvre pas devient une dette. ProsArtisan avance cette part au client (voir « Interprétations à confirmer »).
12. **Recouvrement automatique** : tout crédit ultérieur du portefeuille du débiteur (vente au retrait d'une commande, gain de course) solde la dette en priorité, avec une écriture de ledger par prélèvement.
13. **Règlement direct** : le débiteur peut rembourser par Wave ou Orange Money (`POST /api/v1/dispute-debts/{debt}/pay`, montant fixé par le serveur). Sans cette voie, un compte bloqué ne pourrait plus jamais se débloquer.
14. Notifications : dette créée (avec le montant et le motif), chaque prélèvement, dette soldée.

### Lot D — Blocage du profil débiteur

15. Tant qu'une dette est `en_cours` :
    - **fournisseur** : plus de nouvelle commande ni de nouveau bon matériel à servir, boutique retirée de la liste des fournisseurs, retrait de fonds refusé ;
    - **livreur** : plus de course proposée ni acceptée, retrait de gains refusé.
16. Les commandes et courses déjà engagées vont à leur terme (Règle d'or 53 : une marchandise retirée n'est jamais abandonnée).
17. Le serveur est seul juge (middleware `dispute.debt_free` sur les routes concernées) ; l'application affiche un bandeau « Remboursement dû » avec le montant et le bouton de règlement.
18. Le blocage tombe dès que la dette est soldée, sans intervention de l'administrateur.

### Lot E — Backoffice

19. Fiche de la commande : la clôture « Accepter la réclamation » ouvre une fenêtre avec le montant (plafond affiché), le responsable et le motif.
20. Onglet Transactions, sous-onglet **Dettes de litige** : dettes en cours et soldées, montant recouvré, historique ; action « Annuler la dette » (motif obligatoire, capacité `admin.transactions.manage`, auditée).

### Lot F — Application mobile

21. Client : le remboursement apparaît dans « Litiges de commandes » et dans le portefeuille.
22. Fournisseur et livreur : bandeau de dette, écran de règlement, historique des prélèvements.

### Lot G — Documentation

23. Manuel, `CLAUDE.md`, `AGENTS.md`, `PRD.md`, ce plan, `CHRONOLOGIE.md`. Correction du texte de l'application « Un litige bloque temporairement les paiements », qui deviendra exact.

## Règles d'or concernées

- 9 et 73 : chaque gel, remboursement et prélèvement est une écriture de ledger équilibrée, jamais un ajustement de solde.
- 17 : clôture, annulation de dette et règlement audités.
- 36 : montant et plafond établis par le serveur ; plafond cumulatif (un litige ne rembourse jamais plus que le prix des articles) ; dette propre à son débiteur.
- 53 : une course en transit n'est jamais interrompue par le blocage.
- 74 : remboursement du client par le circuit des versements, débit au seul virement réussi.
- 80 : notifications par événements du catalogue.
- 55 : migrations défensives.

## Vérification

- Pest (SQLite et MariaDB 11.8) : gel et levée, remboursement partiel et total, plafond, responsable fournisseur puis livreur, fonds déjà retirés (dette), recouvrement sur crédit ultérieur, règlement direct, blocage et déblocage, idempotence de la clôture, rapprochement de trésorerie (`prosartisan:reconcile-treasury`) sans écart.
- Vitest : fenêtre de clôture, sous-onglet des dettes.
- Flutter : bandeau, écran de règlement.
- Contrôle manuel : un litige complet avec remboursement réel sur un compte de test.

## Écarts

- **Suivi des dettes dans le backoffice** : bloc « Dettes de litige de commande » du sous-onglet Encaissements, et non un sous-onglet à part.
- **Notifications en push seul** : la liste des SMS envoyés par défaut est figée (Règle d'or 80) ; aucun SMS n'a été ajouté pour la dette. Chaque message reste activable en SMS depuis l'onglet « Messages push & SMS ».
- **Avance de ProsArtisan** : le remboursement part du compte financier de ProsArtisan (celui qui reçoit les commissions). Si son solde ne couvre pas l'avance, le virement échoue, est relancé automatiquement, et peut être soldé à la main par un administrateur (« marquer versé »).
- **Recouvrement automatique** : déclenché au crédit d'une vente de commande et d'un gain de course. Le paiement d'un bon matériel (J-Code), viré directement sur le Mobile Money du fournisseur, n'est pas prélevé.
- **Gel** : il ne porte que sur le fournisseur. Rien n'est gelé chez le livreur à l'ouverture, le responsable n'étant connu qu'à la clôture.
- **Livreur débiteur** : il ne peut plus accepter de course ni retirer ses gains ; la liste des courses disponibles reste consultable.
- **Historique des prélèvements** : affiché dans l'écran « Remboursements dus » de l'application, résumé (nombre d'opérations, dernière source) dans le backoffice.
- **Rapprochement de trésorerie** : le test existant de `prosartisan:reconcile-treasury` passe ; aucun scénario de litige remboursé n'y a été ajouté.
- **Contrôle manuel** : non effectué (un litige complet avec un remboursement réel sur un compte de test).
