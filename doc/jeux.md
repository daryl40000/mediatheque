# Jeux vidéo (phase M4)

Documentation du module **Jeux** dans la médiathèque Monciné.

**Version : 0.5.7** · **Date : 2026-06-16**

## Objectif

Gérer une **collection de jeux vidéo** (physiques ou dématérialisés) avec le même principe que les films et magazines :

- un **catalogue partagé** (`oeuvres` + `oeuvre_jeu`) ;
- une **bibliothèque personnelle** (`bibliotheque`) : collection du foyer ou envies individuelles ;
- un **pont avec les magazines** : relier un sujet test/preview/interview à une fiche jeu.

## Schéma base de données

Vue d’ensemble de **toutes** les tables (catalogue, bibliothèque, magazines, comptes…) : [base-de-donnees.md](base-de-donnees.md).

### Table `oeuvre_jeu`

| Colonne | Rôle |
|---------|------|
| `oeuvre_id` | Clé vers `oeuvres.id` (jeu catalogue) |
| `studio` | Développeur |
| `editeur` | Éditeur |
| `genre` | Genres (Action-RPG, FPS…) — liste séparée par virgules |
| `platform` | Plateforme principale (`pc`, `ps5`, `switch`…) |
| `is_digital` | 1 = version démat, 0 = physique |
| `physical_supports` | Supports physiques possédés (CD/DVD, disquette…) |
| `digital_stores` | Magasins démat (Steam, GOG, Epic…) + URLs |
| `is_extension` | 1 = extension (DLC / add-on) — migration **044** |
| `base_game_oeuvre_id` | Jeu de base du catalogue (extensions) |
| `is_remake` | 1 = remake — migration **045** |
| `original_game_oeuvre_id` | Jeu d’origine du catalogue (remakes) |
| `igdb_id` | Identifiant IGDB (correction manuelle possible) — migration **046** |
| `igdb_enriched_at` | Date de la dernière tentative d’enrichissement IGDB |
| `franchise` | Saga / franchise IGDB (ex. « The Witcher ») — migration **047** |
| `game_mode` | Modes de jeu traduits (Solo, Multijoueur…) — tags séparés par virgules |
| `theme` | Thèmes traduits (Fantasy, Monde ouvert…) — tags séparés par virgules |
| `alternative_names` | Acronymes IGDB uniquement (GTA, FF…) — tags séparés par virgules |

### Table `game_attachment`

| Colonne | Rôle |
|---------|------|
| `id` | Identifiant du fichier joint |
| `bibliotheque_id` | Entrée bibliothèque (exemplaire du foyer) |
| `original_filename` | Nom du fichier à l’upload |
| `stored_filename` | Nom sur disque (dossier `games/`) |
| `mime_type` | Type MIME |
| `file_size` | Taille en octets |
| `created_at` | Date d’ajout |

Migrations :

- `sql/migrations/039_oeuvre_jeu_magazine_link.sql` — table `oeuvre_jeu` + pont magazine ;
- `sql/migrations/040_oeuvre_jeu_editions.sql` — exemplaires physiques/démat ;
- `sql/migrations/041_bibliotheque_tested_on_linux.sql` — flag « testé sur Linux » ;
- `sql/migrations/042_game_attachment.sql` — fichiers attachés ;
- `sql/migrations/043_bibliotheque_linux_not_supported.sql` — flag « Linux non supporté » ;
- `sql/migrations/044_oeuvre_jeu_extensions.sql` — extensions (DLC) ;
- `sql/migrations/045_oeuvre_jeu_remakes.sql` — remakes ;
- `sql/migrations/046_oeuvre_jeu_igdb.sql` — identifiant et date enrichissement IGDB ;
- `sql/migrations/047_oeuvre_jeu_igdb_metadata.sql` — franchise, modes, thèmes, acronymes.

### Enrichissement IGDB (0.5.5)

Même logique que **TMDB pour les films** : compléter automatiquement les fiches catalogue (et donc la bibliothèque) depuis [IGDB](https://www.igdb.com/).

#### Configuration (une fois)

1. Créer une application **Confidential** sur [dev.twitch.tv/console/apps](https://dev.twitch.tv/console/apps).
2. Page **Importer** → section **« Enrichir mes jeux (IGDB) »** : enregistrer **Client ID** et **Client Secret**.
3. Alternative serveur : variables `MONCINE_IGDB_CLIENT_ID` et `MONCINE_IGDB_CLIENT_SECRET`, ou fichier `data/igdb_credentials.json`.

#### Données récupérées

| Champ | Stockage | Remarque |
|-------|----------|----------|
| Titre français | `oeuvres.titre` | Via `game_localizations` IGDB si disponible |
| Titre anglais | `oeuvres.titre_original` | Nom IGDB par défaut |
| Jaquette | `oeuvres.poster_url` | Téléchargée localement (`PosterStorage`) |
| Année | `oeuvres.annee` | Première date de sortie |
| Studio | `oeuvre_jeu.studio` | Développeur |
| Éditeur | `oeuvre_jeu.editeur` | Éditeur |
| Genres | `oeuvre_jeu.genre` | Traduits FR (`IgdbGenreMap`) |
| Saga | `oeuvre_jeu.franchise` | Première saga IGDB ; voir [Sagas jeux (0.5.6)](#sagas-jeux-056) |
| Modes | `oeuvre_jeu.game_mode` | Traduits FR (`IgdbGameModeMap`) |
| Thèmes | `oeuvre_jeu.theme` | Traduits FR (`IgdbThemeMap`) |
| Acronymes | `oeuvre_jeu.alternative_names` | Filtre acronymes seuls (`IgdbAlternativeNameFilter`) |

**Affichage titre :** le français est montré s’il existe ; sinon le titre anglais. L’anglais reste en base pour une future interface bilingue.

Les champs déjà remplis ne sont **pas écrasés**, sauf correction avec un **identifiant IGDB** précis.

**Jaquette existante (0.5.7) :** cochez **« Garder la jaquette »** pour conserver l’image déjà en place lors d’un enrichissement ou d’une correction IGDB (panneaux fiche, import par lot).

#### Utilisation

| Action | Où |
|--------|-----|
| Enrichir ~8 jeux par clic | **Importer** → « Enrichir mes jeux » |
| Enrichir une fiche catalogue | `/oeuvre-jeu.php` → panneau IGDB (admin) |
| Enrichir une fiche bibliothèque | `/jeu.php` → panneau IGDB (admin) |
| Corriger avec un ID IGDB | Coller le numéro ou l’URL `igdb.com/games/…` |

Handlers : `/enrichir-jeux.php` (lot + config), `/enrichir-jeu.php`, `/enrichir-oeuvre-jeu.php`.

### Sagas jeux (0.5.6)

Inspiré des **sagas films** (`/sagas.php`), adapté aux jeux vidéo :

| Élément | Films | Jeux |
|---------|-------|------|
| Nom de la saga | `bibliotheque.saga` (saisie utilisateur) | `oeuvre_jeu.franchise` (IGDB ou saisie manuelle) |
| Ordre dans la série | `bibliotheque.saga_ordre` | **Année de sortie**, puis titre |
| Page liste | `/sagas.php` | `/sagas-jeux.php` |
| Action de masse | Mes films → « Saga » | Mes jeux → « Ajouter à une saga » |

- Le nom de saga est rempli à l’**enrichissement IGDB** ; vous pouvez le corriger sur la fiche catalogue, via l’action de masse ou le renommage sur `/sagas-jeux.php`.
- **Autocomplétion** : sagas déjà présentes dans le catalogue proposées à la saisie (formulaires, action de masse, renommage).
- **Liste** : jaquette du premier jeu de chaque saga ; **détail** : jaquettes de tous les jeux, tri par année.
- Classe : `GameFranchiseRepository` ; URL : `View::gameFranchiseUrl()`.

**Correctif 0.5.6 — filtre genre (statistiques → Mes jeux) :** un jeu avec plusieurs genres (`Aventure, RPG, …`) est bien retrouvé pour **chaque** genre cliqué dans les statistiques.

#### Classes PHP (IGDB)

| Classe | Rôle |
|--------|------|
| `IgdbConfig` | Identifiants Twitch / IGDB (fichier ou env) |
| `IgdbClient` | OAuth2 + requêtes API v4 |
| `GameEnricher` | Enrichissement unitaire et par lots |
| `GameCatalogEnrichment` | Sélection et mise à jour SQL |
| `GameTitle` | Titre affiché FR / EN |
| `IgdbGenreMap`, `IgdbGameModeMap`, `IgdbThemeMap` | Traductions EN → FR |
| `IgdbAlternativeNameFilter` | Ne garde que les acronymes |

### Extensions et remakes (0.5.2 / 0.5.4)

| Type | Formulaire | Fiche jeu / catalogue |
|------|------------|------------------------|
| **Extension (DLC)** | Case « Extension » + autocomplétion jeu de base | Bandeau jaquettes « Extensions » ou lien vers le jeu de base |
| **Remake** | Case « Remake » + autocomplétion jeu d’origine | Bandeau « Remakes » ou lien vers le jeu d’origine (avec année) |

- Une fiche ne peut pas être **à la fois** extension et remake.
- À l’**ajout** : les cases Extension / Remake restent visibles tant qu’aucun jeu catalogue n’est sélectionné dans l’autocomplétion du titre.
- Affichage **discret** : jaquette cliquable + année sous l’image (`templates/_game_related_posters.php`) ; extensions et remakes côte à côte en deux colonnes si les deux existent.

### Recherche et autocomplétion (0.5.4)

Classe **`SearchMatch`** (insensible **casse** et **accents**, **1 faute de frappe** par mot dans l’autocomplétion) :

| Zone | Comportement |
|------|--------------|
| Autocomplétion **jeux** (`/rechercher-jeux-catalogue.php`) | accents + 1 faute / mot |
| Autocomplétion **films** (titre catalogue) | accents + 1 faute / mot |
| Autocomplétion **sujets magazines** | accents + 1 faute / mot (+ FTS si disponible) |
| Recherche **Mes jeux** / **Mes films** | accents ; jeux : acronymes (`alternative_names`, **0.5.7**) |
| Recherche **catalogue admin** | accents |

Fonction SQL **`fold_search()`** enregistrée au démarrage (`FrenchSort::fold`) pour comparer sans accents dans les requêtes.

Exemples : `demon` → *Démon Souls* ; `eldn ring` → *Elden Ring* ; `gran turismo` → *Gran Turismo* ; `GTA` → jeux dont l’acronyme IGDB correspond (**0.5.7**).

### Lien magazine → jeu

Colonne **`magazine_subject.catalog_oeuvre_id`** (nullable) :

- pointe vers `oeuvres.id` où `media_domain = 'jeu'` ;
- utilisée pour les sujets **Test**, **Preview**, **Interview** ;
- la saisie libre du sujet reste possible (données existantes conservées).

## Pages web

| URL | Rôle |
|-----|------|
| `/` (onglet Jeux) | Accueil jeux — activité récente, raccourcis |
| `/jeux.php` | Liste « Mes jeux » (tri, recherche, actions de masse saga) |
| `/sagas-jeux.php` | Sagas jeux (liste avec jaquettes, détail ordonné par année, renommage) |
| `/jeux-envies.php` | Liste des envies jeux |
| `/jeu.php?id=` | Fiche jeu (+ section « Dans vos magazines », fichiers attachés) |
| `/oeuvre-jeu.php?id=` | Fiche catalogue jeu (admin : édition, enrichissement IGDB) |
| `/enrichir-jeux.php` | Enrichissement IGDB par lots + config (POST) |
| `/enrichir-jeu.php` | Enrichissement IGDB fiche bibliothèque (POST, admin) |
| `/enrichir-oeuvre-jeu.php` | Enrichissement IGDB fiche catalogue (POST, admin) |
| `/ajouter-jeu.php` | Formulaire d’ajout (collection ou envie) |
| `/modifier-jeu.php?id=` | Modification fiche catalogue (admin) |
| `/rechercher-jeux-catalogue.php` | API JSON autocomplétion catalogue |
| `/marquer-joue.php` | Enregistrer une note sur 10 |
| `/supprimer-jeu.php` | Retirer un jeu de la collection ou des envies |
| `/promouvoir-jeu-collection.php` | Passer une envie en collection (« J’ai acheté ») |
| `/enregistrer-fichier-jeu.php` | Ajouter un fichier joint (POST) |
| `/supprimer-fichier-jeu.php` | Supprimer un fichier joint |
| `/statistiques.php` | Statistiques jeux (onglet Jeux actif) |

## Classes PHP

| Classe | Rôle |
|--------|------|
| `GameFranchiseRepository` | Sagas jeux (liste, détail, renommage) |
| `GameRepository` | CRUD collection, recherche catalogue, jaquettes, flags Linux, extensions, remakes |
| `GameEnricher` | Enrichissement IGDB (unitaire et par lots) |
| `IgdbClient` | Client HTTP API IGDB v4 |
| `GameTitle` | Titre affiché (français prioritaire, anglais en secours) |
| `GameSchema` | Détection colonnes / migrations progressives |
| `GameRowMapper` | Hydratation lignes catalogue et bibliothèque |
| `GameRelatedSections` | Bandeaux extensions / remakes |
| `SearchMatch` | Recherche tolérante (accents, casse, faute) pour autocomplétion |
| `GameAttachmentRepository` | Upload, liste et suppression des fichiers joints |
| `GameEditionIcons` | URLs des icônes support (images ou repli SVG) |
| `GameGenre` | Genres réutilisables (tags, comme magazines) |
| `GameCollectionStats` | Statistiques collection jeux |
| `GamePlatform` | Liste et normalisation des plateformes |
| `GamePhysicalSupport` | Supports physiques (CD/DVD, disquette) |
| `GameDigitalStore` | Magasins démat PC et stores console |
| `MagazineGameLink` | Validation et gestion du pont sujet ↔ jeu |

## Jaquettes

Comme pour les **films** :

- **Fichier** (JPEG, PNG, WebP) : enregistré dans `MONCINE_DATA/posters/` ;
- **URL HTTPS** : téléchargée automatiquement puis stockée en local ;
- affichage en liste (vignette) et sur la fiche.

Méthode : `GameRepository::savePoster()` → `PosterStorage::ensureLocalForOeuvre()`.

## Exemplaires (physique / démat)

| Type | Saisie |
|------|--------|
| Physique | CD/DVD, Disquette (plusieurs possibles) |
| Démat PC | Steam, GOG, Epic — plusieurs magasins, lien HTTPS optionnel par magasin |
| Démat console | Store imposé (PSN, Xbox, eShop) — **sans** lien personnalisé |

Le panneau démat s’adapte à la plateforme choisie (PC vs console) via JavaScript (`initGameEditionFields`).

### Icônes support (0.5.1)

Dans les listes et sur la fiche, chaque support affiche une **icône** :

- fichiers PNG/WebP/SVG dans `www/assets/img/game-editions/` ;
- noms attendus : `cd_dvd`, `steam`, `gog`, `epic` ;
- si aucun fichier n’existe, un **SVG de repli** est affiché (`GameEditionIcons::iconImageUrl()`).

## Fichiers attachés (0.5.1)

Sur la **fiche jeu**, le foyer peut joindre des fichiers utiles (abandonware, patch, archive, manuel scanné…) :

- **Limite** : 350 Mo par fichier (`UploadLimits::maxAttachmentBytes()`, même plafond que les PDF magazines) ;
- **Stockage** : sous-dossier `games/` dans `MONCINE_DATA` ;
- **Téléchargement** : via `/media-object.php` (contrôle d’accès foyer) ;
- **Interface** : panneau `_game_attachments_panel.php` (upload + liste + suppression).

## Notes et bibliothèque

Comme pour les **films** (table `historique`, réutilisée avec l’id bibliothèque) :

- **Note sur 10** : note personnelle + moyenne du foyer (sans date de session visible) ;
- **Date d’ajout** : `bibliotheque.created_at` (collection ou envie) ;
- **Envie → collection** : bouton « J’ai acheté » sur la fiche ou la liste des envies ;
- **Suppression** : icône poubelle en bas de fiche — retire l’entrée bibliothèque et la note.

## Linux sur jeux PC (0.5.1)

Deux cases **mutuellement exclusives** dans le formulaire d’ajout/modification (visible si plateforme = PC) :

| Case | Colonne | Affichage |
|------|---------|-----------|
| Testé sur Linux | `bibliotheque.tested_on_linux` | Badge pingouin Tux (fond bleu ciel) |
| Linux non supporté | `bibliotheque.linux_not_supported` | Pingouin barré (barre rouge) |

Les badges apparaissent sur la fiche jeu et dans les listes (Mes jeux, Mes envies).

## Accueil (onglet Jeux)

Quand l’onglet **Jeux** est actif, la page d’accueil (`home-jeu.php`) affiche :

- nombre de jeux en collection ;
- 5 derniers jeux notés ;
- 5 derniers ajouts collection et envies (vignettes cliquables) ;
- raccourcis vers Mes jeux, envies et ajout.

## Synergie magazines

**En place (0.5.0+) :**

- schéma `catalog_oeuvre_id` sur `magazine_subject` ;
- API `/rechercher-jeux-catalogue.php` ;
- **autocomplétion catalogue jeux** à l’ajout d’un sujet test / preview / interview sur un numéro magazine ;
- lien sujet → fiche jeu ; affichage sur fiche sujet, numéro et section « Dans vos magazines » sur la fiche jeu ;
- `MagazineGameLink::setSubjectCatalogLink()` pour rattacher un sujet.

**Prochaine étape (M5+) :**

- rattachement rétroactif des sujets existants ;
- recherche globale incluant le titre catalogue jeu.

## Liste, tri et modes d’affichage

Sur `/jeux.php` :

- **Colonnes triables** : titre, année, studio, genre, support, note, date d’ajout ;
- **Recherche texte** : titre, studio, genre, **acronymes** (`alternative_names`) ;
- **Trois vues** (`CollectionViewMode`) — même principe que Mes films :
  - **Liste** (défaut) ;
  - **Vignettes** (`?view=grid`) ;
  - **Bibliothèque** (`?view=shelf`) — tranches verticales (190 px), bord gauche de la jaquette, vignette au survol ; **toute la collection sur une page** (sans pagination).

Styles : classes `.game-shelf-*` ; script `initGameShelfHoverPreviews()` dans `app.js`.

## Statistiques

Page `/statistiques.php` (onglet Jeux) : répartition par plateforme, physique/démat, genres, décennies, sujets magazine reliés.

## Priorité produit

La phase **M4 (Jeux)** est prioritaire sur **M2 (BD)** et **M3 (Livres)** car elle prépare le pont avec les magazines déjà en production (PC Jeux, Joystick…).

**MVP livré en 0.5.0** ; enrichissements **0.5.1** — voir [CHANGELOG.md](../CHANGELOG.md) et [ROADMAP.md](../ROADMAP.md) § M4.
