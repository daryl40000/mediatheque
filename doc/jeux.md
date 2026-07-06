# Jeux vidéo (phase M4)

Documentation du module **Jeux** dans la médiathèque Monciné.

**Version : 0.7.11** · **Date : 2026-07-06**

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
| `platform` | Plateforme principale (`pc`, `ps5`, `switch`…) — conservée pour compatibilité |
| `platforms` | Toutes les plateformes du titre (liste CSV : `pc,ps5`) — migration **051** |
| `is_digital` | 1 = version démat, 0 = physique |
| `physical_supports` | Supports physiques possédés (CD/DVD, disquette/cartouche…) |
| `digital_stores` | Magasins démat (Steam, GOG, Epic, Battle.net…) + URLs |
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

### Table `game_platform` (migration **050**)

Liste des plateformes configurables par l’administrateur (`/plateformes-jeux.php`) : clé technique, libellé, ordre d’affichage, type (PC / console…), store console associé.

### Colonne `bibliotheque.owned_platforms` (migration **051**)

Plateformes que **vous possédez** pour cet exemplaire (sous-ensemble de `oeuvre_jeu.platforms`). Exemple : un jeu catalogue `pc,ps5` peut être dans votre collection avec `pc` seulement si vous ne l’avez que sur PC.

### Table `game_completion` (migration **054**, **0.6.9**)

| Colonne | Rôle |
|---------|------|
| `bibliotheque_id` | Entrée bibliothèque (jeu) |
| `user_id` | Utilisateur ayant terminé le jeu |
| `completed_at` | Date de fin (ISO `AAAA-MM-JJ`) |
| `created_at` | Horodatage d’enregistrement |

Plusieurs lignes par jeu et par utilisateur (rejeu, nouvelle partie). Les **notes** restent dans `historique` (sans date obligatoire) ; les **fins** sont séparées.

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
- `sql/migrations/047_oeuvre_jeu_igdb_metadata.sql` — franchise, modes, thèmes, acronymes ;
- `sql/migrations/049_bibliotheque_non_pretable.sql` — option « ne pas prêter » ;
- `sql/migrations/050_game_platform.sql` — table `game_platform` ;
- `sql/migrations/051_oeuvre_jeu_multi_platform.sql` — colonnes `platforms` et `owned_platforms`.

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

### Recherche et filtres Mes jeux (**0.6.9** → **0.7.0**)

Sur `/jeux.php`, en plus du champ texte (titre, studio, genre…) :

| Filtre | Paramètre URL | Interface (0.7.0) |
|--------|---------------|-------------------|
| Plateforme précise | `platform` | Liste déroulante |
| Type de support | `support` | `physical` ou `digital` |
| Magasin démat | `store` | `steam`, `epic`, `gog`, `psn`, `xbox`, `eshop` |
| Type de plateforme | `platform_kind` | Liens **statistiques** uniquement (`pc`, `console`, `mobile`, `multi`) |

**Barre de recherche (0.7.0)** : champ texte + menus sur **une seule ligne** (PC) — classes `collection-search--filters` / `collection-search__toolbar`.

**Filtre magasin (0.7.0)** : `GameDigitalStore::sqlStoredJsonContains()` analyse le JSON `digital_stores` via `json_each` (plus de `LIKE` fragile).

**Partage visiteur (0.7.0)** : mêmes filtres et colonnes **Note** / **Fini le** sur `/partage-jeux.php` — voir [partage-visiteur.md](partage-visiteur.md).

Les filtres issus des **statistiques** (genre, décennie, extensions…) restent actifs via les liens existants. Classe : `GameListFilter` ; template : `_games_collection_search_filters.php`.

### Jeu terminé (**0.6.9**)

Même principe que « marquer un film comme vu » :

- Formulaire sur la fiche `/jeu.php` (date du jour ou passée) ;
- **Plusieurs fins** enregistrables (historique sur la fiche) ;
- Liste Mes jeux : colonne **Fini le** (dernière date) à la place de « Ajouté le », triable ;
- Accueil onglet Jeux : **5 derniers jeux finis** ;
- Statistiques : jeux terminés au moins une fois, pourcentage de la collection, total des fins (reprises incluses).

Handler : `/marquer-jeu-fini.php` · Classe : `GameCompletionRepository`.

### Fiche jeu — actions en bulles (**0.7.11**)

Sur `/jeu.php`, quatre **icônes** sous la jaquette (sidebar) remplacent les anciens blocs inline :

| Icône | Action |
|-------|--------|
| Étoile | Noter ou modifier son **ressenti** |
| Chrono | Saisir le **temps de jeu manuel** |
| Crayon | Modifier **mon exemplaire** (plateformes, supports, démat) |
| Coche | **Marquer comme terminé** (+ historique des fins dans la bulle) |

Les formulaires s’ouvrent dans une **bulle à droite** des icônes (sans faire défiler toute la page). Fermeture : clic ailleurs, Échap, ou second clic sur l’icône.

Partial : `templates/_game_detail_action_popovers.php` · JS : `initGameDetailQuickActions()` dans `app.js`.

### Temps de jeu manuel (**0.7.11**)

Pour les plateformes **sans synchro automatique** (Battle.net, temps Epic non importé, etc.) :

| Élément | Détail |
|---------|--------|
| Colonne | `bibliotheque.manual_playtime_minutes` (migration **061**) |
| Saisie | Bulle chrono sur la fiche jeu, ou formulaire exemplaire |
| Affichage | **Total** = temps Steam (`game_steam_stats`) + temps manuel |
| Handler dédié | `/modifier-jeu-exemplaire.php` avec `scope=playtime` |
| Classe | `GamePlaytime` (formatage, parsing formulaire, SQL tri) |

Exemple : WoW — saisir le temps affiché en jeu (`/played`, profil Battle.net). Le temps Steam reste additionné séparément si le jeu est aussi sur Steam.

### Statistiques temps de jeu (**0.7.11**)

Page `/statistiques.php` (onglet Jeux) — **deux cartes** :

1. **Temps de jeu total** — Steam + saisies manuelles
2. **Temps Steam** — synchronisation Steam uniquement

Le top « Jeux les plus joués » classe selon le **temps total**.

### Icônes support / magasin (**0.6.9**)

Fichiers dans `www/assets/img/game-editions/` : `cd_dvd`, `disquette`, `steam`, `gog`, `epic` (PNG ou SVG). La **disquette/cartouche** utilise une icône dédiée (CF2) en listes et sur la fiche jeu (section Exemplaires). Classe : `GameEditionIcons`.

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
| `/plateformes-jeux.php` | Admin : liste des plateformes jeux (**0.6.5**) |
| `/modifier-jeu.php?id=` | Modification fiche catalogue (admin) |
| `/rechercher-jeux-catalogue.php` | API JSON autocomplétion catalogue |
| `/marquer-joue.php` | Enregistrer une note sur 10 |
| `/marquer-jeu-fini.php` | Marquer un jeu comme terminé (date, plusieurs fois) |
| `/modifier-jeu-exemplaire.php` | Enregistrer mon exemplaire ou le temps manuel seul (`scope=playtime`) |
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
| `GameEditionIcons` | Icônes support (CD/DVD, disquette, Steam…) — PNG/SVG dans `www/assets/img/game-editions/` |
| `GameGenre` | Genres réutilisables (tags, comme magazines) |
| `GameCollectionStats` | Statistiques collection jeux |
| `GameCompletionRepository` | Fins de partie (date, historique, stats) |
| `GamePlaytime` | Temps total Steam + manuel, formatage, tri SQL (**0.7.11**) |
| `GamePlatform` | Liste et normalisation des plateformes (délègue au registre) |
| `GamePlatformRegistry` | Lecture plateformes depuis `game_platform` (**0.6.5**) |
| `GamePlatformAdmin` | CRUD admin plateformes (**0.6.5**) |
| `GamePlatformList` | Listes multi-plateformes CSV catalogue / bibliothèque (**0.6.5**) |
| `LoanEligibility` | Règles de prêt (films, jeux physiques) (**0.6.5**) |
| `LoanCatalog` | Listes prêts multi-domaines (**0.6.5**) |
| `GamePhysicalSupport` | Supports physiques (CD/DVD, disquette/cartouche) |
| `GameDigitalStore` | Magasins démat PC et stores console |
| `MagazineGameLink` | Validation et gestion du pont sujet ↔ jeu |
| `MagazineGameLinkMaintenance` | Rattachement rétroactif admin (pont magazine ↔ jeux) |

## Jaquettes

Comme pour les **films** :

- **Fichier** (JPEG, PNG, WebP) : enregistré dans `MONCINE_DATA/posters/` ;
- **URL HTTPS** : téléchargée automatiquement puis stockée en local ;
- affichage en liste (vignette) et sur la fiche.

Méthode : `GameRepository::savePoster()` → `PosterStorage::ensureLocalForOeuvre()`.

## Exemplaires (physique / démat)

| Type | Saisie |
|------|--------|
| Physique | CD/DVD, Disquette/cartouche (plusieurs possibles) |
| Démat PC | Steam, GOG, Epic, Battle.net — plusieurs magasins, lien HTTPS optionnel par magasin |
| Démat console | Store imposé (PSN, Xbox, eShop) — **sans** lien personnalisé |

Le panneau démat s’adapte aux **plateformes cochées** (PC vs console) via JavaScript (`initGameEditionFields`, `initGamePlatformFields`).

## Plateformes multi-exemplaires (0.6.5)

| Niveau | Colonne | Rôle |
|--------|---------|------|
| Catalogue | `oeuvre_jeu.platforms` | Toutes les plateformes du titre (ex. `pc,ps5`) |
| Catalogue | `oeuvre_jeu.platform` | Plateforme principale (1ʳᵉ de la liste, rétrocompatibilité) |
| Bibliothèque | `bibliotheque.owned_platforms` | Plateformes que **vous** possédez pour cet exemplaire |

### Saisie

- **Admin catalogue** : cases « Plateformes disponibles » sur création / édition fiche (`/oeuvre-jeu.php`, maintenance).
- **Utilisateur** : à l’ajout, choisir le jeu dans le catalogue puis cocher **« Mes plateformes »** uniquement parmi celles du titre.
- **Non admin** : ne peut pas créer une fiche catalogue jeu — doit choisir une suggestion du catalogue (comme pour les films), ou **proposer une nouvelle fiche** via `/proposer-jeu.php` (validation administrateur, comme pour les films).

### Admin plateformes

Page `/plateformes-jeux.php` (lien depuis maintenance catalogue) : ajout, modification, désactivation des clés (`pc`, `ps5`, `snes`, `switch`…).

## Propositions au catalogue (0.6.8)

Comme pour les **films**, un utilisateur non administrateur peut proposer une **nouvelle fiche jeu** au catalogue partagé :

| Étape | Page / action |
|-------|----------------|
| Proposer | `/proposer-jeu.php` (menu Paramètres → Proposer au catalogue, ou lien depuis `/ajouter-jeu.php`) |
| Suivi | `/mes-soumissions.php` |
| Validation admin | `/soumissions-catalogue.php` — accepter, refuser, ou accepter avec enrichissement **IGDB** |
| Après acceptation | L’utilisateur ajoute le jeu à sa collection via `/ajouter-jeu.php?oeuvre_id=…` |

Champs proposés : titre, plateformes, année, studio, éditeur, genres, jaquette, synopsis. Le domaine (`film` / `jeu`) est stocké dans `catalogue_soumissions.payload_json` (`submission_domain`).

Handlers : `/enregistrer-soumission.php` (film ou jeu), `/traiter-soumission.php` (admin). Code : `GameManualEdit`, `CatalogSubmission`, `CatalogSubmissionPayload`.

## Ajout à la bibliothèque (utilisateur, 0.6.5)

Lorsqu’un utilisateur **non administrateur** ajoute un film ou un jeu :

1. Il tape le titre et **choisit une ligne du catalogue** (autocomplétion).
2. Seuls les champs **exemplaire** s’affichent (plateformes possédées, supports physiques/démat, saga, prêt…).
3. Les métadonnées catalogue (studio, synopsis, IGDB, jaquette catalogue…) restent **masquées** — elles sont gérées par l’admin ou l’enrichissement IGDB.

## Foyer personnel (0.6.5)

Chaque compte dispose d’un **foyer** pour sa collection partagée :

- À l’**inscription** ou au **premier besoin**, le système crée automatiquement un foyer **« Mon foyer »** si aucun n’est associé (`FoyerRepository::ensurePersonalFoyerForUser`).
- Un utilisateur **seul** n’a pas besoin qu’un admin le rattache à un foyer pour ajouter des films ou des jeux.
- S’il **rejoint un groupe famille** plus tard, son `foyer_id` pointe vers le foyer partagé du groupe.

Voir aussi [mediatheque.md](mediatheque.md) (section foyers).

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

## Prêts entre amis (0.6.5)

Même système que pour les films : un ami peut demander un prêt depuis votre **profil public** (onglet Jeux → collection).

| Règle | Détail |
|-------|--------|
| Éligible | Jeu avec **support physique** coché (CD/DVD, disquette/cartouche…) |
| Refusé | Jeu **démat seul** (Steam, GOG, etc. sans support physique) |
| Option | Case **« Ne pas prêter cet exemplaire »** à l’ajout / modification (colonne `bibliotheque.non_pretable`) |
| Flux | Demande → acceptation (réservation) → validation le jour J → retour — page `/mes-prets.php` |

Les magazines ne sont pas prêtables (PDF / démat).

Voir aussi `LoanEligibility` et `LoanCatalog` dans le code ; migration **049**.

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

**Complété (0.6.3+) :**

- **rattachement rétroactif** admin (`/maintenance-magazine-jeux-liens.php`) ;
- **recherche globale** magazines incluant le titre catalogue jeu (sujets reliés) ;
- section sujets magazine sur fiche catalogue admin (`/oeuvre-jeu.php`) ;
- guide homonymes : [pont-magazine-jeu.md](pont-magazine-jeu.md).

## Liste, tri et modes d’affichage

Sur `/jeux.php` :

- **Colonnes triables** : titre, année, studio, genre, support, note, **fini le**, **temps de jeu** (total Steam + manuel) ;
- **Recherche texte** : titre, studio, genre, **acronymes** (`alternative_names`) ;
- **Trois vues** (`CollectionViewMode`) — même principe que Mes films :
  - **Liste** (défaut) ;
  - **Vignettes** (`?view=grid`) ;
  - **Bibliothèque** (`?view=shelf`) — tranches verticales (190 px), bord gauche de la jaquette, vignette au survol ; **toute la collection sur une page** (sans pagination).

Styles : classes `.game-shelf-*` ; script `initGameShelfHoverPreviews()` dans `app.js`.

## Statistiques

Page `/statistiques.php` (onglet Jeux) : répartition par plateforme, physique/démat, genres, décennies, sujets magazine reliés, **temps de jeu total** et **temps Steam** (**0.7.11**), jeux les plus joués (selon temps total).

## Import bibliothèque GOG (à venir)

Spécification détaillée (non implémentée) : [import-gog.md](import-gog.md).

Résumé : connexion compte GOG → rapprochement avec le **catalogue existant** → validation utilisateur si le match est incertain → ajout à Mes jeux ou **fusion du magasin GOG** (`digital_stores`) si le jeu est déjà en collection (Steam, physique, etc.).

## Priorité produit

La phase **M4 (Jeux)** est prioritaire sur **M2 (BD)** et **M3 (Livres)** car elle prépare le pont avec les magazines déjà en production (PC Jeux, Joystick…).

**MVP livré en 0.5.0** ; enrichissements **0.5.1** — voir [CHANGELOG.md](../CHANGELOG.md) et [ROADMAP.md](../ROADMAP.md) § M4.
