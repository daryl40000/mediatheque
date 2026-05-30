# Journal des versions (Médiathèque)

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Les numéros suivent le [versionnement sémantique](https://semver.org/lang/fr/).

**Lignée :** ce dépôt est un **fork** de [Monciné](README.md) (dvdthèque films). L’historique Monciné **0.7 → 1.0.0** reste dans ce fichier ; la branche Médiathèque repart en **0.1.0** pour le multi-médias.

**Tags Git recommandés :** `v0.1.0` (Médiathèque) ; historique Monciné `v1.0.0`, `v0.8.0`, etc.

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
