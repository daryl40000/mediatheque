# Magazines — guide utilisateur et technique

**Version : 0.2.1** · **Date : 2026-05-31**

L’onglet **Magazines** permet de gérer des **séries** (revues) et leurs **numéros** : couverture, sommaire, PDF, recherche et supports (papier / PDF).

---

## 1. Parcours utilisateur

| Page | URL | Rôle |
|------|-----|------|
| Liste des séries | `/magazines.php` | Séries présentes dans votre collection ou vos envies |
| Numéros d’une série | `/serie-magazine.php?series_id=…` | Grille de numéros, recherche, tri |
| Fiche numéro | `/magazine-numero.php?bib_id=…` | Détail, PDF, édition |
| Ajouter une série | `/ajouter-serie-magazine.php` | Création catalogue |
| Ajouter un numéro | `/ajouter-numero-magazine.php?series_id=…` | Nouveau numéro + import PDF |

---

## 2. Support : tags Papier et PDF

Le support n’est plus un champ texte libre.

| Tag | Comment il apparaît |
|-----|---------------------|
| **PDF** | Automatique dès qu’un fichier PDF est importé sur le numéro |
| **Papier** | Case à cocher « J’ai le numéro en papier » à l’ajout ou sur la fiche |

En base, les tags sont stockés dans `bibliotheque.support_physique` sous la forme `papier,pdf`. Les anciennes saisies (« Papier + PDF », etc.) restent comprises à l’affichage.

Classe PHP : `lib/MagazineSupport.php`.

---

## 3. Numérotation et hors-série

Deux champs distincts :

- **Numéro** (`numero`) — texte affiché (`123`, `HS 8`, `8 bis`…).
- **Ordre de tri** (`numero_ordre`) — nombre décimal pour classer la liste (ex. `123`, `8.5`, `8.1`).

**Hors-série** : case à cocher + badge **HS** sur la carte. À la **création**, si l’ordre est un entier (ex. `8`) et la case est cochée, l’ordre devient **8.5** (entre 8 et 9).

Pour un numéro oublié au milieu de la série : modifiez **uniquement** ce numéro et mettez un ordre **entre** les voisins (ex. `8.1` ou `8.5`) — inutile de renuméroter toute la collection.

---

## 4. Fichiers PDF

### Emplacement

Les PDF sont stockés sous :

`data/media/magazines/{slug-revue}/{année}/{revue}-{numero}.pdf`

(via `stored_objects` et `MagazineRepository::buildMagazinePdfRelativePath`).

### Après l’import (traitement différé)

Pour ne pas bloquer le navigateur sur les gros fichiers, le post-traitement s’exécute en fin de requête HTTP :

| Étape | Outil | Résultat |
|-------|--------|----------|
| Texte des 6 premières pages | `pdftotext` (Poppler) | Colonne `pdf_text_preview` — recherche sur la page série |
| Couverture si absente | `pdftoppm` | Page 1 → affiche du numéro |
| Nombre de pages si 0 | `pdfinfo` | Champ `pages` rempli automatiquement |

**Dépendance système :** paquet `poppler-utils` (`pdftotext`, `pdftoppm`, `pdfinfo`).

Migration : `sql/migrations/033_magazine_pdf_text_preview.sql`.

---

## 5. Recherche sur une série

Sur `/serie-magazine.php`, le champ **Rechercher** (`q`) filtre sur :

- numéro affiché ;
- date de parution ;
- sommaire ;
- texte extrait du PDF (`pdf_text_preview`).

---

## 6. Développement local (gros PDF)

Le serveur PHP intégré par défaut limite souvent les envois à 2–8 Mo.

**Recommandé :**

```bash
./start-dev.sh
```

Ce script lance `www/serve.sh` avec `www/php-dev.ini` (upload ~350 Mo, sessions dans `data/sessions/`).

Alternative Apache / PHP-FPM : `www/.user.ini` (mêmes limites si l’hébergeur l’autorise).

Classe : `lib/UploadLimits.php` — alerte dans les formulaires si les limites PHP sont trop basses.

---

## 7. Fichiers PHP principaux

| Fichier | Rôle |
|---------|------|
| `lib/MagazineRepository.php` | CRUD numéros, PDF, sync tags support |
| `lib/MagazineSupport.php` | Tags papier / pdf |
| `lib/MagazinePdfTextExtractor.php` | Extraction texte 6 pages |
| `lib/MagazinePdfCoverExtractor.php` | Couverture page 1 |
| `lib/MagazinePdfInfo.php` | Nombre de pages |
| `lib/SeriesRepository.php` | Séries magazine |
| `lib/PublicationType.php` | Types de parution, formatage dates |

---

## 8. Mise à jour depuis 0.2.0

```bash
php lib/cli/migrate.php
```

Installez **poppler-utils** si la recherche dans le PDF ou la couverture auto est souhaitée.

Pour le dev local avec import PDF volumineux : utilisez `./start-dev.sh` plutôt que `php -S` seul.

---

*Voir aussi [CHANGELOG.md](../CHANGELOG.md) (section 0.2.1) et [ROADMAP.md](../ROADMAP.md) (phase M5).*
