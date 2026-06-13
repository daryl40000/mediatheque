# Médiathèque

**Version : 0.5.1**

**Auteur :** Stéphane MATER  
**Licence :** [GNU General Public License v3.0 ou ultérieure](LICENSE) (GPL-3.0-or-later)

**Médiathèque** est l’évolution de **[Monciné](CHANGELOG.md)** : une application web pour gérer **plusieurs types de médias** (films, BD/manga, livres, jeux vidéo, magazines) dans une seule interface, avec des **onglets** et une **couleur par média**.

En **0.2.x**, l’onglet **Films** reprend toute la dvdthèque Monciné ; l’onglet **Magazines** permet de gérer des séries et numéros (PDF, recherche, tags papier/PDF). L’onglet **Jeux** est utilisable depuis **0.5.0** (collection, envies, pont magazine). Les onglets BD et Livres affichent « Bientôt disponible ».

| Document | Contenu |
|----------|---------|
| [doc/conventions-techniques.md](doc/conventions-techniques.md) | **Règles de nommage** Monciné vs Médiathèque (obligatoire pour les devs) |
| [doc/mediatheque.md](doc/mediatheque.md) | Guide du fork, socle multi-médias |
| [doc/magazines.md](doc/magazines.md) | Magazines : PDF, recherche, tags, hors-série |
| [doc/jeux.md](doc/jeux.md) | Jeux vidéo : collection, pont magazine, Linux |
| [ROADMAP.md](ROADMAP.md) | Plan détaillé M0 → M7 |
| [CHANGELOG.md](CHANGELOG.md) | Journal des versions (Médiathèque + historique Monciné) |

---

## Fonctionnalités (onglet Films — v0.1.0)

| Domaine | Disponible |
|---------|------------|
| Multi-médias | Onglets Films / BD / Livres / Jeux / Magazines + thème couleur |
| Magazines | Séries, numéros, couvertures, PDF, sommaire, recherche OCR 6 pages, tags support (**0.2.0+**, détail [doc/magazines.md](doc/magazines.md)) |
| Jeux vidéo | Collection, envies, notes, statistiques, pont magazine, fichiers attachés, vue vignettes, badges Linux (**0.5.0+**, détail [doc/jeux.md](doc/jeux.md)) |
| Collection & envies | Mes films, Mes envies, sagas, statistiques, **questionnaire du soir**, **listes imprimables** |
| Prêts | Demandes entre amis, réservation, validation et retour (`/mes-prets.php`) |
| Stockage médias | Fichiers volumineux hors `www/` (PDF magazines par numéro) |
| Foyers & famille | Collection partagée par foyer ; envies et historique personnels |
| Catalogue partagé | Fiches œuvres, enrichissement TMDB / OMDB, affiches |
| Comptes | Connexion, rôles admin/utilisateur, inscription publique optionnelle |
| Social | Amis, groupe famille, envies du groupe, partage visiteur, **profil public multi-onglets** (films + magazines) |
| EAN | Codes-barres multiples par œuvre catalogue |

### Prochaines versions (résumé)

| Version cible | Contenu |
|---------------|---------|
| **0.5.1 (M4 ✅)** | Jeux : fichiers attachés, vue vignettes, icônes support, Linux testé / non supporté |
| **0.5.0 (M4 ✅)** | Jeux vidéo : collection, envies, pont magazine, accueil dédié, badge Linux |
| **0.4.4 (M1 ✅)** | Stabilisation films : QA prod, grille homogène, pagination collection |
| **0.3.x (M2)** | BD / Manga |
| **0.4.x (M3)** | Livres |
| **0.6.x (M5)** | Magazines (PDF, lecteur, recherche) — suite polish |
| **1.0.0 (M6–M7)** | Fonctions transverses + identité « Médiathèque » aboutie |

Détail phase par phase : [ROADMAP.md](ROADMAP.md).

---

## Comprendre le code (par où commencer)

> **Développeurs :** lire en premier [doc/conventions-techniques.md](doc/conventions-techniques.md) (nom produit **Médiathèque** vs identifiants code **Moncine** / `MONCINE_*`).

| Fichier | Rôle |
|---------|------|
| `lib/bootstrap.php` | Config, base, session, domaine média actif |
| `lib/MediaDomain.php` / `lib/MediaContext.php` | Onglets et filtre `media_domain` |
| `lib/Auth.php` | Connexion, pages publiques |
| `lib/FilmRepository.php` | Collection films (façade catalogue) |
| `lib/CatalogSchema.php` | Jointures œuvres + bibliothèque + filtre domaine |
| `www/*.php` | Une page = un contrôleur léger |
| `templates/layout.php` | Menu, onglets média, thème |

Documentation Monciné (toujours valable pour l’onglet Films) :

- [questionnaire du soir](doc/questionnaire-du-soir.md)
- [listes imprimables](doc/listes-imprimables.md)
- [comptes et mots de passe](doc/comptes-mot-de-passe.md)

---

## Structure du projet

```text
mediatheque/
├── www/              pages web
├── lib/              code PHP (+ cli/migrate.php)
├── templates/        vues HTML
├── sql/
│   ├── schema.sql    schéma complet (install fraîche)
│   └── migrations/   001 … 030 (media_domain)
├── data/             base SQLite, clés API (non versionné)
├── tests/            PHPUnit
├── doc/              documentation
└── install_seed/     graine d’install optionnelle (CSV/ZIP non versionnés)
```

---

## Prérequis

- **PHP 8.2 ou plus** avec l’extension **sqlite3** (obligatoire)
- **[Composer](https://getcomposer.org/)** — utile surtout pour lancer les tests (`composer install`)
- **Optionnel (magazines)** : paquet **Poppler** (`pdftotext`, `pdftoppm`, `pdfinfo`) pour la recherche dans les PDF et les couvertures auto — sous Debian/Ubuntu : `sudo apt install poppler-utils`

---

## Installation et utilisation de la webapp

Cette section décrit comment **installer** Médiathèque sur votre machine et **l’utiliser** au quotidien dans le navigateur.

### 1. Récupérer le projet

```bash
git clone https://github.com/VOTRE_ORGANISATION/mediatheque.git
cd mediatheque
```

*(Adaptez l’URL du dépôt si besoin.)*

### 2. Dépendances PHP

```bash
composer install
```

Sans Composer, le site peut quand même tourner si vous n’exécutez pas les tests PHPUnit.

### 3. Base de données

Au **premier accès** au site (navigateur ou ligne de commande), les migrations SQL s’appliquent **automatiquement** et créent la base SQLite dans `data/` (fichier `moncine.db`).

Pour vérifier l’état du schéma :

```bash
php lib/cli/migrate.php
```

**Première installation sur une machine vide** (base inexistante uniquement) :

```bash
php lib/cli/migrate.php --fresh
```

Ne pas utiliser `--fresh` si vous avez déjà des données dans `data/moncine.db`.

**Graine optionnelle** (catalogue films + affiches au premier install vide) : déposez un CSV et/ou un ZIP dans `install_seed/` — voir [install_seed/README.md](install_seed/README.md).

### 4. Lancer le serveur web local

Deux façons équivalentes pour ouvrir le site ; la **recommandée** pour les **PDF magazines** (gros fichiers) est `start-dev.sh`.

| Méthode | Commande | Quand l’utiliser |
|---------|----------|------------------|
| **Recommandée** | `./start-dev.sh` | Magazines, import PDF jusqu’à ~350 Mo, sessions stables |
| **Simple** | `php -S localhost:8080 -t www` | Essai rapide (limites d’upload PHP souvent basses) |

Par défaut le site écoute sur **http://localhost:8080/**  
Pour un autre port : `./www/serve.sh localhost:9000`

Arrêt du serveur : **Ctrl+C** dans le terminal.

### 5. Premier démarrage dans le navigateur

1. Ouvrez **http://localhost:8080/**
2. Si aucun compte n’existe, vous êtes guidé vers **`/premier-compte.php`** — créez le **compte administrateur** (identifiant, mot de passe, foyer).
3. Ensuite, connectez-vous via **`/connexion.php`** si nécessaire.
4. Vous arrivez sur l’**accueil** : choisissez un **onglet** en haut (Films, Magazines, etc.).

Les données (base, affiches, PDF, sessions) sont stockées dans le dossier **`data/`** à la racine du projet (non versionné par git).

### 6. Utilisation au quotidien

#### Navigation générale

- **Onglets** en haut : chaque type de média a sa couleur et ses menus (**Ma collection**, **Mes envies**, **Statistiques**, etc.).
- **Films** : dvdthèque complète (collection, envies, visions, prêts entre amis, questionnaire du soir…).
- **Magazines** (version 0.2.x) : séries → numéros → fiche détaillée ; détail dans [doc/magazines.md](doc/magazines.md).
- **BD, Livres, Jeux** : onglets présents mais marqués **« Bientôt disponible »** pour l’instant.

#### Parcours magazines (résumé)

| Action | Où |
|--------|-----|
| Voir vos revues | Onglet **Magazines** → **Mes magazines** (`/magazines.php`) |
| Numéros d’une série | Cliquez sur une série → grille (48 numéros par page) |
| Ajouter une série / un numéro | Boutons **Ajouter une série** / **Ajouter un numéro** |
| Importer un PDF | Fiche du numéro → section **PDF du numéro** |
| Numéros souhaités | **Mes envies magazines** (`/magazines-envies.php`) |
| Statistiques (PDF, Go…) | **Statistiques** dans l’onglet Magazines |
| Export liste en PDF | Bouton **Exporter en PDF** sur la page série (possession par numéro, sans couvertures) |
| **Recherche globale** | Champ **Rechercher dans vos magazines** sur **Mes magazines** — séries, sujets, sommaires, extraits PDF (**0.4.1**) |
| **Recherche par sujet** | **Recherche par sujet** (`/magazines-recherche.php`) — tests, previews, dossiers, interviews ; tags de série et année du numéro (**0.4.0**, Interview **0.4.2**) |
| **Filtre hors-série** | Liste numéros d’une série → bouton **Hors-série** (**0.4.2**) |
| **Nettoyage sujets (admin)** | **Maintenance catalogue** → **Sujets magazines** — orphelins et doublons après faute de frappe (**0.4.2**) |
| **Pagination collection** | Mes films — 56 vignettes (7×8) ou 100 en liste par page (**0.4.4**) |
| **Année du sujet** | Menu déroulant sur la fiche numéro (défaut = année du magazine, modifiable) (**0.4.3**) |

Pour importer de **gros PDF**, utilisez toujours **`./start-dev.sh`** plutôt que le serveur PHP minimal.

#### Parcours films (résumé)

| Action | Où |
|--------|-----|
| Ma collection | Onglet **Films** → liste des films |
| Ajouter un film | Recherche catalogue ou formulaire d’ajout |
| Mes envies | Menu **Mes envies** |
| Import / export bibliothèque | Pages **Importer** / export selon votre profil |
| Admin catalogue | Compte administrateur → **Catalogue** |

Documentation complémentaire : [questionnaire du soir](doc/questionnaire-du-soir.md), [listes imprimables](doc/listes-imprimables.md), [comptes et mots de passe](doc/comptes-mot-de-passe.md).

#### Profil public d’un ami (`/utilisateur.php`)

Visible par les **amis** et les **membres du même groupe** :

| Onglet profil | Contenu |
|---------------|---------|
| **Films** | Statistiques, 5 derniers vus/ajouts, listes collection / envies / visions, demandes de prêt |
| **Magazines** | Statistiques séries et numéros, listes collection / envies, **numéros par série** (lecture seule) |
| **BD, Livres, Jeux** | Message « bientôt disponible » sur le profil |

Sur l’onglet **Magazines** du profil : cliquez une **série** → liste des numéros (`/utilisateur-serie-magazine.php`) → fiche numéro avec sommaire (`/utilisateur-numero-magazine.php`). Les **PDF ne sont pas partagés** (couvertures et texte uniquement).

Paramètre d’URL : `?id=…&domain=magazine` (Films par défaut si omis).

### 7. Mise à jour du logiciel

Après un `git pull` :

```bash
composer install          # si les dépendances ont changé
php lib/cli/migrate.php   # contrôle des migrations (appliquées au prochain accès web)
./start-dev.sh            # relancer le serveur
```

### Variables d’environnement utiles

| Variable | Rôle |
|----------|------|
| `MONCINE_DATA_PATH` | Dossier des données (base, clés API, affiches) |
| `MONCINE_MEDIA_PATH` | Fichiers volumineux (PDF, exports…) |
| `MONCINE_BASE_URL` | URL publique (e-mails) |
| `MONCINE_TRUST_PROXY` | `1` derrière Nginx/YunoHost |
| `MONCINE_MAIL_FROM` | Expéditeur des e-mails |

*(Les noms `MONCINE_*` sont conservés volontairement — voir [doc/conventions-techniques.md](doc/conventions-techniques.md).)*

---

## Installation rapide (résumé)

```bash
cd mediatheque
composer install
./start-dev.sh
```

Puis ouvrir **http://localhost:8080/** et créer le compte sur **`/premier-compte.php`**.

---

## Migrations SQL

- **Install fraîche** : création automatique via `Database::getInstance()` (schéma + fichiers dans `sql/migrations/`)
- **Contrôle manuel** : `php lib/cli/migrate.php`
- **Recréer une base vide** : supprimer `data/moncine.db`, puis `php lib/cli/migrate.php --fresh` ou premier accès web
- Les fichiers `sql/migrations_legacy/` ne sont **pas** exécutés (historique Monciné)

Voir aussi la section [Installation et utilisation](#installation-et-utilisation-de-la-webapp) ci-dessus.

## Tests

```bash
composer test
# ou
./vendor/bin/phpunit
```

---

## Lien avec Monciné

| | Monciné | Médiathèque |
|---|---------|-------------|
| Version production films | **1.0.0** | — |
| Version fork multi-médias | — | **0.1.0** (films OK) |
| Nom affiché | Monciné | Médiathèque |
| Code PHP | `Moncine\` | `Moncine\` (inchangé — voir [conventions-techniques.md](doc/conventions-techniques.md)) |

L’historique complet Monciné **0.7 → 1.0.0** est dans [CHANGELOG.md](CHANGELOG.md).
