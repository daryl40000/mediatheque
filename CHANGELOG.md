# Journal des versions (Médiathèque)

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Les numéros suivent le [versionnement sémantique](https://semver.org/lang/fr/).

**Lignée :** ce dépôt est un **fork** de [Monciné](README.md) (dvdthèque films). L’historique Monciné **0.7 → 1.0.0** reste dans ce fichier ; la branche Médiathèque repart en **0.1.0** pour le multi-médias.

**Tags Git recommandés :** `v0.1.0` (Médiathèque) ; historique Monciné `v1.0.0`, `v0.8.0`, etc.

---

## [0.7.22] — 2026-07-13

**Magazines : retirer un PDF d’un numéro**

### Ajouté

- **Fiche numéro magazine** : bouton **Retirer le PDF** (le numéro reste en collection) + option de remplacement.

### Corrigé

- **Tests** : réinitialisation correcte des fonctions SQLite (collation `FRENCH_NOCASE`, fonction `fold_search`) et du cache foyer en PHPUnit.
- **Recherche numéros (série)** : une requête numérique (ex. `20`) cible bien le **numéro**, sans bruit sur les dates.

---

## [0.7.21] — 2026-07-13

**Correctif icône raccourci Android, validation CSRF uploads**

### Corrigé

- **Raccourci écran d’accueil (Android)** : ancien logo Monciné — `www/favicon.ico` aligné sur le logo Médiathèque, manifeste PWA (`manifest.webmanifest`), partial `_head_icons.php`.
- **Sécurité CSRF** : validation du jeton sur `traiter-numero-magazine.php`, `enregistrer-numero-magazine.php`, `enregistrer-fichier-jeu.php` (uploads PDF/couverture magazine, fichiers joints jeu).

### Technique

- Icônes PWA 192 / 512 px ; type MIME `.webmanifest` dans `.htaccess`.
- Paramètre `?v=` sur les liens favicon (invalidation cache à chaque release).

---

## [0.7.20] — 2026-07-11

**Correctif défilement menu mobile (smartphone / iOS)**

### Corrigé

- **Menu navigation mobile** : impossible de faire défiler les entrées du menu sur petit écran — panneau fixe sous l’en-tête avec scroll interne, verrouillage du scroll de la page (dont iOS via `position: fixed` + `touchmove`).

### Technique

- CSS : `.site-nav.is-mobile-nav-panel`, `body.is-nav-open` (mobile), fallback flex scrollable.
- JS : `initMobileNav()` — sauvegarde/restauration `scrollY`, `syncMobileNavPanel()`, recalcul au `resize`.

---

## [0.7.19] — 2026-07-11

**Correctif suppression jeu catalogue, retrait enrichissement GOG/Epic**

### Corrigé

- **Catalogue admin** : suppression d’une fiche **jeu** (tous onglets confondus) — `CatalogAdmin::deleteOeuvre()` utilise `findByIdForAdmin()` au lieu de `findById()` filtré par l’onglet actif.

### Retiré

- **Enrichissement automatique** des liens magasins GOG/Epic (page Importer, maintenance, API catalogue).
- Fichiers associés : `StoreLinkEnricher`, clients recherche GOG/Epic, file de relecture admin.

### Conservé

- **Saisie manuelle** des liens Steam / GOG / Epic sur `/oeuvre-jeu.php` (table `oeuvre_store_links`).

### Technique

- Test : `CatalogMediaDomainTest::testCatalogAdminDeletesGameWhileFilmTabActive`.

---

## [0.7.18] — 2026-07-11

**Connexion par pseudo, pages Compte/Import compactes, menu navigation**

### Ajouté

- **Connexion** : identifiant **e-mail ou pseudo** (si renseigné sur le compte) — `LoginIdentifier`, migration `064_utilisateur_pseudo_login.sql`.
- **Unicité du pseudo** (insensible à la casse) à la création, inscription et modification du profil.
- **Bulles d’aide** (icône « i ») : partials `_form_label_info.php`, `_heading_with_info.php` ; pages **Compte** et **Importer / exporter** allégées.

### Modifié

- **`/parametres.php`** : textes d’aide déplacés dans les bulles ; formulaire plus compact.
- **`/import.php`** : mêmes bulles sur les titres de section et options avancées.
- **Menu Paramètres / Gestion** (desktop) : fermeture automatique au retrait de la souris (`initDesktopNavMenus`).

### Technique

- Tests : `LoginIdentifierTest`, `AuthLoginIdentifierTest`.
- `ROADMAP.md` synchronisée (**0.7.18**).

---

## [0.7.17] — 2026-07-10

**Sujets magazine (vignettes, multi-médias), magazines sur fiche jeu, correctifs catalogue**

### Ajouté

- **Fiche numéro magazine** : sujets reliés en **bandeau horizontal** de couvertures (défilement, bulle test/preview…, lien bibliothèque ou catalogue).
- **Ajout de sujet** : menu **type de média** (jeu, film) ; **création automatique** d’une fiche catalogue minimale si le titre n’existe pas encore.
- **Fiche jeu** : bouton **Magazines** → page `/jeu-magazines.php` (grille de couvertures + tags par numéro).
- **Collections films / jeux** : mode **vignettes seules** avec infos en bulle au survol.
- **`MagazineSubjectCatalogLink`**, API `/rechercher-catalogue-sujet-magazine.php`.

### Modifié

- Pont magazine ↔ catalogue : lien `catalog_oeuvre_id` utilisable pour un **film** ou un **jeu** (test / preview / interview).

### Corrigé

- **Catalogue admin** : suppression unitaire et **groupée** fonctionnelles (structure HTML des formulaires).
- **Fiche numéro** : message **« Sujet retiré de ce numéro »** après suppression (plus « Sujet enregistré »).
- **Recherche globale** : rendu du template `recherche` (plus de double `.php`).

### Technique

- `templates/_magazine_issue_subjects_strip.php`, `_game_magazines_link.php`, `_game_magazine_issues_grid.php`, `jeu-magazines.php`, `initMagazineSubjectStripHoverBubbles`, `initCollectionGridHoverBubbles`.

---

## [0.7.16] — 2026-07-09

**Séries BD/magazines (catalogue complet, filtre mémorisé), redirection après ajout catalogue**

### Ajouté

- **Magazines — fiche série** : affichage de **tous les numéros du catalogue** (comme les BD), compteur **« X possédé(s) sur Y »**, synchronisation automatique à l’ouverture de la série.
- **Séries BD et magazines** : le filtre **Afficher** (Tous / Possédés / Non possédés / Hors-série) est **mémorisé** dans le navigateur pour les visites suivantes.

### Modifié

- **Catalogue admin** : après l’ajout d’une œuvre (film ou jeu), ouverture automatique de la **fiche catalogue** correspondante (`/oeuvre.php` ou `/oeuvre-jeu.php`).
- **Séries BD** : synchronisation des tomes catalogue manquants à chaque visite de la fiche série (collection).

### Technique

- `MagazineLibraryQuery::countCatalogIssuesForSeries`, `countPossessedIssuesForSeries`, `View::urlWithQuery`, `initSeriesPossessionFilterMemory` (`app.js`).

---

## [0.7.15] — 2026-07-08

**Catalogue admin (suppression groupée), liens icônes magasins GOG, import ABM couvertures**

### Ajouté

- **Catalogue admin** (`/catalogue.php`) : cases à cocher et **suppression groupée** des fiches sélectionnées.

### Modifié

- **Import ABM** (`abm-fetch-catalog.php`) : les espaces dans les URLs de couverture sont encodés en **`%20`** dans le JSON exporté (ex. revue « PC Team »).

### Corrigé

- **Icônes magasins** sous la jaquette jeu : liens **GOG** et **Epic** cliquables comme Steam (`GameEditionIcons`, `CatalogGameStoreLinks`, hydratation `catalog_store_urls`) ; prise en charge des URLs GOG avec segment `/fr/game/` ou `/en/game/`.
- **Possession démat** : retirer un lien catalogue n’efface plus la case « je possède ce magasin » (`GameDigitalStore::clearStoreUrl`).
- **Catalogue admin** : après une suppression, **conservation de la page** courante (et recul d’une page si la page devient vide).
- **Catalogue admin** : pastille du filtre média active affichée avec la **couleur du type** choisi (plus la teinte « Magazines » par défaut) ; thème de page aligné sur le filtre.

### Technique

- `CatalogAdmin::deleteOeuvres`, `AbmApiParser::normalizeCoverUrl`, tests `AbmApiParserTest`, `GameEditionIconsLinkTest`, `CatalogGameStoreLinksTest`.

---

## [0.7.14] — 2026-07-07

**Recherche globale, liens magasins catalogue séparés de la possession, correctifs bibliothèque démat**

### Ajouté

- **Recherche globale** : barre dans l’en-tête (entre notifications et navigation) — suggestions en direct, page `/recherche.php`, API `/rechercher-global.php` ; cherche dans **toute la bibliothèque** (films, jeux, BD, magazines) et le **catalogue partagé** (tous médias).
- **Liens magasins catalogue jeux** : table `oeuvre_store_links` (migration **063**), saisie manuelle admin sur `/oeuvre-jeu.php`, section publique **« Disponible sur »** (Steam, GOG, Epic).
- **Enrichissement automatique GOG/Epic** (base technique) : clients catalogue, matcher, file de relecture admin — voir [doc/enrichissement-magasins.md](doc/enrichissement-magasins.md) ; automatisation mise en pause côté produit, saisie manuelle prioritaire.
- **Catalogue admin** : filtre par type de média (Tous / Films / Jeux / BD / Magazines…) sur `/catalogue.php`.

### Modifié

- **`digital_stores`** : ne représente plus que la **possession** (cases « Exemplaires possédés ») ; les **URLs magasins** sont sur `oeuvre_store_links` (catalogue).
- **Formulaire bibliothèque jeux** : retrait des champs « lien magasin » ; l’utilisateur coche uniquement Steam / GOG / Epic / Battle.net s’il possède le jeu.

### Corrigé

- **Bibliothèque** : décocher un magasin dématérialisé enregistre correctement la suppression (`GameLibraryAttach` remplace la liste au lieu de fusionner).
- **Liens magasin catalogue** : n’ajoutent plus le jeu à la bibliothèque ni les icônes de possession par erreur ; migration des anciens liens depuis `digital_stores` à l’enregistrement admin.

### Technique

- `GlobalSearch`, `CatalogGameStoreLinks`, `OeuvreStoreLinkRepository`, `StoreLinkEnricher` (+ clients GOG/Epic, matcher, normalizer).
- Tests : `GlobalSearchTest`, `CatalogGameStoreLinksTest`, `StoreLink*Test`, `GameEditionIconsLinkTest`.

---

## [0.7.13] — 2026-07-06

**Liens catalogue sur les sagas et correctif défilement bandeau saga**

### Corrigé

- **Jeux liés (saga, extensions, remakes)** : les jaquettes non possédées sont cliquables pour tout utilisateur connecté (lien vers `/oeuvre-jeu.php`), plus seulement pour l’admin.
- **Films de saga** : même correction sur `/film.php` (lien vers `/oeuvre.php`).
- **BD — bandeau série** : tomes voisins absents de la bibliothèque liés vers `/oeuvre-bd.php`.
- **Fiche jeu bibliothèque** : bandeau saga long ne déborde plus de la page (défilement horizontal interne).

---

## [0.7.12] — 2026-07-06

**Profil ami → fiches catalogue, actions envies sous la jaquette, fiches détaillées harmonisées**

### Ajouté

- **Profil public** : clic sur un film, jeu, BD ou magazine (bandeaux, grilles, films vus) ouvre la **fiche catalogue** de l’œuvre — pas la fiche bibliothèque de l’ami.
- **Fiches catalogue consultables** : tout utilisateur connecté peut voir `/oeuvre.php`, `/oeuvre-jeu.php`, `/oeuvre-bd.php`, `/oeuvre-magazine.php` ; l’édition catalogue reste réservée aux admins.
- **Actions sous la jaquette (catalogue)** : bouton **cœur** (envies) et **+** (collection) sur les fiches catalogue lorsque l’œuvre n’est pas encore dans votre bibliothèque — partials `_catalog_oeuvre_poster_sidebar.php` / `_catalog_oeuvre_sidebar_actions.php`.
- **Fiches détaillées harmonisées** : films, BD et magazines alignés sur le modèle jeu — sidebar jaquette, actions en bulles (`_film_detail_sidebar`, `_bd_detail_sidebar`, `_magazine_detail_sidebar`).
- **BD — bandeau série** : sur la fiche tome, affichage des tomes voisins (4 avant / 4 après) avec grisage selon possession (`BdSeriesContext`, `DetailLibraryState`).
- **BD — envies** : bouton cœur sur les tomes non possédés (`_bd_wishlist_action.php`, `BdLibraryAttach::addAlbumToWishlist`).
- **Jeux — saga extensions/remakes** : sections **Jeu de base** / **Jeu d’origine** + bandeau saga sur les fiches extension (`GameRelatedSections`).

### Modifié

- **Retour depuis une fiche catalogue** : lien « Profil » si l’on vient du profil d’un ami (`?profile_user=`, `View::catalogOeuvreDetailUrlFromProfile`).
- **Fiche catalogue BD** : mise en page complète (jaquette, résumé, métadonnées) au lieu de la vue minimale admin.
- **Films / BD** : détails sur deux colonnes ; ressenti affiché à côté de l’année dans le titre.
- **Magazines** : libellé **Preview** (plus « Preview / avant-première ») ; retrait d’un numéro via icône poubelle.
- **Formulaires Lu / Vu** : sans ressenti intégré (ressenti via bulle dédiée sur la fiche film).
- **`ajouter-oeuvre-bibliotheque.php`** : prise en charge des **BD** ; retour vers la fiche catalogue film (plus `addFilmChoiceUrl`).

### Technique

- `CatalogAdmin::denyUnlessCatalogAvailable()` — consultation catalogue vs `denyUnlessAccess()` (admin).
- `View::catalogOeuvreDetailUrlFromProfile()`, `catalogOeuvrePageBackUrl()`.
- JS générique `initDetailQuickActions()` pour les bulles d’actions fiches détaillées.
- Tests : `GameRelatedSectionsTest`, `GameEditionIconsLinkTest`.

---

## [0.7.11] — 2026-07-06

**Fiche jeu : actions en bulles, temps manuel, stats temps total / Steam**

### Ajouté

- **Temps de jeu manuel** : colonne `bibliotheque.manual_playtime_minutes` (migration `061`) pour Battle.net, Epic hors sync, etc. — classe `GamePlaytime`, somme **Steam + manuel** partout où le temps est affiché.
- **Fiche jeu — actions rapides** : quatre icônes sous la jaquette (noter, temps, modifier exemplaire, marquer terminé) ouvrant des **bulles** à droite, sans sections inline encombrantes sur la page.
- **Statistiques jeux** : deux cartes distinctes — **temps de jeu total** et **temps Steam** (synchro uniquement).

### Modifié

- **Liste Mes jeux** : colonne temps de jeu = **total** (Steam + saisie manuelle) ; affichage aussi en vue vignettes et au survol bibliothèque.
- **Fiche jeu** : retrait des blocs inline « Mon ressenti », « Marquer terminé » et « Modifier mon exemplaire » (contenu déplacé dans les bulles) ; icône **coche** pour « terminé ».
- **Édition catalogue jeu** : formulaire admin sans pollution des champs « mon exemplaire » (`$catalogEditOnly`).

### Corrigé

- **Édition fiche catalogue jeu** : enregistrement admin ne modifiait plus par erreur l’exemplaire personnel (plateformes / Steam).

### Technique

- `www/modifier-jeu-exemplaire.php` (exemplaire + scope `playtime`), `GameRepository::updateLibraryPlaytimeOnly()`.
- Partial `templates/_game_detail_action_popovers.php` ; JS `initGameDetailQuickActions()` (positionnement bulles, fermeture Échap / clic extérieur).
- Tests : `GamePlaytimeTest`, `GameCollectionStatsSteamTest` (total vs Steam).

---

## [0.7.10] — 2026-07-05

**Fusion catalogue, import Steam utilisateur, fiche catalogue jeu**

### Ajouté

- **Fusion manuelle de fiches catalogue** : panneau admin sur les fiches film, jeu et magazine (autocomplétion, choix de la fiche conservée) — `CatalogMaintenance::mergeOeuvres`.
- **Import Steam (utilisateurs)** : liste unique à cocher ; jeux au catalogue ajoutés directement, absents proposés au catalogue et ajoutés en attente dans Mes jeux.
- **Import Steam (admins)** : section « Relier au catalogue » réservée aux administrateurs.
- **Validation proposition catalogue** : ajout automatique à la bibliothèque du demandeur et import des stats Steam (`SteamLibraryImporter::fulfillApprovedSubmission`).

### Modifié

- **Fiche catalogue jeu** : même mise en page que la fiche bibliothèque (sidebar, détails en 2 colonnes, saga sous le titre, outils admin regroupés).
- **Statistiques jeux** : retrait du sous-texte « X jeux avec temps enregistré » sous le temps Steam cumulé (le total couvre toute la collection).

### Corrigé

- **Page sagas jeux** : erreur 500 (`GameFranchiseRepository::findByFranchise` — jointure Steam corrigée).
- **Fusion catalogue** : recherche des fiches sans filtre d’onglet actif ; refus si les deux fiches ne sont pas du même type de média.

### Technique

- Endpoints `fusionner-oeuvre-catalogue.php`, `rechercher-oeuvres-catalogue.php` ; partial `_catalog_oeuvre_merge_panel.php`.
- Seed catalogue `install_seed/moncine-catalogue-2026-07-05.csv`.

---

---

## [0.7.10] — 2026-07-05

**Fusion catalogue, import Steam utilisateur, fiche catalogue jeu**

### Ajouté

- **Fusion manuelle de fiches catalogue** : panneau admin sur les fiches film, jeu et magazine (autocomplétion, choix de la fiche conservée) — `CatalogMaintenance::mergeOeuvres`.
- **Import Steam (utilisateurs)** : liste unique à cocher ; jeux au catalogue ajoutés directement, absents proposés au catalogue et ajoutés en attente dans Mes jeux.
- **Import Steam (admins)** : section « Relier au catalogue » réservée aux administrateurs.
- **Validation proposition catalogue** : ajout automatique à la bibliothèque du demandeur et import des stats Steam (`SteamLibraryImporter::fulfillApprovedSubmission`).

### Modifié

- **Fiche catalogue jeu** : même mise en page que la fiche bibliothèque (sidebar, détails en 2 colonnes, saga sous le titre, outils admin regroupés).
- **Statistiques jeux** : retrait du sous-texte « X jeux avec temps enregistré » sous le temps Steam cumulé (le total couvre toute la collection).

### Corrigé

- **Page sagas jeux** : erreur 500 (`GameFranchiseRepository::findByFranchise` — jointure Steam corrigée).
- **Fusion catalogue** : recherche des fiches sans filtre d’onglet actif ; refus si les deux fiches ne sont pas du même type de média.

### Technique

- Endpoints `fusionner-oeuvre-catalogue.php`, `rechercher-oeuvres-catalogue.php` ; partial `_catalog_oeuvre_merge_panel.php`.
- Seed catalogue `install_seed/moncine-catalogue-2026-07-05.csv`.

---

## [0.7.9] — 2026-07-05

**Import Steam, refonte fiche jeu, statistiques temps de jeu**

### Ajouté

- **Import bibliothèque Steam** : page `/import-steam.php` (aperçu puis validation), liaison AppID ↔ catalogue avec mémorisation manuelle (`game_steam_appid_map`), temps de jeu importés (`game_steam_stats`) — voir [doc/import-steam.md](doc/import-steam.md).
- **Profil** : champ SteamID64 (utilisé par l’import ; retrait du SteamID de test sur `/import.php`).
- **Mes jeux** : tri par plateforme, saga et temps Steam ; affichage du temps de jeu Steam en liste/grille/étagère.
- **Statistiques jeux** : temps Steam cumulé et top 10 des jeux les plus joués.
- **Maintenance catalogue** : bouton « Conserver toutes les fiches » pour masquer un groupe de doublons légitimes (titres, TMDB, magazines).
- **Fiche jeu** : colonne latérale (jaquette, terminé, éditions, temps Steam), détails en 2 colonnes, saga sous le titre, ressenti (icône à droite du titre + crayon discret).

### Modifié

- **Fiche jeu** : retrait des acronymes IGDB et de la date « Ajouté le » dans les détails.
- **IGDB** : résolution Steam via `external_games` ; enrichissement magasins démat (`GameDigitalStore::mergeStore`).

### Technique

- Migrations `058_steam_import.sql`, `059_steam_appid_map.sql`, `060_catalog_duplicate_dismissal.sql`.
- Bibliothèques `SteamWebApiClient`, `SteamLibraryImporter`, `SteamGameResolver`, tests unitaires et d’intégration associés.

---

## [0.7.81] — 2026-07-05

**Qualité code : CSS, autocomplétions JS, lisibilité PHP**

### Modifié

- **`app.js`** : moteur partagé `attachCatalogAutocomplete` pour les autocomplétions catalogue (films, jeux, magazines) — ~180 lignes en moins.
- **`style.css`** : fusion des sélecteurs dupliqués (`.site-header`, `.btn-sm`, table films triable).

### Corrigé

- **`View.php`**, **`ShareLinkService.php`** : affectation dans une condition remplacée par une forme plus lisible (`CollectionViewMode::queryValue`).

---

## [0.7.8] — 2026-07-04

**Onglets Musique et Livres (placeholder), refactor BD et magazines (phase B), navigation onglets**

### Ajouté

- **Onglet Musique** (couleur ambre) : pages placeholder `/musique.php` et `/musique-envies.php` (phase M8 — après les livres).
- **Pages Livres dédiées** : `/livres.php` et `/livres-envies.php` (remplace la redirection vers `/films.php`).
- **Spécification import musique** : [doc/import-musique.md](doc/import-musique.md) (vinyles, CD, schéma `oeuvre_musique`).
- **Refactor BD (phase B pilote 2)** : `BdCatalogSql`, `BdTomeOrdre`, `BdCatalogWriter`, `BdLibraryQuery`, `BdCatalogUpdater`, `BdCatalogCreator`, `BdLibraryAttach`, `BdPosterService` — `BdRepository` réduit à ~514 lignes.
- **Refactor magazines (phase B pilote 3)** : 11 classes extraites (`MagazineLibraryQuery`, `MagazinePdfService`, …) — `MagazineRepository` réduit à ~481 lignes.
- **Tests** : `BdTomeOrdreTest`, cas navigation Musique/Livres dans `MediaDomainTest`.
- **Seed catalogue** : `install_seed/moncine-catalogue-2026-07-03.csv`.

### Modifié

- `MediaDomain` : domaine `musique`, thème ambre, chemins collection/envies.
- `templates/media-domain-soon.php` : liste dynamique des onglets déjà disponibles.
- `roadmap-amelioration-code.md` : pilotes 2 et 3 phase B cochés.

### Corrigé

- **Navigation onglets** : depuis Musique ou Livres, un clic sur un onglet actif (Jeux, BD, Films…) ouvre la bonne collection au lieu de rester sur la page « bientôt » (`MediaDomainGuards::isPlaceholderCollectionPath`).

---

## [0.7.7] — 2026-07-03

**Jeux : Battle.net, ressentis sociaux discrets, refactor GameRepository (phase B)**

### Ajouté

- **Jeux PC — magasin Battle.net** : choix démat, icône `battlenet.png`, filtre liste.
- **Ressentis sociaux discrets** : popover « Foyer et amis » à côté du ressenti personnel (films, jeux, BD) via `_ressenti_fiche_row.php`.
- **Refactor jeux (phase B)** : `GameLibraryQuery`, `GameCatalogUpdater`, `GameCatalogCreator`, `GameLibraryAttach`, `GamePosterService` — `GameRepository` réduit à ~540 lignes.
- **Tests** : `GameCatalogUpdaterTest`, test Battle.net dans `GameEditionTest`.
- **Doc** : [roadmap-amelioration-code.md](roadmap-amelioration-code.md) (qualité code, phase B pilote jeux).

### Modifié

- Fiches film / jeu / BD : retrait du panneau volumineux « Ressentis autour de cette œuvre ».

### Corrigé

- **Fiche jeu** : erreur de syntaxe PHP (`endif` en trop dans `templates/jeu.php`).

---

## [0.7.6] — 2026-06-16

**Ressentis : remplacement des notes 1–10 et suppression de la moyenne foyer**

### Ajouté

- **Système de ressenti** (5 paliers : J’adore → Je déteste) : `RessentiNote`, badges SVG/PNG, sélecteur sur les fiches et formulaires (film, jeu, BD).
- **Icônes PNG** : `www/assets/icons/ressenti/` (repli SVG si fichier absent).
- **Ressentis sociaux** : panneau foyer + amis sur les fiches film, jeu et BD (`SocialRessentiService`, partials `_ressenti_*`).
- **Migration** `057_ressenti_notes.sql` : conversion des anciennes notes 1–10 vers 1–5.
- **Statistiques films** : répartition par ressenti (graphique icônes), sections **Coups de cœur** et **Films les moins aimés** (2 colonnes).
- **Tests** : `RessentiNoteTest`, test coups de cœur dans `CollectionStatsViewingDurationTest`.

### Modifié

- Listes, impressions et profil public : icônes de ressenti au lieu de `X/10`.
- Import CSV : les notes 1–10 sont converties vers le ressenti correspondant.
- Fiches film / jeu / BD : libellé « Mon ressenti » retiré en tête de fiche ; tailles d’icônes harmonisées (fiche 60 px, listes 30 px, stats 32 px).

### Corrigé

- **Statistiques films** : exclusion des jeux et autres médias (filtre `media_domain = film` dans `CollectionStats`).
- **Coups de cœur** : liste vide malgré un compteur correct (paramètre PDO dans `HAVING` SQLite).

### Supprimé

- Note moyenne du foyer sur les listes et fiches.

---

## [0.7.5] — 2026-06-16

**BD / Magazines : modification série BD, repli couverture tome/numéro 1, maintenance affiches**

### Ajouté

- **BD — modifier une série** : `/modifier-serie-bd.php` (titre, type, éditeur, notes, couverture) ; bouton sur la fiche série.
- **Affiches de série** : `SeriesPoster` — repli automatique sur la couverture du **tome 1** (BD) ou **numéro 1** (magazine) si pas de logo dédié ; `View::seriesPosterSrc()`.
- **Tests** : `SeriesPosterTest` ; maintenance ignore les logos série (`s{id}.jpg`).

### Corrigé

- **Maintenance catalogue** : les fichiers `/posters/s{id}.jpg` référencés par `series.poster_url` ne sont plus proposés comme affiches orphelines.
- **Couverture série** : si le logo série pointe vers un fichier supprimé, bascule vers le tome/numéro 1 ; priorité explicite sur le volume n°1.
- **SeriesRepository** : `update()` et `findById()` en CLI/tests ne bloquent plus sur le domaine média actif.

### Modifié

- **Documentation** : [doc/bd.md](doc/bd.md), [doc/magazines.md](doc/magazines.md) ; hint maintenance catalogue.

## [0.7.4] — 2026-06-16

**Magazines : hors-série, doublons et fiche catalogue — BD tome 0 — maintenance catalogue — refactor jeux/films**

### Ajouté

- **Magazines — doublons catalogue** : section **Doublons magazines (série + numéro)** sur `/maintenance-catalogue.php` (`CatalogMaintenance::findDuplicateMagazineIssueGroups()`).
- **Maintenance catalogue** : liste détaillée des fiches en doublon avec lien **Ouvrir la fiche** (`_catalog_maintenance_duplicate_oeuvres.php`, `oeuvreSummariesForIds()`).
- **BD — tome 0** : saisie et tri du tome 0 (préquel, hors chronologie) ; migration `056_oeuvre_bd_hors_serie.sql` ; doc [doc/bd.md](doc/bd.md).
- **Films** : actions groupées extraites vers `FilmBulkActionService` ; `ValidationException`.
- **Jeux** : début du découpage de `GameRepository` (`GameFormPayload`, `GameCatalogSql`, `GameCatalogWriter`, `GameLibraryFields`).
- **Formulaires** : `FormCheckbox` pour une lecture fiable des cases à cocher POST.
- **Tests** : magazines (HS partagé, bascule catalogue, mise à jour partielle), maintenance doublons magazines, `FormCheckboxTest`, `GameFormPayloadTest`, `FilmBulkActionServiceTest`.

### Corrigé

- **Fiche catalogue magazine** : champ couverture en `type="text"` — les chemins `/posters/…` ne sont plus rejetés par le navigateur.
- **Magazines — hors-série** : enregistrement à la modification (catalogue et collection) ; correction de l’ordre `$horsSerie` dans `updateCatalogByOeuvreId` ; message explicite si le retrait du HS entre en conflit avec un numéro classique existant.
- **Magazines — HS vs doublons** : un classique et un hors-série peuvent partager le même libellé de numéro (`findCatalogIssueBySeriesNumero` filtre `est_hors_serie`).
- **Magazines — mise à jour** : `updateIssue` ne réécrit plus toute la ligne œuvre (évite l’erreur SQL sur `saga`).
- **Import admin** : balise `<?php endif; ?>` manquante sur `/import.php` (page blanche).
- **BD** : badge et filtre hors-série sur les grilles et pages partage/profil.

### Modifié

- **Documentation** : [doc/magazines.md](doc/magazines.md), [doc/bd.md](doc/bd.md).

## [0.7.3] — 2026-06-16

**BD / Manga : couverture par URL et correction possession à l’ajout**

### Ajouté

- **Couverture par URL** : à l’ajout ou à la modification d’un tome, champ URL HTTPS (comme les jeux) ; téléchargement local via `BdRepository::savePoster()` ; partial `_bd_cover_fields.php`.
- **Tests** : possession à la création, `savePoster` avec chemin local existant.

### Corrigé

- **Possession à l’ajout** : cocher « Je possède cet exemplaire » lors de la création d’un tome est bien enregistré dès la première sauvegarde (`BibliothequeRepository::normalizeSupportPhysiqueForStorage()` reconnaît les supports BD).

### Modifié

- **Documentation** : [doc/bd.md](doc/bd.md) (section couverture).

## [0.7.2] — 2026-06-16

**BD / Manga : polish profil public, partage visiteur, impression et comptage possession**

### Ajouté

- **Profil public BD** : onglet sur `/utilisateur.php?domain=bd`, séries, tomes (`utilisateur-serie-bd`, `utilisateur-album-bd`) ; stats et bandeaux récents.
- **Partage visiteur BD** : `/partage-bd.php`, `/partage-serie-bd.php`, `/partage-album-bd.php` ; option BD dans `/gerer-partages.php` ; boutons Partager sur Mes BD et envies.
- **Liste imprimable** : `/imprimer-serie-bd.php` et bouton « Exporter en PDF » sur la fiche série (`BdPrintListService`).
- **Classes** : `ShareLinkBdRepository`, `BdPrintListService` ; méthodes BD dans `UserPublicProfileService`.
- **Tests** : `UserPublicProfileBdTest`, `BdPrintListServiceTest`, partage BD dans `ShareFeaturesTest`.

### Corrigé

- **Comptage possession** : tomes référencés mais non possédés ne sont plus comptés comme possédés (stats, liste séries « x possédé sur z », en-tête de série).
- **Profil public** : l’onglet BD n’était plus traité comme les films (grille incorrecte).

### Modifié

- **Documentation** : [doc/bd.md](doc/bd.md) (pages profil, partage, impression).

## [0.7.1] — 2026-06-16

**Correctif tri « Fini le » sur Mes jeux**

### Corrigé

- **Mes jeux** : clic sur la colonne **Fini le** provoquait une erreur **500** (SQL invalide `ORDER BY … DESC DESC`) ; alignement sur la logique déjà corrigée pour le partage visiteur (`GameRepository::listInLibrary()`).

## [0.7.0] — 2026-06-16

**Partage visiteur, recherche jeux unifiée, colonnes historique sur listes partagées**

### Ajouté

- **Partage jeux — filtres** : sur `/partage-jeux.php`, mêmes menus que Mes jeux (**plateforme**, **type de support** physique/démat, **magasin démat.** Steam/Epic/…) pour les visiteurs non connectés ; `ShareLinkGameRepository::findAllForLink()` accepte `GameListFilter` ; `ShareLinkService::collectionQueryParams()` propage les filtres dans tri, vues et fiches.
- **Partage jeux — colonnes** : **Note** et **Fini le** dans le tableau visiteur (`_partage_games_list.php`) ; tri `note` / `finished_at` déjà supporté côté dépôt.
- **Partage films — colonnes** : **Note** et **Dernière vue** sur les listes collection (`ShareLinkFilmRepository::historyExtrasSql()`, `_partage_collection_list.php`).
- **Mes jeux — filtre support** : menu **Physique uniquement** / **Dématérialisé uniquement** (`GameListFilter::supportChoices()`, `templates/_games_collection_search_filters.php`).
- **Mes jeux — barre recherche** : champ texte et listes déroulantes sur **une ligne** (PC) — `collection-search__toolbar` + CSS `collection-search--filters`.
- **Documentation** : [doc/partage-visiteur.md](doc/partage-visiteur.md) (liens, filtres, colonnes, fichiers, tests).
- **Tests** : `ShareFeaturesTest::testGameShareLinkAppliesListFilters` ; `GameRepositoryTest::testListFilterByPhysicalAndDigitalSupport`.

### Modifié

- **Mes jeux** : retrait du menu **type de plateforme** (`platform_kind`) de l’interface (le paramètre reste actif via liens statistiques en champ caché).
- **Tri partagé** : correction `ORDER BY` pour **Fini le** / **dernière vue** (évite le double `DESC` SQL invalide).

### Corrigé

- **Filtre magasin démat.** : recherche Steam, Epic, GOG, etc. via `json_each` / `json_extract` sur `digital_stores` au lieu d’un `LIKE` fragile (`GameDigitalStore::sqlStoredJsonContains()` + repli console implicite).

## [0.6.9] — 2026-07-01

**Jeux : fin de partie, filtres recherche, icône disquette, correctif jaquette**

### Ajouté

- **Jeu terminé** : table `game_completion` (migration **054**) ; formulaire avec date sur `/jeu.php` ; handler `/marquer-jeu-fini.php` ; plusieurs fins possibles ; colonne **Fini le** (remplace « Ajouté le ») dans Mes jeux ; bloc **5 derniers jeux finis** sur l’accueil ; statistiques (jeux terminés, % collection, reprises).
- **Recherche Mes jeux** : filtres **type de plateforme** (PC, consoles…), **plateforme** et **magasin démat** (Steam, Epic, GOG, PSN, Xbox, eShop) — `GameListFilter` + `templates/_games_collection_search_filters.php`.
- **Icône disquette/cartouche** : `www/assets/img/game-editions/disquette.png` (+ SVG de secours) ; affichage listes et fiche jeu (`GameEditionIcons`, `_game_editions_display.php`).
- **Classes** : `GameCompletionRepository` ; filtres magasin dans `GameDigitalStore::filterChoices()`.
- **Tests** : fin de partie et filtres magasin/plateforme (`GameRepositoryTest`) ; icône disquette (`GameEditionIconsTest`).

### Corrigé

- **Modification jeu** : le champ jaquette catalogue accepte les chemins `/posters/…` (`type="text"` au lieu de `type="url"` qui bloquait la sauvegarde côté navigateur).
- **`GameListFilter.php`** : erreur de syntaxe PHP sur `normalizeSupport` (régression 0.6.9).

## [0.6.8] — 2026-06-30

**Jeux : propositions au catalogue, plateforme SNES, correctifs validation admin**

### Ajouté

- **Propositions jeux au catalogue** : `/proposer-jeu.php` pour les utilisateurs (comme `/proposer-oeuvre.php` pour les films) ; validation admin sur `/soumissions-catalogue.php` avec enrichissement IGDB optionnel.
- **`GameManualEdit`**, **`CatalogSubmissionPayload`** (domaine film/jeu), formulaire `_game_catalog_submission_form.php`.
- **Plateforme SNES** : migration **053** (`snes` — Super Nintendo).
- **Tests** : `CatalogSubmissionTest::testUserCanSubmitGameAndAdminApproves`.

### Corrigé

- **Examen admin proposition jeu** : titre et champs préremplis ; formulaire de révision dédié.
- **Validation proposition jeu** : insertion catalogue sans erreur sur `oeuvres.saga` / `saga_ordre` (`CatalogSchema::completeOeuvrePayload`).
- **Notifications** : lien « proposition acceptée » vers `/ajouter-jeu.php` pour les jeux.
- **Plateformes** : cases cochées conservées si la clé n’est pas encore dans `game_platform` (ex. avant migration).
- **Support physique console** : libellé **Disquette/cartouche** (jeux SNES, Mega Drive, etc.) ; alias `cartouche` en import/saisie.

## [0.6.7] — 2026-06-29

**Partage visiteur : pages publiques, jaquettes et liens jeux**

### Corrigé

- **Liens de partage jeux** : `/partage-jeux.php` et `/partage-jeu.php` accessibles sans connexion (auparavant redirection vers la page de login).
- **Jaquettes visiteur** : `poster.php` utilise un bootstrap allégé (sans session ni contrôle de login) ; chemins `/posters/` et `/poster.php` publics.
- **URLs d’affiches** : `AppUrl::webPath()` et préfixe optionnel `MONCINE_WEB_BASE_PATH` pour les installations dans un sous-dossier.
- **Auth** : normalisation du chemin de requête avec le préfixe d’installation (pages publiques reconnues correctement).
- **Fiche jeu partagée** : affichage des liens **jeu de base**, **extensions** et **remakes** (bandeau jaquettes, comme sur `/jeu.php`).

### Ajouté

- **`lib/bootstrap-poster.php`** — chargement minimal pour la livraison des affiches.
- **`www/router.php`** — routeur `php -S` pour `/posters/*.jpg` en développement local.
- **Tests** : `AuthPublicPathsTest`, `AppUrlWebPathTest`, `ShareFeaturesTest::testGameShareLinkShowsExtensionRelations`.

## [0.6.5] — 2026-06-16

**Jeux : prêts, multi-plateformes, formulaires bibliothèque — Foyers personnels**

### Ajouté

- **Prêts jeux** : bouton « Demander un prêt » sur la collection jeux d’un ami (profil public), même flux que les films (demande → réservation → prêt → retour).
- **Règles prêts** : jeux avec support physique uniquement ; refus automatique pour exemplaires démat seuls ; magazines non prêtables ; case « Ne pas prêter cet exemplaire » (`bibliotheque.non_pretable`, migration **049**).
- **Admin plateformes** : page `/plateformes-jeux.php` (libellés, ordre, activation).
- **Multi-plateformes catalogue** : colonne `oeuvre_jeu.platforms` — un titre peut exister sur PC et console (migration **051**).
- **Mes plateformes** : colonne `bibliotheque.owned_platforms` — l’utilisateur coche les plateformes qu’il possède pour son exemplaire.
- **Migrations 050–051** : table `game_platform`, colonnes `platforms` et `owned_platforms`.
- **Sagas films catalogue** : colonnes `oeuvres.saga` et `oeuvres.saga_ordre` (migration **052**) — même principe que `oeuvre_jeu.franchise` pour les jeux ; recopie automatique à l’ajout depuis le catalogue.
- **Foyer personnel** : création automatique de « Mon foyer » pour tout compte sans foyer (`FoyerRepository::ensurePersonalFoyerForUser`) — inscription, connexion ou premier ajout en collection.
- **Classes** : `LoanEligibility`, `LoanCatalog`, `GamePlatformRegistry`, `GamePlatformAdmin`, `GamePlatformList`.
- **Tests** : `LoanEligibilityTest`, `GameLoanTest`, `GamePlatformListTest`, `FoyerTest::testEnsurePersonalFoyerForSoloUser`, `FilmSagaCatalogTest`.

### Modifié

- **Formulaires ajout film / jeu** (utilisateur non admin) : après choix dans le catalogue, seuls les champs **exemplaire** (plateformes possédées, supports, saga…) ; les métadonnées catalogue restent masquées.
- **Formulaires jeu** : cases à cocher plateformes ; panneaux Linux et démat selon les plateformes cochées ; autocomplétion renvoie `platform_list`.
- Filtres, statistiques et profil public : prise en charge des plateformes possédées.
- Page **Mes prêts** et **notifications** : libellés neutres films + jeux.

### Corrigé

- Utilisateur sans foyer : plus d’erreur fatale à l’ajout film/jeu — foyer personnel créé à la volée.
- **Sagas films** : un utilisateur qui ajoute un film depuis le catalogue hérite désormais de la saga définie au niveau catalogue (auparavant la saga n’existait que sur l’exemplaire de l’admin).

## [0.6.4] — 2026-06-16

**Correction liens croisés jeux ↔ magazines**

### Corrigé

- **Navigation inter-onglets** : depuis l’onglet Jeux (ou Magazines), un clic vers un numéro / sujet magazine ou une fiche jeu bascule l’onglet actif et ouvre la **bonne fiche** (`MediaDomainGuards`, `View::*NavUrl`).

## [0.6.3] — 2026-06-16

**Pont magazine ↔ jeux — complété**

### Ajouté

- **Maintenance admin** `/maintenance-magazine-jeux-liens.php` : rattachement rétroactif des sujets test / preview / interview au catalogue jeux ; suggestions automatiques ; retrait de lien ; audit admin (`MagazineGameLinkMaintenance`).
- **Recherche globale magazines** : les sujets **reliés** remontent aussi via le titre catalogue jeu (et acronymes IGDB).
- **Fiche catalogue jeu** : section « Sujets magazine reliés » (`listCatalogSubjectCoverageForGame`).
- **Documentation** : `doc/pont-magazine-jeu.md` (homonymes, bonnes pratiques).

### Modifié

- Fusion de sujets magazines : conservation du `catalog_oeuvre_id` du sujet fusionné si le sujet conservé n’en avait pas.
- **Sagas jeux** : grille vignettes alignée sur Mes jeux (mise en page large `/sagas-jeux.php`).

## [0.6.2] — 2026-06-16

**Jeux : sagas en vignettes et extensions triées chronologiquement**

### Ajouté

- **Sagas jeux** : bascule **Liste / Vignettes** sur `/sagas-jeux.php` (liste de toutes les sagas et jeux d’une saga) ; paramètre URL `view=grid`.

### Modifié

- **Fiches jeux** : extensions (DLC) affichées par **ordre chronologique** (année, puis titre) — bibliothèque et catalogue.

## [0.6.1] — 2026-06-16

**Magazines : clôture M5 — autocomplétion numéro, profil public et parité catalogue**  
**Jeux : partage visiteur et listes imprimables (parité films)**

### Ajouté

- **Autocomplétion catalogue** à l’ajout d’un numéro (`/ajouter-numero-magazine.php`, `/rechercher-numeros-catalogue.php`) — rattachement via `addFromCatalogOeuvre` sans doublon.
- **Export JSON** du catalogue magazines (`/export-catalogue-magazines.php`, `MagazineCatalogExporter`).
- **Profil public** : bandeau « 5 derniers numéros » (collection et envies) au lieu des seules séries.
- **Partage visiteur jeux** : liens lecture seule collection / envies (`/partage-jeux.php`, `/partage-jeu.php`) ; choix Films ou Jeux dans `/gerer-partages.php` ; migration `048_share_links_media_domain.sql`.
- **Listes imprimables jeux** : `/imprimer-jeux.php`, `/imprimer-envies-jeux.php` (`GamePrintListService`, boutons sur Mes jeux / Mes envies jeux).
- **Tests** : recherche numéros catalogue, export, profil public mis à jour ; partage jeux (`ShareLinkGameRepository`).

### Modifié

- **Catalogue admin** : recherche par n° de magazine et titre de série ; colonne série/n° dans la liste.
- **`createIssueWithLibrary`** : enregistre la série dans `series_bibliotheque` ; bloque la création si le numéro existe déjà au catalogue.
- **Mes jeux / Mes envies jeux** : boutons Partager et Version imprimable alignés sur la page Mes films (`collection-page__head`).

## [0.6.0] — 2026-06-16

**Magazines : import catalogue ABM, parité collection et dates de parution**

Alignement de l’onglet Magazines sur films/jeux : import catalogue partagé depuis [Abandonware Magazines](https://www.abandonware-magazines.org/), ajout de séries existantes, retrait d’une série de la bibliothèque, et normalisation des dates de parution françaises.

### Ajouté

- **Import catalogue ABM** (outil ponctuel CLI + admin) :
  - `lib/cli/abm-fetch-catalog.php` — télécharge l’API ABM (`choixapi=12` / `10`) vers un JSON local ;
  - `lib/cli/abm-import-catalog.php` — importe le JSON en catalogue partagé (sans bibliothèque) ;
  - `lib/AbmApiParser.php`, `lib/AbmCatalogFetcher.php`, `lib/MagazineCatalogImporter.php` ;
  - page admin **`/import-catalogue-magazines.php`** (upload JSON, simulation, filtre revue).
- **Ajouter une série depuis le catalogue** : autocomplétion sur `/ajouter-serie-magazine.php` (`/rechercher-series-catalogue.php`) ; rattache tous les numéros catalogue en **non possédés** (`attachCatalogIssuesToCollection`).
- **Retirer une série** de la collection ou des envies (`/traiter-serie-magazine.php`, bouton sur fiche et modification série) — le catalogue partagé n’est pas supprimé.
- **Dates de parution** : `PublicationType::parseParutionDateLabel()` — `Janvier 2002` → `2002-01-01`, `Juillet / août 2020` → `2020-07-01` ; appliqué à l’import et à l’affichage des libellés bruts.
- **Couvertures par lots** : téléchargement limité (défaut **20** par passage, max 40), pause 300 ms entre requêtes, reprise sur numéros déjà importés.
- **Documentation** : [doc/import-abm.md](doc/import-abm.md) ; section catalogue dans [doc/magazines.md](doc/magazines.md).
- **Tests** : `AbmApiParserTest`, `MagazineCatalogImporterTest`, `MagazineCatalogImporterTest` (unit), `MagazineTest` (retrait série), `PublicationTypeTest` (dates).

### Modifié

- **Menu admin** : liens « Import magazines » (Gestion, catalogue, maintenance, import).
- **`MagazineRepository`** : `createCatalogIssue`, `searchCatalogSeries`, `removeSeriesFromLibrary`, `isSeriesInLibrary`.
- **`PosterStorage::cacheRemoteForSeries`** — logos de séries magazines en local.
- Libellés UI : « Ajouter une série » (au lieu de créer uniquement).

### Notes

- Les scripts ABM sont **ponctuels** (préparation d’import) ; le JSON et le cache API sont ignorés par Git.
- Pour ~300 numéros avec couvertures : importer les métadonnées d’abord, puis relancer l’import avec « Télécharger les couvertures » par lots de 20.

## [0.5.7] — 2026-06-16

**Vue bibliothèque (films et jeux), enrichissement IGDB et recherche par acronymes**

Affichage « étagère » des collections (tranches verticales avec aperçu au survol), option pour conserver la jaquette à l’enrichissement IGDB, et recherche jeux par acronymes.

### Ajouté

- **Vue Bibliothèque** (`?view=shelf`) sur **Mes films** et **Mes jeux** : tranches verticales (190 px), bord gauche de l’affiche, vignette au survol, cases à cocher pour actions de masse.
- **Templates** : `_films_collection_shelf.php`, `_films_shelf_hover_tile.php`, `_games_collection_shelf.php`, `_games_shelf_hover_tile.php`, `_partage_collection_shelf.php`.
- **Styles / JS** : blocs `.game-shelf-*` dans `style.css` ; `initGameShelfHoverPreviews()` dans `app.js`.
- **Enrichissement IGDB** : case **« Garder la jaquette »** (panneaux enrichissement, import par lot) — ne remplace pas une jaquette déjà présente si cochée.
- **Recherche jeux par acronymes** : champ `alternative_names` pris en compte dans Mes jeux, catalogue et `GameTitle::searchText`.
- **Tests** : `CollectionViewModeTest`, `GameCatalogEnrichmentTest`, recherche acronyme (`GameRepositoryTest`, `GameTitleTest`).

### Modifié

- **`CollectionViewMode`** : modes Liste / Vignettes / Bibliothèque pour films et jeux (`SHELF = shelf`).
- **Vue bibliothèque** : **toute la collection sur une seule page** (pas de pagination), comme Mes jeux — films inclus (`FilmCollectionPagination::usesPagination`).
- **Partage visiteur** : mode Bibliothèque disponible sur `/partage.php`.
- **`View`** : `collectionShelfSpineHeightPx()` (190 px), `collectionSpineHueStyle()` (teinte de repli sans affiche).

## [0.5.6] — 2026-06-16

**Sagas jeux, documentation base de données et correctifs statistiques**

Regroupement des jeux par saga (donnée IGDB `franchise`), page dédiée, actions de masse et autocomplétion du champ saga.

### Ajouté

- **Sagas jeux** : page `/sagas-jeux.php` (liste avec jaquette du premier jeu, détail ordonné par année, renommage).
- **`GameFranchiseRepository`** : liste, détail, assignation en masse, renommage, suggestions autocomplétion (`listKnownSagas`).
- **Actions de masse** sur `/jeux.php` : « Ajouter à une saga » (cases à cocher, barre d’outils).
- **Liens saga** : franchise cliquable sur fiches et listes (`_game_franchise_link.php`).
- **Autocomplétion saga** : datalist catalogue sur formulaires, action de masse et renommage (`_game_saga_datalist.php`).
- **Documentation** : [doc/base-de-donnees.md](doc/base-de-donnees.md) (structure SQLite, maintenance).
- **Tests** : `GameFranchiseRepositoryTest`, filtre multi-genres, `GameGenreTest::testListContainsTagIgnoresPositionAndSpacing`.

### Modifié

- **UI jeux** : libellé « Saga » (au lieu de « Franchise ») ; colonne saga dans la liste « Mes jeux ».
- **Styles** : liste sagas jeux avec vignettes (`.sagas-list--games`).

### Corrigé

- **Filtre genre statistiques → Mes jeux** : les jeux à **plusieurs genres** apparaissent pour **chaque** genre concerné (normalisation `, ` dans `GameListFilter` / `GameGenre::sqlTaggedCsvLower`).

## [0.5.5] — 2026-06-16

**Enrichissement jeux via IGDB (comme TMDB pour les films)**

Complétion automatique des fiches jeux depuis [IGDB](https://www.igdb.com/) (via compte Twitch Developer) : jaquette locale, titres FR/EN, studio, éditeur, genres, franchise, modes, thèmes et acronymes.

### Ajouté

- **Migrations 046–047** : `igdb_id`, `igdb_enriched_at`, `franchise`, `game_mode`, `theme`, `alternative_names` sur `oeuvre_jeu`.
- **Enrichissement IGDB** : `IgdbClient`, `IgdbConfig`, `GameEnricher`, `GameCatalogEnrichment` — lot sur `/import.php`, fiche catalogue `/oeuvre-jeu.php`, fiche bibliothèque `/jeu.php` (admin).
- **Titres bilingues** : titre français (`oeuvres.titre`) + titre anglais IGDB (`oeuvres.titre_original`) ; affichage FR prioritaire, EN en secours.
- **Traductions FR** : `IgdbGenreMap`, `IgdbGameModeMap`, `IgdbThemeMap` (tags modes et thèmes comme les genres).
- **Acronymes seuls** : `IgdbAlternativeNameFilter` (ex. GTA, FF — pas les titres complets).
- **UI** : panneaux enrichissement (`_enrich_game_panel.php`), métadonnées IGDB sur fiches (`_game_igdb_metadata_display.php`), handlers `enrichir-jeux.php`, `enrichir-jeu.php`, `enrichir-oeuvre-jeu.php`.
- **Refactor jeux** : `GameSchema`, `GameRelations`, `GameRowMapper`, `GameLinkedGamesQuery`, `GameRelatedSections`, `GameTitle` ; allègement de `GameRepository`.
- **Tests** : `IgdbGenreMapTest`, `IgdbMetadataMapsTest`, `GameTitleTest`, `GameRelationsTest`, `GameRelatedSectionsTest`.

### Modifié

- **Affichage titres jeux** : listes et fiches utilisent `display_titre` (FR si présent, sinon EN).
- **Formulaires admin** : champs franchise, modes, thèmes, acronymes éditables après enrichissement.

## [0.5.4] — 2026-06-16

**Remakes jeux, liens visuels extensions/remakes et recherche tolérante**

Enrichissement du module jeux (remakes, affichage discret des jeux liés) et amélioration globale des champs de recherche / autocomplétion (accents, casse, une faute de frappe par mot).

### Ajouté

- **Migration 045** : remakes jeux dans `oeuvre_jeu` (`is_remake`, `original_game_oeuvre_id`).
- **Remakes (UI)** : case « Remake » + autocomplétion du jeu d’origine (formulaire ajout / modification / admin catalogue).
- **Liens croisés remake** : fiche remake → jeu d’origine (année) ; fiche origine → liste des remakes.
- **Affichage discret** : bandeau jaquettes + année pour extensions et remakes (`_game_related_posters.php`) — deux colonnes si les deux types sont présents.
- **Classe** `SearchMatch` : recherche insensible accents / casse + distance d’édition (1 faute par mot) ; fonction SQL `fold_search()`.
- **Tests** : `SearchMatchTest`, tests remakes et recherche catalogue jeux.

### Modifié

- **Export / import catalogue** : colonnes `jeu_is_remake`, `jeu_original_game_oeuvre_id`.
- **Autocomplétion jeux, films, sujets magazines** : préfiltre SQL plié + classement pertinence (`SearchMatch::filterRankLimit`).
- **Recherche collection** jeux et films, catalogue admin : `fold_search()` à la place de `LOWER()`.
- **FTS magazines** : tokens normalisés sans accents.

### Corrigé

- **Ajout jeu** : bloc Extension / Remake visible dès l’ouverture du formulaire (masqué seulement si un jeu catalogue est choisi dans l’autocomplétion titre).
- **Jaquettes liées** : URL d’affiche des remakes / extensions (double échappement HTML).

## [0.5.3] — 2026-06-16

**Catalogue jeux/magazines, profil public jeux et ajout par autocomplétion**

Fiches catalogue adaptées aux **jeux** et **magazines**, statistiques jeux enrichies, profil utilisateur cohérent pour l’onglet Jeux, et rattachement d’un jeu existant au catalogue lors de l’ajout à la collection.

### Ajouté

- **Fiches catalogue dédiées** : `/oeuvre-jeu.php` et `/oeuvre-magazine.php` (plateforme, studio, série, numéro… — sans TMDB ni champs film).
- **Édition admin** depuis ces fiches ; **ajout à la bibliothèque** (collection / envies) pour jeux et magazines via `/ajouter-oeuvre-bibliotheque.php`.
- **Autocomplétion à l’ajout d’un jeu** (`/ajouter-jeu.php`) : recherche dans le catalogue partagé, comme pour les films.
- **Catalogue admin** : catégorie « Jeu vidéo » à l’ajout ; formulaire et enregistrement catalogue seul.
- **Statistiques jeux** : carte extensions, comptage hors extensions, filtres **cliquables** vers `/jeux.php` (plateforme, genre, décennie, support).
- **Profil public** : onglet Jeux avec stats, grilles et libellés adaptés (plus le contenu films).
- **Classe** `GameListFilter` ; template `_user_public_games_grid.php`.

### Modifié

- **Liens catalogue** : jeux et magazines pointent toujours vers leur fiche catalogue (`View::catalogOeuvreDetailUrl`) ; `/oeuvre.php` redirige selon le domaine.
- **Upload affiche** : retour vers la bonne fiche catalogue après envoi.
- **Navigation** précédent/suivant du catalogue : URLs par domaine média.

### Corrigé

- **Layout** : `$mediaDomain` calculé avant l’URL du profil public (évite une erreur PHP).

## [0.5.2] — 2026-06-16

**Catalogue admin multi-médias + extensions de jeux**

Correctifs et améliorations autour du **catalogue partagé** : affichage et liens adaptés aux domaines (films / jeux / magazines), export/import conservant `media_domain` et les champs spécifiques, et ajout d’une notion d’**extension** (DLC / add-on) pour les jeux.

### Ajouté

- **Migration 044** : extensions jeux dans `oeuvre_jeu` (`is_extension`, `base_game_oeuvre_id`).
- **Extensions jeux (UI)** : dans le formulaire jeu, une case « Extension » + champ « Jeu de base » avec **auto-complétion** sur le catalogue.
- **Liens croisés** : fiche extension → lien vers le jeu de base ; fiche jeu de base → liste des extensions.
- **Catalogue export/import enrichi** : colonne **`media_domain`** + colonnes spécifiques jeux/magazines (incluant extension et jeu de base).

### Corrigé

- **Catalogue (admin)** : jeux et magazines n’apparaissent plus comme des films ; le lien ne renvoie plus systématiquement vers la fiche film.
- **Import catalogue** : préservation du domaine média (`film` / `jeu` / `magazine`) et reconstruction des tables spécialisées (ex. `oeuvre_jeu`, `oeuvre_magazine`).

## [0.5.1] — 2026-05-31

**Jeux vidéo — enrichissements M4 (fichiers, affichage, Linux tri-état)**

Compléments à la **0.5.0** : vue vignettes, fichiers attachés, icônes support image, et distinction « testé » / « non supporté » sous Linux.

### Ajouté

- **Migration 042** : table `game_attachment` — fichiers joints sur la fiche jeu (abandonware, patch, archive…) ; limite **350 Mo** (comme les PDF magazines).
- **Migration 043** : colonne `bibliotheque.linux_not_supported` — alternative à « testé sur Linux » (mutuellement exclusif).
- **Vue vignettes** sur `/jeux.php` : bascule Liste / Vignettes (`?view=grid`), comme la collection films.
- **Icônes support** : images dans `www/assets/img/game-editions/` (`cd_dvd`, `steam`, `gog`, `epic`) avec repli SVG si fichier absent — classe `GameEditionIcons`.
- **Endpoints fichiers jeu** : `/enregistrer-fichier-jeu.php`, `/supprimer-fichier-jeu.php` ; téléchargement via `media-object.php`.
- **Classe** `GameAttachmentRepository` ; sous-dossier stockage `MediaStorage::SUBDIR_GAMES`.
- **Tests** : `GameEditionIconsTest`.

### Modifié

- **Badge Linux** : deux cases au formulaire (« Testé sur Linux » / « Linux non supporté ») ; pingouin barré (barre rouge) sur fiche et listes.
- **Liste Mes jeux** : colonne support avec icônes image ; templates `_games_collection_grid.php` / `_games_collection_list.php`.
- **Fiche jeu** : panneau fichiers attachés (`_game_attachments_panel.php`).

---

## [0.5.0] — 2026-06-10

**Jeux vidéo — phase M4 (MVP livré)**

Première version utilisable de l’onglet **Jeux** : collection, envies, fiches catalogue, pont avec les magazines et parité fonctionnelle avec les films (notes, suppression, promotion envie → collection).

### Ajouté

- **Migrations 039–041** : table `oeuvre_jeu`, colonnes exemplaires (`physical_supports`, `digital_stores`), `magazine_subject.catalog_oeuvre_id`, `bibliotheque.tested_on_linux`.
- **Onglet Jeux actif** : `/jeux.php`, `/jeux-envies.php`, `/jeu.php`, `/ajouter-jeu.php`, `/modifier-jeu.php`.
- **Accueil jeux** : page dédiée (`home-jeu.php`) — derniers jeux notés, ajouts collection/envies, raccourcis.
- **API catalogue** : `/rechercher-jeux-catalogue.php` (autocomplétion JSON).
- **Classes** : `GameRepository`, `GamePlatform`, `GameGenre`, `GamePhysicalSupport`, `GameDigitalStore`, `GameCollectionStats`, `MagazineGameLink`.
- **Fiche jeu** : jaquette, genres (badges), exemplaires physiques/démat, section « Dans vos magazines ».
- **Parité films** : notes sur 10 (+ moyenne foyer), date d’ajout, suppression (icône poubelle), passage envie → collection (« J’ai acheté »).
- **Linux (jeux PC)** : case « Testé sur Linux » à l’ajout/modification ; badge pingouin Tux (fond bleu ciel) sur fiche et listes.
- **Pont magazine ↔ jeu (UI)** : autocomplétion catalogue jeux à l’ajout d’un sujet test/preview/interview ; lien `catalog_oeuvre_id` ; affichage sur fiche sujet et numéro.
- **Liste triable** : titre, année, studio, genre, support, note, date d’ajout.
- **Statistiques jeux** : page dédiée (`GameCollectionStats`) — plateformes, démat/physique, genres, décennies, sujets magazine liés.
- **Documentation** : [doc/jeux.md](doc/jeux.md).

### Modifié

- **ROADMAP** : M4 MVP livré en **0.5.0** ; M2/M3 repoussés ; pont magazine ↔ jeu partiellement en place (UI saisie + affichage).
- **Accueil** (`index.php`) : branche selon l’onglet actif (films / magazines / jeux).
- **Profil public** : bandeau d’affiches compatible jeux (`UserPublicProfileService`, `_user_profile_poster_strip.php`).

### Technique

- Notes jeux via table `historique` réutilisée (`HistoriqueRepository::setPersonalNote`) — note sans session datée visible.
- Garde-fous domaine média : `MediaDomainGuards::ensureGameContext()`.

---

## [0.4.4] — 2026-06-09

**Films — clôture phase M1 (stabilisation Monciné)**

### Ajouté

- **Pagination Mes films** : **56** vignettes par page (7 lignes × 8 colonnes) ou **100** films en mode liste ; navigation Première / Préc. / Suiv. / Dernière + saut de page (`FilmCollectionPagination`).

### Modifié

- **Grille Mes films** : tuiles de hauteur uniforme (titre sur 2 lignes, zone notes réservée, carte flex).
- **ROADMAP** : phase **M1 clôturée** — QA production complète (blocs A–R, 2026-06-09).

### Corrigé

- Tuiles inhomogènes en vue vignettes (notes VOUS/FOYER, titres courts vs longs) — M1-001.
- Chargement de toute la collection d’un coup sur grosses dvdthèques — M1-002.

---

## [0.4.3] — 2026-05-31

**Magazines — année du sujet choisie à la saisie**

### Modifié

- **Année sur les sujets** : menu déroulant à l’ajout d’un sujet sur la fiche numéro (par défaut l’année du numéro, modifiable) — remplace l’affectation automatique systématique à la date de parution.
- Ajout d’un sujet possible même si le numéro n’a pas encore de date de parution (année courante proposée par défaut).

---

## [0.4.2] — 2026-05-31

**Magazines — Interview, filtre hors-série, maintenance sujets admin**

### Ajouté

- Catégorie **Interview** sur les sujets magazines (alias « entretien » / « entretiens »).
- **Filtre Hors-série** sur la liste des numéros d’une série (`possession=hors_serie`) ; export PDF série avec libellé adapté.
- **Maintenance admin — sujets magazines** (`/maintenance-magazine-sujets.php`) : suppression des sujets **orphelins** (créés par erreur, sans numéro lié), purge groupée, fusion de **doublons probables** ; journal d’audit (`MagazineSubjectMaintenance`, `CatalogAuditLog`).

### Modifié

- **ROADMAP** : section **Pont Magazines ↔ Jeux vidéo** (lien optionnel sujet → fiche jeu, compatibilité des données déjà saisies en production).

### Corrigé

- Mise à jour partielle d’un numéro (couverture, tag papier, etc.) ne remettait plus le flag **hors-série** à zéro.

---

## [0.4.1] — 2026-05-31

**Magazines — recherche FTS (texte intégral)**

### Ajouté

- Index **FTS5** SQLite pour la recherche rapide dans les **numéros** (`magazine_issue_fts`) : n°, sommaire, extrait PDF, date.
- Index **FTS5** pour le **catalogue de sujets** (`magazine_subject_fts`).
- Triggers SQL de synchronisation automatique ; repli **LIKE** si FTS indisponible.
- Classes `MagazineFtsQuery`, `MagazineIssueFts`, `MagazineSubjectFts` ; migration `038_magazine_fts.sql`.
- **Recherche globale** sur **Mes magazines** : titres de séries, sujets (tests, previews, dossiers), sommaires et extraits PDF.
- **Autocomplétion** à la saisie d’un sujet sur la fiche numéro (réutilise le catalogue existant).
- Regroupement des libellés proches à l’ajout (ex. « After Life » / « Afterlife »).

### Modifié

- Champ de recherche **Mes magazines** : placeholder et résultats par sujets / numéros / séries.
- Recherche par sujet et par série : syntaxe FTS5 corrigée (`magazine_issue_fts MATCH`, nom complet de table).

### Corrigé

- Erreur SQL **« no such column: f »** lors d’une recherche sur **Mes magazines** : SQLite n’accepte pas l’alias de table avec l’opérateur `MATCH` FTS5.

---

## [0.4.0] — 2026-05-31

**Magazines — sujets, tags de série et recherche globale**

### Ajouté

- **Catalogue de sujets** (`magazine_subject`, `oeuvre_magazine_subject`) : catégories **Test**, Preview, Comparatif, Dossier.
- **Sujets sur la fiche numéro** (`templates/_magazine_issue_subjects.php`) : associer un test, une preview ou un dossier à un numéro.
- **Tags de série** (`series.tags`) : saisie par **badges** sur la fiche série (Ajouter / ×) ; un tag → appliqué à tous les sujets ; plusieurs tags → menu déroulant sur chaque numéro.
- **Année automatique** sur les tags : tirée de la **date de parution** du numéro (`parution_year`), ex. « Gran Turismo 7 (PC · 2024) ».
- **Recherche globale** `/magazines-recherche.php` et **fiche sujet** `/magazine-sujet.php` (statistiques + liste des parutions).
- API JSON `/rechercher-sujets-magazine.php` (autocomplétion).
- Classes `MagazineSubject`, `MagazineSubjectRepository`, `MagazineSeriesTag` ; tests unitaires et d’intégration.
- Migrations `034` à `037` (sujets, année, tags série, fusion catégorie Test).

### Modifié

- Catégories **Test jeu / voiture / matériel** fusionnées en une seule catégorie **Test** ; la recherche inclut les anciennes valeurs en base.
- Menu **Mes magazines** : lien « Recherche par sujet ».

---

## [0.3.2] — 2026-05-31

**Navigation onglets ; profil public multi-médias**

### Ajouté

- **Profil public multi-onglets** (`/utilisateur.php?domain=…`) : Films, Magazines et message « bientôt » pour BD / Livres / Jeux ; thème couleur selon l’onglet consulté.
- **Profil Magazines** : statistiques (séries, numéros possédés, envies), vignettes et listes collection / envies.
- **Numéros sur le profil ami** : `/utilisateur-serie-magazine.php` (grille paginée, recherche) et `/utilisateur-numero-magazine.php` (couverture, sommaire, tags support) — **lecture seule**, PDF non partagés.
- Tests `UserPublicProfileMagazineTest`, `MediaDomainTest::testTabSwitchBetweenFilmAndMagazineCollections`.

### Corrigé

- **Changement d’onglet Magazines → Films** : plus de boucle de redirection (la cible restait une URL magazine) — `MediaDomainGuards::redirectTargetForTabSwitch()`.

### Modifié

- `UserPublicProfileService` : stats et listes filtrées par `media_domain` ; méthodes magazines pour le profil public.
- `View::userProfileUrl()` / `userProfileListUrl()` : paramètre `domain` ; URLs `userProfileMagazineSeriesUrl()` / `userProfileMagazineIssueUrl()`.

---

## [0.3.1] — 2026-06-02

**Magazines — export liste ; catalogue — propositions multiples**

### Ajouté

- **Export PDF** d’une série magazine (`/imprimer-serie-magazine.php`) : bouton **Exporter en PDF** sur la page série ; tableau sans couvertures avec colonne **Possession** (Non possédé, Papier, PDF, Papier + PDF) ; mêmes filtres/tri que la liste ; enregistrement via le navigateur (`MagazinePrintListService`, `MagazineSupport::possessionStatusLabel()`).

### Corrigé

- **Propositions au catalogue** (films) : un utilisateur peut envoyer **plusieurs** suggestions en parallèle (plus de blocage tant qu’une proposition est en attente) ; le formulaire **Proposer une œuvre** reste toujours accessible.

---

## [0.3.0] — 2026-06-02

**Magazines — liste, statistiques, documentation**

### Ajouté

- **Liste série** : pagination **48 numéros par page** (grille **8 × 6**), tuiles plus grandes ; couvertures **noir et blanc** pour les numéros non possédés en collection (couleur conservée sur **Mes envies**).
- **Statistiques magazines** : nombre de **PDF possédés** et **espace disque** (Go/Mo) via `collectionPdfStats()`.
- **README** : guide **installation et utilisation** de la webapp (serveur local, premier compte, parcours films/magazines).
- Tests unitaires `MagazineRepositoryFormatTest`.

### Corrigé

- **Sommaire** sur la fiche numéro : suppression du double saut de ligne (`nl2br` + `pre-wrap`).

### Modifié

- `countIssuesForSeries()` : comptage SQL direct (plus de chargement de toute la liste).
- `listIssuesForSeries()` : paramètres `limit` / `offset` pour la pagination.

---

## [0.2.5] — 2026-05-31

**Magazines — envies, suppression, import PDF**

### Ajouté

- **Mode suppression** sur la fiche numéro : icône poubelle dans la barre d’outils → panneau explicite avec confirmation (évite les poubelles sur chaque carte de la liste).
- **`MagazineRepository::resolveIssueBibIdForRedirect()`** — redirection fiable vers la fiche collection ou envies après une action (PDF, papier…).

### Corrigé

- **Retrait auto des envies** lorsqu’un numéro devient possédé (tag papier ou import PDF) — l’entrée wishlist est supprimée sans retirer le numéro de la collection.
- **Import PDF depuis Mes envies** : après possession, redirection vers l’identifiant **collection** (l’ancien id envies n’existait plus → page « introuvable » et avertissement `http_response_code`).
- **`syncSupportTagsForOeuvre`** : met à jour la ligne **collection** (plus une ligne wishlist prise au hasard).
- **`magazine-numero.php`** : code HTTP 404 envoyé **avant** le rendu HTML.
- **`registerSeriesInLibrary`** : ordre des arguments corrigé lors de l’ajout aux envies (régression 0.2.3).

### Modifié

- Suppression retirée des **cartes** de la liste série ; centralisée sur la fiche via le mode suppression.

---

## [0.2.4] — 2026-05-31

### Ajouté

- **Envies** : ajout en **dupliquant** l’entrée wishlist (le numéro **reste** dans la collection) ; badge **« En envies »** si déjà listé.
- Filtre **Tous / Possédés / Non possédés** (`?possession=all|owned|unowned`) sur `/serie-magazine.php`.

### Modifié

- Bouton **Ajouter aux envies** : ne déplace plus le numéro vers la wishlist seule.

---

## [0.2.3] — 2026-05-31

### Ajouté

- Numéros **sans tag** (ni papier ni PDF) : exclus du compteur **numéros possédés** (stats, accueil, cartes série) ; badge **« Non possédé »** ; bouton **Ajouter aux envies** en un clic.

---

## [0.2.2] — 2026-05-31

### Corrigé

- **Magazines** : la case « J’ai le numéro en papier » à l’**ajout** d’un numéro n’était pas enregistrée (`SupportPhysique::normalize` effaçait le tag `papier` à l’insertion bibliothèque).

### Modifié

- **Affiches et couvertures** : taille max. par image portée à **10 Mo** (`MONCINE_POSTER_MAX_BYTES`) ; ZIP d’affiches à l’import admin : **200 Mo** (`MONCINE_POSTERS_ZIP_MAX_BYTES`).
- **`UploadLimits`** : contrôles et alertes PHP pour affiches et ZIP (page Import, fiche catalogue, magazines).
- Textes d’aide mis à jour (import ZIP, formulaires couverture magazine).

---

## [0.2.1] — 2026-05-31

**Magazines — PDF, recherche, supports en tags, confort dev**

### Ajouté

- **Tags support** (`lib/MagazineSupport.php`) : case **Papier** ; tag **PDF** automatique à l’import ; badges sur la liste série et la fiche numéro (`templates/_magazine_support_tags.php`).
- **Recherche globale** sur la page série (`q`) : numéro, date, sommaire, texte des 6 premières pages du PDF.
- **Extraction PDF** (Poppler, post-traitement non bloquant) :
  - `MagazinePdfTextExtractor` — `pdftotext` → `pdf_text_preview` (migration **`033_magazine_pdf_text_preview.sql`**).
  - `MagazinePdfCoverExtractor` — couverture depuis la page 1 si absente.
  - `MagazinePdfInfo` — nombre de pages via `pdfinfo` si champ à 0.
- **Liste numéros** : grille en cartes, couvertures, layout large ; tri et recherche conservés.
- **Dev local** : `start-dev.sh`, `www/serve.sh`, `www/php-dev.ini` (limites upload ~350 Mo) ; sessions PHP dans `data/sessions/` (`lib/config.php`).
- **`lib/UploadLimits.php`** — garde-fou POST et message d’alerte dans les formulaires magazine.
- Documentation : [doc/magazines.md](doc/magazines.md).

### Modifié

- **Chemins PDF** : `data/media/magazines/{revue}/{année}/{revue}-{numero}.pdf` ; remplacement de fichier simplifié.
- **Import PDF** : réponse HTTP renvoyée avant l’indexation lourde (`register_shutdown_function`, `fastcgi_finish_request` si disponible).
- **Formulaires numéro** : plus de champ texte « Support » ; ordre de tri décimal (hors-série `8.5`, insertion `8.1`, etc.).
- **`www/media-object.php`** — livraison PDF magazines alignée sur le stockage.

### Tests

- `MagazineSupportTest`, `MagazinePdfTextExtractorTest`, `MagazinePdfInfoTest`, `MagazinePdfCoverExtractorTest`.
- `MagazineTest` — recherche globale série ; `PublicationTypeTest` — suggestion ordre.

Après mise à jour : `php lib/cli/migrate.php` (migration **033**). Dépendance optionnelle : **`poppler-utils`**. Dev PDF : `./start-dev.sh`.

---

## [0.2.0] — 2026-05-31

**Module Magazines (M5 — première version)** — collection par **série** puis **numéros**.

### Ajouté

- Tables **`series`** et **`oeuvre_magazine`** (migration `031_series_magazine.sql`).
- **`PublicationType`** — hebdomadaire, mensuel, bimensuel, trimestriel, annuel, irrégulier ; formatage des dates de parution.
- **`SeriesRepository`**, **`MagazineRepository`** — séries, numéros, collection, envies.
- Pages : **`/magazines.php`** (liste des séries), **`/serie-magazine.php`**, **`/magazine-numero.php`**, ajout série/numéro.
- **Couverture** via `PosterStorage` (même format que les affiches films).
- **PDF** par numéro (`stored_objects` + dossier `magazines/`).
- **Sommaire** sur la fiche numéro (à la place du synopsis).
- Onglet **Magazines** activé dans la navigation (`MediaDomain::isCollectionImplemented`).

### Tests

- `MagazineTest`, `PublicationTypeTest` ; mise à jour `MediaDomainTest`.

Après mise à jour : `php lib/cli/migrate.php`.

---

## [0.1.1] — 2026-05-31

**Reprise des correctifs Monciné 1.0.3 → 1.0.5** (onglet Films et fonctions communes).

### Ajouté

- **Gestion clé API TMDB (admin)** : remplacer ou supprimer la clé stockée ; indication si la clé vient de `MONCINE_TMDB_API_KEY`.
- **Affiches locales** : stockage dans `{MONCINE_DATA}/posters/` (même dossier que `moncine.db`), servies par `poster.php` (compatible Nginx/YunoHost). Repli de lecture sur l’ancien `www/posters/` pendant la migration.
- Fichiers `lib/PosterDelivery.php`, `www/poster.php`, `tests/Unit/TmdbConfigTest.php`.

### Modifié

- **Inscription** : plus de groupe famille créé automatiquement à la création du compte (le premier admin conserve son groupe via `createFirstAdmin`).

### Corrigé

- **Suppression de compte** : retrait explicite des liens sociaux (`group_members`, amis, partages…) avant suppression ; ordre de détachement puis `foyer_id = NULL` avant purge des foyers orphelins — corrige « Suppression impossible » (contraintes SQLite).
- **Paramètres** : chargement du foyer avant la détection « membre seul dans un groupe ».

### Tests

- `AccountDeleteTest` — membres de groupe et foyer solo après inscription.
- `TmdbConfigTest` — suppression du fichier clé.

Aucune migration SQL. Après mise à jour : déplacer éventuellement `www/posters/*` vers `{MONCINE_DATA}/posters/`.

---

## [0.1.0] — 2026-05-30

**Première version Médiathèque** — socle multi-médias + onglet **Films** (= fonctionnalités Monciné 1.0.0).

### Ajouté

- **Onglets média** (Films, BD / Manga, Livres, Jeux, Magazines) avec **couleur par domaine** et thème global (barre, en-tête, boutons).
- **Domaine actif en session** (`MediaContext`) + changement via `/set-media-domain.php`.
- Colonne **`oeuvres.media_domain`** (migration `030_media_domain.sql`) ; collections filtrées par domaine.
- Classes **`MediaDomain`**, **`MediaDomainGuards`**, filtre SQL central dans **`CatalogSchema`**.
- Page **« Bientôt disponible »** pour les onglets non encore implémentés (BD, livres, jeux, magazines).
- **`.gitignore`** (données locales, `vendor/`, graine d’install volumineuse, sessions).
- Documentation : [ROADMAP.md](ROADMAP.md) détaillée, [doc/mediatheque.md](doc/mediatheque.md).

### Couleurs des onglets (thème)

| Onglet | Couleur d’accent |
|--------|------------------|
| Films | Gris argenté `#adb5bd` |
| BD / Manga | Rose corail `#f06292` |
| Livres | Bleu ciel `#64b5f6` |
| Jeux | Violet `#9575cd` |
| Magazines | Vert d’eau `#4db6ac` |

### Corrigé

- **Changement d’onglet depuis le quiz** (« Ce soir ») : plus de boucle de redirection ; retour vers Mes films.
- **Page Compte** (`parametres.php`) : variable `$foyer` définie avant usage (suppression de l’avertissement PHP).

### Technique

- Schéma SQL : **001 → 030** (dont `media_domain`).
- Namespace PHP **`Moncine\`** et constantes **`MONCINE_*`** conservés volontairement — voir [doc/conventions-techniques.md](doc/conventions-techniques.md).
- Tests : `tests/Unit/MediaDomainTest.php`, `tests/Integration/MediaDomainTest.php`.

### Hors périmètre 0.1.0 (voir roadmap)

- BD, livres, jeux, magazines (collection utilisable).
- Renommage complet des fichiers `film*` → générique (prévu progressivement).

---

## [1.0.0] — 2026-05-30 (Monciné — amont)

**Première version de production** (dvdthèque films, déploiement YunoHost ou serveur classique).

### Livré

- Fonctionnalités des versions **0.7** à **0.9.6** : collection, envies, prêts, partage visiteur, inscription publique, comptes, listes imprimables, stockage médias, etc.

### Documentation & install

- `sql/schema.sql` : table **`email_change_requests`** incluse (install fraîche alignée avec la migration `029`).
- README, ROADMAP et prérequis déploiement alignés sur **1.0.0**.

### Déploiement production (rappel)

1. `php lib/cli/migrate.php` — migrations **001** à **029** appliquées.
2. Variables recommandées (PHP-FPM) : `MONCINE_DATA_PATH`, `MONCINE_BASE_URL`, `MONCINE_MAIL_FROM`, **`MONCINE_TRUST_PROXY=1`** derrière un reverse proxy.
3. Sauvegarde régulière de la base SQLite et du dossier données (`data/.keys/`, affiches, etc.).

Aucune migration SQL supplémentaire par rapport à **0.9.6**.

## [0.9.6] — 2026-05-30

### Sécurité

- **Inscription** : mot de passe des demandes en attente **chiffré** en base (`RegistrationPasswordCipher`, clé dans `data/.keys/`) ; validité du lien réduite à **24 h**.
- **Confirmation inscription** : jeton retiré de l’URL après le premier chargement (session) ; `Referrer-Policy: no-referrer` sur les pages sensibles.
- **IP / throttle** : en-têtes `X-Forwarded-For` / `X-Real-IP` utilisés seulement si `MONCINE_TRUST_PROXY=1` (recommandé sur YunoHost).
- **Changement d’e-mail** : confirmation sur la **nouvelle** adresse + notification de l’**ancienne** ; mot de passe requis (migration `029`).
- **Suppression de compte** : foyers sans autre membre supprimés ; liens de partage révoqués ; demandes de changement d’e-mail effacées.

## [0.9.5] — 2026-05-30

### Ajouté

- **Mon compte** (`/parametres.php`) : section **Supprimer mon compte** (mot de passe requis, confirmation) ; message informatif pour les **administrateurs** (suppression impossible depuis cette page).

### Corrigé

- **Suppression de compte** : nettoyage complet avant suppression (`prepareUserDeletion`) — journal admin, bibliothèque (réattribution des films de collection partagée à un autre membre du groupe), demandes d’inscription, références groupe/foyer ; corrige l’erreur « Suppression impossible » due aux contraintes SQLite.
- **Mot de passe oublié** : erreurs SQL à la création du jeton gérées sans erreur fatale (`PasswordResetRepository`).

### Tests

- `tests/Integration/AccountDeleteTest.php` — suppression utilisateur, refus admin, mot de passe, foyer partagé.

Documentation : [doc/comptes-mot-de-passe.md](doc/comptes-mot-de-passe.md).

## [0.9.4] — 2026-05-30

Correctifs suite à l’inscription publique (v0.9.3).

### Corrigé

- **Inscription (HTTP 500)** : `LockoutThrottleStore` — les closures `static` n’utilisaient plus `$this` (limiteur de tentatives à l’envoi du formulaire).
- **Inscription** : erreurs SQLite à l’insertion (`inscription_requests`) affichées ou traitées comme succès neutre (doublon e-mail) au lieu d’une page blanche.
- **Page inscription** : vérification `RegistrationService::isAvailable()` ; contrôle `isAvailable()` aligné avec la page de confirmation.
- **Mot de passe oublié** : gestion d’erreur à l’insertion des jetons de reset.

## [0.9.3] — 2026-05-28

### Ajouté

- **Inscription publique** : réglage admin (désactivée / ouverte / avec approbation), confirmation par e-mail, une demande active par adresse, page `/demandes-inscription.php` — [doc/inscription-utilisateurs.md](doc/inscription-utilisateurs.md) (migration `027`).

### Sécurité

- **Confirmation d’inscription** : le lien e-mail n’active plus le compte en GET ; l’utilisateur doit cliquer sur un bouton (POST + CSRF) pour éviter les confirmations automatiques par les scanners de messagerie.
- **Connexion / inscription / mot de passe oublié** : limitation de débit **session + IP** (`LockoutThrottleStore`, fichiers sous `data/auth_rate_limit/`) — impossible de contourner en supprimant le cookie de session.
- **Inscription** : suppression du `password_hash` dans `inscription_requests` après approbation ou refus (migration `028`).

## [0.9.2] — 2026-05-28

Renforcement **sécurité et robustesse** (revue qualité).

### Sécurité

- **Chemins médias (admin)** : `MediaPathConfig::validateRootPath()` — chemin absolu, dossier lisible/inscriptible, interdiction de préfixes système (`/etc`, `/proc`, …).
- **Fichiers stockés** : `StoredObjectDelivery` — types MIME sûrs en affichage inline (PDF, images, texte) ; autres types en téléchargement.
- **Content-Disposition** : `HttpContentDisposition` (nom ASCII + UTF-8 RFC 5987) pour `/media-object.php`.

### Amélioré

- **Listes imprimables** : limite de **500 lignes** + message si la liste est tronquée.

## [0.9.1] — 2026-05-28

Alternative légère à la **phase 10** (export PDF serveur reporté pour YunoHost).

### Ajouté

- **Listes imprimables** : `/imprimer-films.php`, `/imprimer-envies.php` — même filtres et tri que Mes films / Mes envies ; bouton **Version imprimable** sur ces pages.
- **Impression navigateur** : « Enregistrer en PDF » via la boîte de dialogue du navigateur (aucune librairie PHP PDF).

### Corrigé

- **Bouton d’impression** : script externe `www/assets/js/print-page.js` (la politique CSP `script-src 'self'` bloquait les `onclick` inline).

### Amélioré (maintenance)

- **Listes imprimables** : logique centralisée dans `PrintListService` ; layout print avec scope isolé (`View::renderPrintLayout`).
- **MediaStorageService** : suppression fichier + métadonnées plus robuste (fichier déjà absent).

Documentation : [doc/listes-imprimables.md](doc/listes-imprimables.md).

## [0.9.0] — 2026-05-28

Phase **9** — stockage de fichiers volumineux hors `www/`. Amélioration de l’usage du **questionnaire du soir**.

### Ajouté

- **Racine médias** : variable `MONCINE_MEDIA_PATH` (défaut `data/media/`) + surcharge admin en base.
- **Sous-dossiers** : `objects/`, `magazines/`, `books/`, `exports/`, `tmp/` créés automatiquement.
- **ObjectStorage** : interface + backend filesystem local ; table `stored_objects` (migration `019`).
- **Admin** : page `/maintenance-medias.php` (config racine, création dossiers, test lecture/écriture).
- **Lecture sécurisée** : `/media-object.php?id=…` (admin, streaming PHP — pas d’URL publique directe).

### Amélioré

- **Questionnaire du soir** (`/resultat.php`) : barre d’actions en haut de chaque proposition (boutons de note **Non**, **Bof**, **Pourquoi pas**, etc. + **Autre tirage** + lien vers les mieux notés) ; suppression du texte récapitulatif des critères en tête de page. Voir [doc/questionnaire-du-soir.md](doc/questionnaire-du-soir.md).

## [0.8.9] — 2026-05-27

Phase **8** (prêts entre amis) + correctifs installation locale.

### Ajouté

- **Prêts** : demandes de prêt entre amis, acceptation (réservation), validation du prêt et retour — page `/mes-prets.php`.
- **Profil public** : sur la liste « Films de … », bouton **Demander un prêt**, affichage des statuts (déjà prêté / réservé / demande envoyée) et annulation de demande.
- **Notifications** : notifications in-app (et e-mail si activé) sur demande/acceptation/refus/validation/retour.

### Corrigé

- **Installation / création du premier compte** : correction des transactions imbriquées lors de la création du groupe famille.
- **Sessions PHP en local** : bascule vers `data/sessions` quand le répertoire système des sessions n’est pas accessible.

## [0.8.8] — 2026-05-19

Phase **7 bis** (suite cibles d’achat sur les envies), hors comparateur de prix.

### Ajouté

- **Partage visiteur (envies)** : sur les liens « Mes envies », affichage en lecture seule des **versions recherchées** (support + EAN) — liste `/partage.php` et fiche `/partage-film.php`.
- **« J’ai acheté »** : choix d’une **version cible** (`wishlist_targets`) pour pré-remplir le support et l’EAN lors du passage en collection — fiche film et liste Mes envies.
- **Migration `025_ean_digits_only.sql`** : normalisation des EAN déjà en base (chiffres seuls).

### Amélioré

- **Mes envies** : choix de la version achetée en **liste déroulante** (lignes du tableau plus compactes qu’avec les boutons radio).

### Corrigé

- **EAN** : stockage, affichage et formulaires en **chiffres seuls** (plus d’espaces pour la lecture ni en base) ; correction automatique à l’ouverture d’une fiche collection.

### Reporté

- **Comparateur de prix** (phase 7 bis.2) : aucune API publique retenue pour l’instant.

### Tests

- `WishlistTargetsTest` (promotion avec cible, EAN sans espaces), `ShareFeaturesTest` (cibles sur lien envies), `OeuvreEanNormalizeTest`.

---

## [0.8.7] — 2026-05-19

### Ajouté

- **Recherche par acteur / réalisateur** (`/personnes.php`) : résultats sur **tout le catalogue partagé**, avec badge **Dans ma collection**, **Dans mes envies** ou **Pas dans ma liste** ; suggestions de noms issues du catalogue entier.

### Amélioré

- **Page d’accueil** : retrait des boutons redondants avec le menu (Voir mes films, Statistiques, Mon profil) — conservent Lancer le questionnaire et Ajouter film.

### Tests

- `PersonSearchTest`.

---

## [0.8.6] — 2026-05-19

### Ajouté

- **En-tête** : bouton **Mon profil** (icône utilisateur) à côté des notifications — ouvre votre profil public.
- **Page d’accueil** : **activité récente** sur **3 lignes** (comme le profil) — bandeaux horizontaux de vignettes : 5 derniers films vus, 5 derniers ajouts à la collection, 5 derniers ajouts aux envies (liens vers les fiches).
- **Profil public** : section « 5 derniers ajouts à la collection » (votre profil et celui des amis / groupe).
- **Liens de partage** : après création d’un lien, partage par **e-mail** (messagerie locale ou envoi serveur) et par **Bluesky** (intent) ; copie de l’URL ; URL mémorisée 24 h en session pour les liens récents.

### Tests

- `ShareLinkShareTest`, `ShareLinkSessionStoreTest`, `UserPublicProfileCollectionTest`.

---

## [0.8.5] — 2026-05-19

### Ajouté

- **Maintenance catalogue** : sauvegarde et restauration de la base SQLite complète (`moncine.db`) — catalogue, bibliothèques, utilisateurs, historique, envies, groupes, etc.
- **Export** : téléchargement d’un fichier `.db` via `/admin-export-base.php` (POST, mot de passe admin, CSRF, limite de fréquence).
- **Restauration** : remplacement de la base avec validation du fichier, confirmation **RESTAURER**, copie de secours automatique dans `data/db_snapshots/`.
- Journal admin : actions **export** et **restauration** de la base.

### Sécurité

- Accès **administrateur** uniquement ; **mot de passe** redemandé à chaque opération ; protection **CSRF** ; quotas session + IP (exports/restaurations et échecs de mot de passe) ; fichiers temporaires hors répertoire web.

### Tests

- `DatabaseBackupServiceTest`, `DatabaseBackupRestoreTest`.

---

## [0.8.4] — 2026-05-19

### Ajouté

- **Statistiques** : carte **temps de vision cumulé** depuis le début (durée de chaque film × nombre de visionnages, re-visions incluses) — affichage **2h 30min** sous un jour, **3j 5h 30min** au-delà.
- **Infobulle** sur le libellé de cette carte (icône **i** au survol) : explication du calcul et du format, sans texte permanent sous la carte.

### Corrigé

- **Correction TMDB par identifiant** : le **titre français** (fr-FR) de la fiche œuvre est mis à jour ; l’**enrichissement par titre** ne modifie pas le titre saisi.

### Tests

- `CollectionStatsDurationTest`, `CollectionStatsViewingDurationTest`.
- `FilmEnricherTmdbTitleTest`.

---

## [0.8.3] — 2026-05-19

### Ajouté

- **Profil public utilisateur** (`/utilisateur.php`) : visible par les **amis** et les **membres du même groupe** — pseudo, statistiques (collection, envies, films vus, films vus cette année), 5 derniers films vus et 5 derniers ajouts aux envies en **vignettes**.
- Listes complètes en lecture seule : **collection**, **envies** et **films vus** (date et note par vision ; filtre par année depuis les statistiques).
- Page **Mes amis** : section **membres du groupe** ; noms cliquables vers le profil (amis, demandes, groupe ; pas les comptes bloqués).
- Page **Mon groupe famille** : noms des membres cliquables vers le profil.

### Corrigé

- Profil public : les **5 derniers films vus** n’affichent plus des titres sans vision réelle (jointure SQL `historique` ↔ `bibliotheque`).
- Listes collection et envies sur le profil : affichage des films corrigé (`$films` / `$listFilms`).

### Tests

- `UserPublicProfileTest` (accès ami, membres du groupe, refus étranger, historique des visions).

---

## [0.8.2] — 2026-05-19

### Ajouté

- **Versions recherchées sur les envies** : table `wishlist_targets` (migration `024_wishlist_targets.sql`) — plusieurs combinaisons **support + EAN** par film en wishlist, distinctes de l’EAN catalogue et de l’exemplaire futur en collection.
- Fiche film (envie) : panneau « Versions que je cherche », ajout manuel ou depuis les EAN catalogue de l’œuvre.
- Liste **Mes envies** : colonne récapitulative des versions recherchées.

### Tests

- `WishlistTargetsTest` (ajouts multiples, promotion vers collection, import depuis EAN catalogue).

### Prochaine évolution (roadmap)

- Phase **7 bis** : affichage des versions sur le partage visiteur, comparateur de prix (support + EAN), pré-remplissage du support au « J’ai acheté ».

---

## [0.8.1] — 2026-05-19

### Sécurité

- **Partage visiteur** : limite anti brute-force par **adresse IP** (en plus de la session), quota de **10 liens actifs** par compte, en-têtes `X-Robots-Tag: noindex` et `Cache-Control: no-store` sur les pages `/partage.php` et `/partage-film.php`.
- **En-têtes globaux** : `Content-Security-Policy` (scripts depuis `/assets/js/` uniquement ; styles inline autorisés pour les graphiques), **HSTS** envoyé uniquement en HTTPS (production).
- **Recherche SQL** : échappement LIKE unifié via `LikePattern` dans tout le catalogue et les collections.
- Script inline du catalogue déplacé vers `app.js` (compatible CSP `script-src 'self'`).

### Tests

- `ShareSecurityTest`, `LikePatternTest`, `SecurityHeadersTest`.

---

## [0.8.0] — 2026-05-19

### Ajouté

- **Phase 6 bis — EAN catalogue** : table `oeuvre_eans`, gestion sur la fiche œuvre admin (un EAN par support DVD / Blu-ray / 4K), suggestion sur le formulaire « mon exemplaire ».
- **Phase 7 — Partage visiteur** : liens lecture seule (collection du foyer ou envies personnelles), pages publiques `/partage.php` et `/partage-film.php`, gestion `/gerer-partages.php`, expiration 90 jours, révocation, limite anti brute-force.

### Amélioré

- Page partagée visiteur : même confort que **Mes films** — affiches, bascule **Liste** / **Vignettes**, filtres par type (Tout, Films, Séries…), recherche et tri.

### Migrations

- `017_share_links.sql` — liens de partage (`share_links`)
- `023_oeuvre_eans.sql` — codes EAN catalogue (`oeuvre_eans`)

### Déploiement

Après mise à jour du code : `php lib/cli/migrate.php` (applique les migrations 017 et 023 si besoin).

---

## [0.7.10] — 2026-05-21

### Sécurité (fonctions sociales)

- Recherche utilisateurs : échappement des caractères spéciaux SQL `LIKE` (`%`, `_`) — une recherche « % » ne liste plus tout le monde.
- Limitation d’abus : max **20 demandes d’ami / 24 h** et **30 recherches / minute** par compte.
- **Blocage** d’utilisateur : plus de demande d’ami, plus d’invitation groupe, masqué de la recherche ; liste et déblocage dans **Mes amis**.
- Page `bloquer-utilisateur.php` (POST + CSRF) depuis la recherche.

### Déploiement

- Aucune migration SQL.

---

## [0.7.9] — 2026-05-21

### Amélioré

- Lisibilité des liens sur thème sombre (couleurs dédiées, compatibilité `color-scheme: dark`).
- Onglets **Mes films** (Tout, Film, Série…) et **Liste / Vignettes** : contraste stable (texte foncé sur fond doré), y compris pour les liens déjà visités (`:visited`).
- Filtres **Support physique** et onglets **Mes envies / Envies du groupe** : même composant visuel.

### Technique (dette priorité haute)

- Composant CSS réutilisable **`.ui-pill`** / **`.ui-pill-bar`** pour les filtres et onglets (remplace les anciennes classes par page).
- Règles de liens de contenu simplifiées (sélecteurs ciblés `.lead`, `.hint`, etc. — plus de longue chaîne `:not()` sur `main`).
- Fichier **`CHANGELOG.md`** ajouté pour documenter les releases.

### Déploiement

- Aucune migration SQL.
- Remplacer les fichiers ; vider le cache navigateur si besoin (Ctrl+F5).

---

## [0.7.8] — 2026-05-19

### Ajouté

- **Envies du groupe** : agrégation par œuvre, tri par nombre de demandes, liste des votants, bouton « Moi aussi ».
- Ajout en un clic dans la bibliothèque après proposition catalogue acceptée.
- Notifications enrichies (proposition acceptée → lien vers ajout rapide).

---

## [0.7.7] — 2026-05-19

### Ajouté

- **Amis** : demandes, acceptation, refus.
- **Groupes famille** créés par les utilisateurs (remplace la création de foyers par l’admin).
- Invitations au groupe ; `/foyers.php` admin en lecture seule.

### Changé

- Nouveaux comptes sans foyer assigné automatiquement.

---

## [0.7.6] — 2026-05-19

### Ajouté

- Ville optionnelle sur le profil.
- Recherche d’utilisateurs par pseudo / ville.
- Opt-out « masquer mon profil de la recherche ».
- Cloche de notifications compacte dans l’en-tête.

---

## [0.7.4] — 2026-05-19

### Ajouté

- Soumissions au catalogue (proposer, valider, refuser).
- Notifications in-app et par e-mail.
- Navigation Préc./Suiv. entre fiches catalogue et films.

---

## [0.7.0] — 2026-05-19

### Ajouté

- Foyers et collection partagée.
- Envies et historique personnels par utilisateur.

---

## Liens

- Détail des phases futures : [ROADMAP.md](ROADMAP.md)
- Installation et usage : [README.md](README.md)
