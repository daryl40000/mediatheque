# Magazines — guide utilisateur et technique

**Version : 0.7.22** · **Date : 2026-07-13**

L’onglet **Magazines** permet de gérer des **séries** (revues) et leurs **numéros** : couverture, sommaire, PDF, recherche, supports (papier / PDF), collection et envies.

---

## 1. Parcours utilisateur

| Page | URL | Rôle |
|------|-----|------|
| Liste des séries | `/magazines.php` | Séries en collection + **recherche globale** (sujets, sommaires, PDF) (**0.4.1**) |
| Mes envies | `/magazines-envies.php` | Numéros que vous souhaitez acquérir |
| Numéros d’une série | `/serie-magazine.php?series_id=…` | Grille de numéros, recherche, tri, filtres |
| Fiche numéro | `/magazine-numero.php?id=…` | Détail, PDF, édition, suppression |
| Ajouter une série | `/ajouter-serie-magazine.php` | **Catalogue** (autocomplétion) ou création manuelle (**0.6.0**) |
| Ajouter un numéro | `/ajouter-numero-magazine.php?series_id=…` | Catalogue (autocomplétion n°) ou création + PDF (**0.6.1**) |
| Profil ami — série | `/utilisateur-serie-magazine.php?id=…&series_id=…` | Numéros d’un ami (lecture seule, **0.3.2**) |
| Profil ami — numéro | `/utilisateur-numero-magazine.php?id=…&bib_id=…` | Fiche numéro sans PDF partagé (**0.3.2**) |
| Recherche par sujet | `/magazines-recherche.php` | Tests, previews, dossiers dans **toutes** les séries (**0.4.0**) |
| Fiche sujet | `/magazine-sujet.php?id=…` | Numéros et séries concernés par un sujet (**0.4.0**) |

Paramètres utiles sur la liste série :

- `statut=collection` ou `statut=wishlist` — collection du foyer ou envies personnelles ;
- `possession=all|owned|unowned|hors_serie` — filtre **Tous / Possédés / Non possédés / Hors-série** (collection uniquement, **0.4.2**) ; **mémorisé** dans le navigateur pour les visites suivantes (**0.7.16**).

En-tête de série (collection) : **« X possédé(s) sur Y »** où **Y** est le total des numéros dans le **catalogue** partagé. À l’ouverture de la série, les numéros catalogue absents de votre bibliothèque sont **rattachés automatiquement** comme **non possédés** (**0.7.16**, aligné sur le comportement BD).

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

**Logo de série** : sans image dédiée sur la fiche série, la liste « Mes magazines » affiche automatiquement la couverture du **numéro 1** (hors-série exclus). Vous pouvez téléverser un logo propre via **Modifier la série** (`/modifier-serie-magazine.php`).

**Maintenance catalogue — affiches orphelines** : seuls les fichiers non référencés par `oeuvres.poster_url` ou `series.poster_url` sont proposés à la suppression. Une couverture utilisée via le numéro 1 n’est pas orpheline même sans logo de série.

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

**Hors-série** : case à cocher + badge **HS** sur la carte. À la **création**, si l’ordre est un entier (ex. `8`) et la case est cochée, l’ordre devient **8.5** (entre 8 et 9). Un **numéro classique** et un **hors-série** peuvent porter le même libellé (ex. n°1 et HS n°1) : ce ne sont pas des doublons.

Pour un numéro oublié au milieu de la série : modifiez **uniquement** ce numéro et mettez un ordre **entre** les voisins (ex. `8.1` ou `8.5`) — inutile de renuméroter toute la collection.

**Modification catalogue** (`/oeuvre-magazine.php`, administrateur) : la case hors-série est enregistrée explicitement (cochée ou décochée). Si un **numéro classique** portant le même libellé existe déjà, le retrait du hors-série est refusé avec un message explicite — fusionnez d’abord les doublons (voir ci-dessous). Le champ couverture accepte les chemins locaux `/posters/…` (pas seulement les URL HTTPS).

**Doublons numéros** : page **Maintenance catalogue** → section **Doublons magazines (série + numéro)** — regroupe les fiches ayant la même revue, le même libellé de numéro et le même statut hors-série ; liens « Ouvrir la fiche » et fusion.

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

### Retirer un PDF d’un numéro (**0.7.22**)

Sur la **fiche numéro** (`/magazine-numero.php?id=…`) :

1. Cliquez sur l’icône **PDF**.
2. Cliquez sur **Retirer le PDF** (confirmation).

Le fichier est supprimé du serveur et l’icône PDF disparaît. Le numéro reste dans votre collection (si vous aviez aussi le support **Papier**, il reste possédé).

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

## 7. Recherche et liste des numéros

Sur `/serie-magazine.php`, le champ **Rechercher** (`q`) filtre sur :

- numéro affiché ;
- date de parution (ex. « juin 2024 ») ;
- sommaire ;
- texte extrait du PDF (`pdf_text_preview`).

Sur **Mes magazines** (`/magazines.php`), le champ **Rechercher dans vos magazines** interroge en plus :

- les **sujets** associés (tests, previews, comparatifs, dossiers, interviews) ;
- le **sommaire** et l’**extrait PDF** de tous les numéros de votre bibliothèque ;
- les **titres de séries** (comme avant).

Les résultats sont regroupés en **Sujets trouvés**, **Numéros trouvés** et **Séries correspondantes**. Une autocomplétion propose les sujets déjà connus pendant la saisie.

Depuis **0.4.1**, la recherche texte utilise un index **FTS5** (`magazine_issue_fts`) : plus rapide sur de gros catalogues. Si FTS n’est pas disponible, l’ancienne recherche `LIKE` est utilisée.

La **recherche par sujet** (`/magazines-recherche.php`) utilise aussi FTS sur le catalogue de sujets (`magazine_subject_fts`).

Migration : `sql/migrations/038_magazine_fts.sql`.

**Grille** : **48 numéros par page** (8 colonnes × 6 lignes sur grand écran). Navigation **Première / Préc. / Suiv. / Dernière** et saut de page (`?page=2`). Les écrans plus étroits affichent moins de colonnes (6, 4 ou 2) pour garder des tuiles lisibles.

**Couvertures** : sur **Mes magazines** (collection), les numéros **non possédés** (ni papier ni PDF) s’affichent en **noir et blanc** ; les possédés et la liste **Mes envies** restent en couleur.

**Statistiques** (`/statistiques.php`, onglet Magazines) : nombre de **PDF possédés** et **espace disque** total (Go), calculés depuis `stored_objects.size_bytes` à l’import.

**Export PDF** (`/imprimer-serie-magazine.php`) : depuis la page série, bouton **Exporter en PDF** — liste textuelle (sans couvertures) avec colonne **Possession** : Non possédé, Papier, PDF, Papier + PDF. Mêmes filtres et tri que la page série ; enregistrement via « Imprimer / Enregistrer en PDF » du navigateur.

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
| `lib/SeriesRepository.php` | Séries magazine (dont **tags** de série) |
| `lib/MagazineSubject.php` | Catégories de sujets (Test, Preview…) |
| `lib/MagazineSubjectRepository.php` | Catalogue sujets, liens numéro ↔ sujet, recherche |
| `lib/MagazineIssueFts.php` | Index FTS5 des numéros (recherche série) |
| `lib/MagazineSubjectFts.php` | Index FTS5 du catalogue de sujets |
| `lib/MagazineFtsQuery.php` | Construction des requêtes MATCH |
| `templates/_magazine_issue_subjects.php` | Formulaire sujets sur fiche numéro |
| `templates/_magazine_issue_subjects_strip.php` | Bandeau horizontal de vignettes (sujets reliés, **0.7.17**) |
| `templates/_magazine_series_tags_field.php` | Badges tags sur fiche série |
| `templates/_magazine_delete_button.php` | Formulaire suppression (mode fiche) |
| `templates/_magazine_wishlist_button.php` | Bouton / badge envies |
| `lib/PublicationType.php` | Types de parution, formatage dates |

---

## 11. Sujets et recherche globale (**0.4.0**)

Pour retrouver un **test**, une **preview**, un **dossier** ou une **interview** dans l’ensemble de vos magazines :

1. **Tags de la série** (création ou modification de la revue) :
   - tapez un mot (ex. `PC`) → **Ajouter** (ou Entrée) → badge ;
   - répétez pour un 2ᵉ tag (ex. `PS5`) ; **×** pour retirer avant enregistrement ;
   - **1 tag** → appliqué automatiquement à chaque sujet du numéro ;
   - **2 tags ou plus** → menu déroulant à chaque ajout ;
   - **aucun tag** → précision libre optionnelle sur le numéro.
2. **Fiche numéro**, section **Sujets et tests** : catégorie (**Test**, Preview, Comparatif, Dossier, Interview) + nom ;
   - **type de média** (jeu, film…) pour lier le sujet à une fiche catalogue (**0.7.17**) ;
   - **autocomplétion** pendant la saisie : sujets déjà existants **ou** titres du catalogue selon le type choisi ;
   - si le titre catalogue n’existe pas encore, une **fiche minimale est créée automatiquement** à l’enregistrement (**0.7.17**) ;
   - les sujets reliés s’affichent en **bandeau horizontal** de couvertures (défilement, bulle test/preview…, lien bibliothèque ou catalogue) (**0.7.17**) ;
   - **année** : menu déroulant sur la fiche numéro (par défaut l’année du numéro, modifiable si le test porte sur une autre année) ;
   - à l’enregistrement, les libellés **proches** (espaces ou ponctuation différents) sont **fusionnés** avec le sujet existant.
3. **Recherche par sujet** (`/magazines-recherche.php`) : filtre **Test** regroupe aussi les anciennes catégories en base ; autocomplétion sur le nom.
4. **Mes magazines** (`/magazines.php`) : recherche globale dans titres, sujets, sommaires et extraits PDF (depuis **0.4.1**).

Affichage type : `Gran Turismo 7 (PC · 2024)`.

Tables : `magazine_subject`, `oeuvre_magazine_subject`, `series.tags` — migrations `034` à `037` ; index FTS — migration `038`.

**Évolution (onglet Jeux, phase M4+)** : lien optionnel d’un sujet test/preview/interview vers une **fiche catalogue** (`catalog_oeuvre_id`) — **jeu** ou **film** depuis **0.7.17**. À l’ajout d’un sujet sur un numéro, l’autocomplétion propose les titres du catalogue (titre, plateforme ou année selon le média) en plus des sujets déjà saisis. Les sujets déjà en production restent valides ; voir [ROADMAP.md](../ROADMAP.md) § *Pont Magazines ↔ Jeux vidéo*, [pont-magazine-jeu.md](pont-magazine-jeu.md) et [jeux.md](jeux.md).

Classes **0.7.17** : `MagazineSubjectCatalogLink`, API `/rechercher-catalogue-sujet-magazine.php`.

---

## 12. Maintenance admin des sujets (administrateur)

Page réservée aux **administrateurs** du catalogue :

| Page | URL | Rôle |
|------|-----|------|
| Nettoyage sujets | `/maintenance-magazine-sujets.php` | Orphelins, purge, fusion de doublons |
| Liens magazine ↔ jeux | `/maintenance-magazine-jeux-liens.php` | Rattachement rétroactif sujets → catalogue jeux |

Accès depuis le menu admin **Sujets magazines**, **Liens magazine ↔ jeux** ou **Maintenance catalogue**.

### Sujets orphelins

Un sujet **orphelin** n’est lié à **aucun numéro**. Cela arrive souvent après une **faute de frappe** : vous commencez à taper un nom, un mauvais sujet est créé, puis vous corrigez et enregistrez un autre libellé — l’ancien reste en base sans numéro.

- **Supprimer** : retire un orphelin précis (avec confirmation).
- **Tout supprimer** : purge en une fois tous les orphelins listés.
- Les sujets avec **libellé vide** sont mis en évidence (ligne surlignée).

### Doublons probables

Regroupe les sujets qui ont la **même catégorie**, le **même tag**, la **même année** et un **libellé normalisé identique** (espaces et ponctuation ignorés), mais un texte d’affichage différent — ex. « After Life » et « Afterlife » si les deux existent encore.

- Choisissez **Conserver** (fiche à garder) et **Fusionner (supprimer)** (doublon).
- Les numéros liés au doublon sont **réaffectés** vers le sujet conservé.

Les actions sont tracées dans le **journal d’audit** du catalogue (`CatalogAuditLog`).

### Pont magazine ↔ jeux (0.6.3+)

Page **`/maintenance-magazine-jeux-liens.php`** : rattachement rétroactif des sujets test / preview / interview à une fiche jeu catalogue, suggestions automatiques, retrait de lien. Guide : [pont-magazine-jeu.md](pont-magazine-jeu.md).

Classe PHP : `lib/MagazineSubjectMaintenance.php`.

---

## 14. Import catalogue ABM (**0.6.0**)

Alimentation du **catalogue partagé** (séries + numéros) depuis [Abandonware Magazines](https://www.abandonware-magazines.org/) — **sans** ajout à votre bibliothèque personnelle.

| Étape | Outil |
|-------|--------|
| 1. Télécharger les métadonnées | `php lib/cli/abm-fetch-catalog.php --magazine=Tilt --stats` |
| 2. Importer en catalogue | Page admin **`/import-catalogue-magazines.php`** ou `php lib/cli/abm-import-catalog.php` |
| 3. Ajouter à ma collection | `/ajouter-serie-magazine.php` → recherche catalogue |
| 4. Ajouter un numéro | `/ajouter-numero-magazine.php` → autocomplétion n° catalogue (**0.6.1**) |
| 5. Couvertures (optionnel) | Relancer l’import avec « Télécharger les couvertures » par **lots de 20** |
| 6. Exporter | **`/export-catalogue-magazines.php`** (JSON admin) |

Guide détaillé : [doc/import-abm.md](import-abm.md).

### Dates de parution

À l’import, les libellés ABM sont convertis en dates ISO :

| Texte ABM | En base |
|-----------|---------|
| `Janvier 2002` | `2002-01-01` |
| `Mars 2018` | `2018-03-01` |
| `Juillet / août 2020` | `2020-07-01` (premier mois) |

### Retirer une série de ma bibliothèque

Sur la fiche série ou « Modifier la série » : bouton **Retirer de mes magazines** (ou **envies**). Les fiches catalogue restent disponibles pour un nouvel ajout.

---

## 15. Mise à jour depuis 0.2.0

```bash
php lib/cli/migrate.php
```

Installez **poppler-utils** si la recherche dans le PDF ou la couverture auto est souhaitée.

Pour le dev local avec import PDF volumineux : utilisez `./start-dev.sh` plutôt que `php -S` seul.

---

*Voir aussi [CHANGELOG.md](../CHANGELOG.md) (sections **0.6.0**, 0.4.x…) et [ROADMAP.md](../ROADMAP.md) (phase M5, pont jeux).*

**Import massif d’affiches films** (plusieurs centaines) : page **Importer** → ZIP jusqu’à 200 Mo ([doc via README](../README.md)).
