# Import catalogue BD / Manga — CSV

**Statut :** ✅ Spécification + implémentation v1 (`BdCatalogImporter`)  
**Phase :** **M2** — clôture vers **0.8.0**  
**Page admin :** `/import-catalogue-bd.php`  
**Doc module :** [bd.md](bd.md)

---

## Objectif

Importer un **catalogue de séries et tomes** BD / manga / comic depuis un fichier **CSV** (séparateur `;`), **sans API externe**.

- Une **ligne = un tome**
- La **série** est créée ou réutilisée automatiquement (même titre)
- Import au niveau **catalogue partagé** (comme l’import magazines JSON)
- Option : aussi ajouter les tomes à **ma collection**

---

## Format CSV (v1)

Encodage : **UTF-8** (BOM accepté). Première ligne = en-têtes.

| Colonne | Obligatoire | Alias acceptés | Description |
|---------|-------------|----------------|-------------|
| `serie` | **oui** | `série`, `series`, `titre_serie` | Titre de la série |
| `kind` | non | `type`, `format` | `bd`, `manga` ou `comic` (défaut : `bd`) |
| `editeur_serie` | non | `editeur_series` | Éditeur de la série |
| `tome_numero` | non* | `numero`, `n°`, `tome` | Numéro affiché (0 accepté) |
| `tome_ordre` | non | `ordre` | Ordre de tri (décimal) |
| `tome_label` | non | `label`, `libelle` | Libellé alternatif (HS, intégrale…) |
| `hors_serie` | non | `hs`, `est_hors_serie` | `oui` / `non` / `1` / `0` |
| `titre` | non | `titre_tome` | Titre du tome (sinon généré : « Série — Tome N ») |
| `annee` | non | `année`, `year` | Année de parution |
| `scenariste` | non | `scénario`, `auteur` | Scénariste |
| `dessinateur` | non | `dessin` | Dessinateur |
| `editeur` | non | | Éditeur du tome |
| `genre` | non | | Genre libre |
| `synopsis` | non | `resume`, `résumé` | Texte |
| `support` | non | `support_physique` | `album`, `relie`, `poche`, `coffret`, `magazine` (si ajout collection) |

\* Au moins **un** parmi `tome_numero`, `tome_label` ou `titre` doit permettre d’identifier le tome.

### Exemple

```csv
serie;kind;tome_numero;hors_serie;titre;annee;scenariste;dessinateur
Astérix;bd;1;non;Astérix le Gaulois;1961;Goscinny;Uderzo
Astérix;bd;38;oui;;2015;Ferri;Conrad
One Piece;manga;1;non;Romance Dawn;1997;Oda;Oda
```

---

## Comportement

| Situation | Action |
|-----------|--------|
| Série absente | Création (`media_domain = bd`, `tags` = kind) |
| Série déjà présente (même titre) | Réutilisation |
| Tome déjà présent (même série + numéro + HS) | Ignoré si « ignorer existants » (défaut) |
| Mode essai (`dry_run`) | Compteurs sans écriture |
| Ajout collection | Enregistre la série + le tome dans la bibliothèque de l’admin connecté |

Rapport : séries créées / réutilisées, tomes créés / ignorés, erreurs par ligne.

---

## Fichiers

| Fichier | Rôle |
|---------|------|
| `lib/BdCatalogImporter.php` | Lecture CSV + import |
| `lib/BdCatalogCreator.php` | `createCatalogOnly()` (tome sans bibliothèque) |
| `www/import-catalogue-bd.php` | Page admin |
| `templates/import-catalogue-bd.php` | Formulaire |
| `tests/Unit/BdCatalogImporterTest.php` | Parsing / dry-run |
| `tests/Integration/BdCatalogImporterTest.php` | Écriture réelle |

---

## Hors v1

- Export CSV BD (à ajouter plus tard pour aller-retour)
- Enrichissement API (aucun fournisseur retenu)
- Fusion intelligente de doublons au-delà de série+numéro+HS
- Import ODS (CSV uniquement ; ODS possible plus tard via `ImportOds`)

---

## Critère de clôture M2

Avec cet import, l’onglet BD est utilisable **sans saisie manuelle massive** → phase M2 prête pour **0.8.0**.
