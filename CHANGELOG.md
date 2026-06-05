# Journal des versions (Médiathèque)

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Les numéros suivent le [versionnement sémantique](https://semver.org/lang/fr/).

**Lignée :** ce dépôt est un **fork** de [Monciné](README.md) (dvdthèque films). L’historique Monciné **0.7 → 1.0.0** reste dans ce fichier ; la branche Médiathèque repart en **0.1.0** pour le multi-médias.

**Tags Git recommandés :** `v0.1.0` (Médiathèque) ; historique Monciné `v1.0.0`, `v0.8.0`, etc.

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
