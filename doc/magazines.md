# Magazines — guide utilisateur et technique

**Version : 0.2.5** · **Date : 2026-05-31**

L’onglet **Magazines** permet de gérer des **séries** (revues) et leurs **numéros** : couverture, sommaire, PDF, recherche, supports (papier / PDF), collection et envies.

---

## 1. Parcours utilisateur

| Page | URL | Rôle |
|------|-----|------|
| Liste des séries | `/magazines.php` | Séries présentes dans votre collection |
| Mes envies | `/magazines-envies.php` | Numéros que vous souhaitez acquérir |
| Numéros d’une série | `/serie-magazine.php?series_id=…` | Grille de numéros, recherche, tri, filtres |
| Fiche numéro | `/magazine-numero.php?id=…` | Détail, PDF, édition, suppression |
| Ajouter une série | `/ajouter-serie-magazine.php` | Création catalogue |
| Ajouter un numéro | `/ajouter-numero-magazine.php?series_id=…` | Nouveau numéro + import PDF |

Paramètres utiles sur la liste série :

- `statut=collection` ou `statut=wishlist` — collection du foyer ou envies personnelles ;
- `possession=all|owned|unowned` — filtre **Tous / Possédés / Non possédés** (collection uniquement).

---

## 2. Possédé, non possédé, envies

| État | Définition |
|------|------------|
| **Possédé** | Au moins un tag **papier** et/ou un **PDF** importé |
| **Non possédé** | Numéro référencé en collection, sans papier ni PDF — **non compté** dans les stats « numéros possédés » |
| **En envies** | Entrée wishlist **personnelle** (séparée de la collection du foyer) |

Comportements importants (**0.2.3+**) :

- **Ajouter aux envies** : duplique une entrée wishlist ; le numéro **reste** visible dans la collection (badge « Non possédé »).
- **Badge « En envies »** : indique que le numéro est déjà dans vos envies.
- **Retrait auto des envies** (**0.2.5**) : dès qu’un numéro devient possédé (papier ou PDF), il disparaît de **Mes envies** mais reste en collection.

---

## 3. Couvertures et affiches

Les couvertures de numéros et les logos de séries utilisent le même stockage que les **affiches films** (`data/posters/`).

- Taille max. par fichier image : **10 Mo** (`MONCINE_POSTER_MAX_BYTES` dans `lib/config.php`).
- Formats : JPEG, PNG, WebP.

---

## 4. Support : tags Papier et PDF

Le support n’est plus un champ texte libre.

| Tag | Comment il apparaît |
|-----|---------------------|
| **PDF** | Automatique dès qu’un fichier PDF est importé sur le numéro |
| **Papier** | Case à cocher « J’ai le numéro en papier » à l’ajout ou sur la fiche |

En base, les tags sont stockés dans `bibliotheque.support_physique` sous la forme `papier,pdf`. Les anciennes saisies (« Papier + PDF », etc.) restent comprises à l’affichage.

Classe PHP : `lib/MagazineSupport.php`.

---

## 5. Numérotation et hors-série

Deux champs distincts :

- **Numéro** (`numero`) — texte affiché (`123`, `HS 8`, `8 bis`…).
- **Ordre de tri** (`numero_ordre`) — nombre décimal pour classer la liste (ex. `123`, `8.5`, `8.1`).

**Hors-série** : case à cocher + badge **HS** sur la carte. À la **création**, si l’ordre est un entier (ex. `8`) et la case est cochée, l’ordre devient **8.5** (entre 8 et 9).

Pour un numéro oublié au milieu de la série : modifiez **uniquement** ce numéro et mettez un ordre **entre** les voisins (ex. `8.1` ou `8.5`) — inutile de renuméroter toute la collection.

---

## 6. Fichiers PDF

### Emplacement

Les PDF sont stockés sous :

`data/media/magazines/{slug-revue}/{année}/{revue}-{numero}.pdf`

(via `stored_objects` et `MagazineRepository::buildMagazinePdfRelativePath`).

### Import depuis Mes envies

Vous pouvez importer un PDF depuis la fiche ouverte depuis **Mes envies**. Après l’import :

1. le numéro devient **possédé** (tag PDF) ;
2. il est **retiré des envies** automatiquement ;
3. la fiche s’ouvre sur l’entrée **collection** (redirection corrigée en **0.2.5**).

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

## 7. Recherche sur une série

Sur `/serie-magazine.php`, le champ **Rechercher** (`q`) filtre sur :

- numéro affiché ;
- date de parution ;
- sommaire ;
- texte extrait du PDF (`pdf_text_preview`).

---

## 8. Supprimer un numéro (**0.2.5**)

La suppression n’apparaît **pas** sur les cartes de la liste (interface allégée).

Sur la **fiche numéro** :

1. Cliquez sur l’**icône poubelle** en haut à droite → **mode suppression** ;
2. Lisez le panneau d’avertissement ;
3. Confirmez **Retirer des envies** ou **Retirer de ma collection**, ou **Annuler**.

---

## 9. Développement local (gros PDF)

Le serveur PHP intégré par défaut limite souvent les envois à 2–8 Mo.

**Recommandé :**

```bash
./start-dev.sh
```

Ce script lance `www/serve.sh` avec `www/php-dev.ini` (upload ~350 Mo, sessions dans `data/sessions/`).

Alternative Apache / PHP-FPM : `www/.user.ini` (mêmes limites si l’hébergeur l’autorise).

Classe : `lib/UploadLimits.php` — alerte dans les formulaires si les limites PHP sont trop basses.

---

## 10. Fichiers PHP principaux

| Fichier | Rôle |
|---------|------|
| `lib/MagazineRepository.php` | CRUD numéros, PDF, envies, sync tags, redirection fiche |
| `lib/MagazineSupport.php` | Tags papier / pdf, règle « possédé » |
| `lib/MagazinePdfTextExtractor.php` | Extraction texte 6 pages |
| `lib/MagazinePdfCoverExtractor.php` | Couverture page 1 |
| `lib/MagazinePdfInfo.php` | Nombre de pages |
| `lib/SeriesRepository.php` | Séries magazine |
| `lib/PublicationType.php` | Types de parution, formatage dates |
| `templates/_magazine_delete_button.php` | Formulaire suppression (mode fiche) |
| `templates/_magazine_wishlist_button.php` | Bouton / badge envies |

---

## 11. Mise à jour depuis 0.2.0

```bash
php lib/cli/migrate.php
```

Installez **poppler-utils** si la recherche dans le PDF ou la couverture auto est souhaitée.

Pour le dev local avec import PDF volumineux : utilisez `./start-dev.sh` plutôt que `php -S` seul.

---

*Voir aussi [CHANGELOG.md](../CHANGELOG.md) (sections 0.2.3 à 0.2.5) et [ROADMAP.md](../ROADMAP.md) (phase M5).*

**Import massif d’affiches films** (plusieurs centaines) : page **Importer** → ZIP jusqu’à 200 Mo ([doc via README](../README.md)).
