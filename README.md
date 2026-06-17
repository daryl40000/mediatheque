# Médiathèque

**Version : 0.5.3**

**Auteur :** Stéphane MATER  
**Licence :** [GNU General Public License v3.0 ou ultérieure](LICENSE) (GPL-3.0-or-later)

**Médiathèque** est l’évolution de **[Monciné](CHANGELOG.md)** : une application web pour gérer **plusieurs types de médias** (films, BD/manga, livres, jeux vidéo, magazines) dans une seule interface, avec des **onglets** et une **couleur par média**.

En **0.2.x**, l’onglet **Films** reprend toute la dvdthèque Monciné. L’onglet **Magazines** gère séries et numéros (PDF, recherche FTS, sujets tests/previews). L’onglet **Jeux** est **pleinement utilisable** depuis **0.5.0** (collection, envies, statistiques, pont magazine) et **0.5.3** (fiches catalogue dédiées, autocomplétion à l’ajout). Les onglets **BD** et **Livres** affichent encore « Bientôt disponible ».

| Document | Contenu |
|----------|---------|
| [doc/conventions-techniques.md](doc/conventions-techniques.md) | **Règles de nommage** Monciné vs Médiathèque (obligatoire pour les devs) |
| [doc/mediatheque.md](doc/mediatheque.md) | Guide du fork, socle multi-médias |
| [doc/magazines.md](doc/magazines.md) | Magazines : PDF, recherche, tags, hors-série |
| [doc/jeux.md](doc/jeux.md) | Jeux vidéo : collection, pont magazine, Linux |
| [ROADMAP.md](ROADMAP.md) | Plan détaillé M0 → M7 |
| [CHANGELOG.md](CHANGELOG.md) | Journal des versions (Médiathèque + historique Monciné) |

---

## Fonctionnalités par onglet

| Domaine | Statut | Points clés |
|---------|--------|-------------|
| **Multi-médias** | ✅ | Onglets Films / BD / Livres / Jeux / Magazines + thème couleur par domaine |
| **Films** | ✅ Production | Collection, envies, TMDB/OMDB, quiz « Ce soir », prêts, sagas, listes imprimables (**0.4.4+**) |
| **Jeux vidéo** | ✅ Utilisable | Collection, envies, notes, stats, extensions DLC, fichiers attachés, Linux tri-état, pont magazine, **fiche catalogue** `/oeuvre-jeu.php`, **autocomplétion** à l’ajout (**0.5.3**, [doc/jeux.md](doc/jeux.md)) |
| **Magazines** | 🔄 Avancé | Séries, numéros, PDF, sommaire, FTS, sujets tests/previews, **fiche catalogue** `/oeuvre-magazine.php` (**0.2.x–0.4.x**, [doc/magazines.md](doc/magazines.md)) |
| **BD / Livres** | ⏸️ Bientôt | Onglets présents, contenu à venir (M2 / M3) |
| **Transversal** | Partiel | Catalogue partagé multi-domaines, foyers, amis, partage visiteur, profil public (films + magazines + **jeux**) |

**Commun à tous les onglets actifs :** prêts entre amis (physique), import/export, comptes et foyers, codes EAN catalogue, soumissions au catalogue, notifications.

### Catalogue partagé (admin)

| Domaine | Fiche catalogue | Ajout à ma collection |
|---------|-----------------|------------------------|
| Film | `/oeuvre.php` | Autocomplétion titre + réalisateur |
| Jeu | `/oeuvre-jeu.php` | Autocomplétion titre (plateforme · année) |
| Magazine | `/oeuvre-magazine.php` | Depuis la fiche catalogue (autocomplétion à l’ajout : prochaine étape M5) |

### Prochaines étapes (résumé)

Voir le détail dans [ROADMAP.md](ROADMAP.md).

| Priorité | Contenu | Version cible |
|----------|---------|---------------|
| 1 | QA et tag **v0.5.3** | 0.5.3 |
| 2 | Magazines : autocomplétion à l’ajout, profil public, parité catalogue | 0.5.4 → **0.6.0** |
| 3 | Pont magazine ↔ jeu : rattachement rétroactif des sujets | 0.6.0 |
| 4 | Polish jeux (plateformes admin, non prêtable si démat) | 0.5.x |
| 5 | Transversal multi-domaines (stats, partage, import/export) | **0.9.0** |
| 6 | BD / Manga, Livres | 0.6.x+ |
| 7 | Identité Médiathèque aboutie | **1.0.0** |

### Versions récentes livrées

| Version | Contenu |
|---------|---------|
| **0.5.3** | Fiches catalogue jeux/magazines, autocomplétion ajout jeu, stats jeux, profil public jeux |
| **0.5.2** | Catalogue admin multi-médias, extensions jeux (DLC), export/import `media_domain` |
| **0.5.1** | Jeux : fichiers attachés, vignettes, Linux testé / non supporté |
| **0.5.0** | Jeux vidéo : collection, envies, pont magazine |
| **0.4.4** | Films : QA prod, grille homogène, pagination collection |

---

## Comprendre le code (par où commencer)

> **Développeurs :** lire en premier [doc/conventions-techniques.md](doc/conventions-techniques.md) (nom produit **Médiathèque** vs identifiants code **Moncine** / `MONCINE_*`).

| Fichier | Rôle |
|---------|------|
| `lib/bootstrap.php` | Config, base, session, domaine média actif |
| `lib/MediaDomain.php` / `lib/MediaContext.php` | Onglets et filtre `media_domain` |
| `lib/Auth.php` | Connexion, pages publiques |
| `lib/FilmRepository.php` | Collection films (façade catalogue) |
| `lib/GameRepository.php` | Collection jeux + catalogue partagé jeux |
| `lib/MagazineRepository.php` | Numéros magazines + catalogue |
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
│   └── migrations/   001 … 044 (extensions jeux, media_domain, …)
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
- **Jeux** (**0.5.3**) : collection (`/jeux.php`), envies, statistiques, ajout avec autocomplétion catalogue ; détail [doc/jeux.md](doc/jeux.md).
- **Magazines** : séries → numéros → fiche détaillée ; détail [doc/magazines.md](doc/magazines.md).
- **BD, Livres** : onglets présents mais marqués **« Bientôt disponible »**.

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

#### Parcours jeux (résumé)

| Action | Où |
|--------|-----|
| Ma collection | Onglet **Jeux** → **Mes jeux** (`/jeux.php`) |
| Mes envies | **Mes envies jeux** (`/jeux-envies.php`) |
| Ajouter un jeu | **Ajouter un jeu** — tapez le titre : si le jeu est au catalogue, choisissez-le dans la liste (**0.5.3**) |
| Fiche d’un jeu possédé | Cliquez sur un titre → `/jeu.php` (notes, fichiers, lien magazines) |
| Statistiques | **Statistiques** dans l’onglet Jeux (plateformes, genres, extensions…) |
| Catalogue admin | Compte admin → **Catalogue** → fiche `/oeuvre-jeu.php` pour les jeux |

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
| **Magazines** | Statistiques séries et numéros, listes collection / envies, numéros par série (lecture seule) |
| **Jeux** | Statistiques, 5 derniers jeux notés, grilles collection / envies (**0.5.3**) |
| **BD, Livres** | Message « bientôt disponible » sur le profil |

Sur l’onglet **Magazines** du profil : cliquez une **série** → liste des numéros (`/utilisateur-serie-magazine.php`) → fiche numéro avec sommaire (`/utilisateur-numero-magazine.php`). Les **PDF ne sont pas partagés** (couvertures et texte uniquement).

Paramètre d’URL : `?id=…&domain=film|magazine|jeu` (Films par défaut si omis).

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
| Version fork multi-médias | — | **0.5.3** (films, jeux, magazines avancés) |
| Nom affiché | Monciné | Médiathèque |
| Code PHP | `Moncine\` | `Moncine\` (inchangé — voir [conventions-techniques.md](doc/conventions-techniques.md)) |

L’historique complet Monciné **0.7 → 1.0.0** est dans [CHANGELOG.md](CHANGELOG.md).
