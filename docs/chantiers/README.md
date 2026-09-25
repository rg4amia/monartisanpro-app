# Journal des chantiers

Ce dossier conserve, dans le dépôt, les **plans d'implémentation**, les **analyses** et les **audits** de ProsArtisan. Il permet à tout agent (Claude Code, Antigravity, Cursor, Kiro…) comme à tout développeur de retrouver ce qui a été prévu, décidé et livré, et dans quel ordre.

Un plan resté dans le dossier privé d'un outil est invisible pour les autres : le plan du Chantier 5 (KYC Gemini) n'existait que dans le dossier interne de l'IDE Antigravity, et aucun autre agent n'a pu le retrouver quand il a fallu « poursuivre le plan ». Tout plan, analyse ou audit doit donc être enregistré ici.

## Organisation

| Dossier | Contenu |
| --- | --- |
| `plans/` | Plans d'implémentation d'un chantier, avant et pendant sa réalisation |
| `analyses/` | Analyses de projet, d'existant ou d'impact (backlog, écarts, options techniques) |
| `audits/` | Audits de conformité, de sécurité ou de vérification d'un plan livré |
| `_modeles/` | Modèles à copier pour chaque type de document |
| `CHRONOLOGIE.md` | Index chronologique de tous les documents, du plus récent au plus ancien |

## Règles

1. **Lire avant d'agir.** Avant de commencer ou de reprendre un chantier, lire `CHRONOLOGIE.md` puis le plan concerné et ses audits.
2. **Nommer par date.** Un fichier s'appelle `AAAA-MM-JJ-<sujet-en-kebab-case>.md`, daté du jour de sa création : l'ordre alphabétique du dossier suit alors la chronologie.
3. **Partir d'un modèle.** Copier le modèle de `_modeles/` correspondant et renseigner l'en-tête (statut, dates, auteur, commits).
4. **Tenir le statut à jour.** Un plan passe par `proposé` → `en cours` → `livré` (ou `partiel`, `abandonné`). Le statut change dans l'en-tête du document **et** dans `CHRONOLOGIE.md`, dans le même commit que le code concerné.
5. **Ne jamais réécrire l'histoire.** Un plan livré ne se modifie plus : un écart découvert ensuite fait l'objet d'un audit ou d'un nouveau plan qui renvoie vers lui. Seuls le statut, les commits et la section « Écarts » s'ajoutent.
6. **Relier les documents.** Chaque audit cite le plan qu'il vérifie ; chaque plan cite les analyses qui l'ont motivé et les commits qui l'ont livré.
7. **Écrire en français** (Règle d'or 35), y compris pour décrire un composant nommé en anglais dans le code.
8. **Rester à sa place.** Ce dossier raconte le *comment* et le *quand*. Les règles durables restent dans `CLAUDE.md` / `AGENTS.md`, la description du produit dans `PRD.md` : un plan livré y renvoie, il ne les remplace pas.
