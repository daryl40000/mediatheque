# Médiathèque

[![CodeFactor](https://www.codefactor.io/repository/github/daryl40000/mediatheque/badge)](https://www.codefactor.io/repository/github/daryl40000/mediatheque)

**Version : 0.7.9**

**Auteur :** Stéphane MATER  
**Licence :** [GNU General Public License v3.0 ou ultérieure](LICENSE) (GPL-3.0-or-later)

**Médiathèque** est l’évolution de **[Monciné](CHANGELOG.md)** : une application web pour gérer **plusieurs types de médias** (films, BD/manga, livres, musique, jeux vidéo, magazines) dans une seule interface, avec des **onglets** et une **couleur par média**.

En **0.2.x**, l’onglet **Films** reprend toute la dvdthèque Monciné. Depuis **0.7.6** : les notes sur 10 sont remplacées par des **ressentis** (5 paliers avec icônes), sans moyenne foyer. L’onglet **Jeux** est **pleinement utilisable** depuis **0.5.0** ; **0.7.7** ajoute le magasin démat **Battle.net** et allège le code catalogue (`GameRepository` refactoré). **0.7.8** ajoute les onglets placeholder **Livres** et **Musique** (vinyles/CD), refactorise `BdRepository` et `MagazineRepository` (phase B qualité code), et corrige la navigation entre onglets. L’onglet **Magazines** gère séries et numéros (PDF, recherche FTS, sujets tests/previews) ; depuis **0.6.0** : **import catalogue ABM**, ajout d’une série depuis le catalogue, retrait d’une série de la bibliothèque, dates de parution normalisées. L’onglet **BD** est **en cours** (collection, envies, partage) ; **Livres** et **Musique** affichent « Bientôt disponible ».

| Document | Contenu |
|----------|---------|
| [doc/conventions-techniques.md](doc/conventions-techniques.md) | **Règles de nommage** Monciné vs Médiathèque (obligatoire pour les devs) |
| [doc/base-de-donnees.md](doc/base-de-donnees.md) | **Structure SQLite** : tables, relations, migrations (à maintenir à jour) |
| [doc/mediatheque.md](doc/mediatheque.md) | Guide du fork, socle multi-médias |
| [doc/magazines.md](doc/magazines.md) | Magazines : PDF, recherche, tags, import catalogue ABM (**0.6.0**) |
| [doc/import-abm.md](doc/import-abm.md) | Import catalogue depuis Abandonware Magazines (CLI + admin) |
| [doc/jeux.md](doc/jeux.md) | Jeux vidéo : collection, pont magazine, Linux, recherche/filtres |
| [doc/partage-visiteur.md](doc/partage-visiteur.md) | Liens lecture seule : recherche, filtres, colonnes notes (**0.7.0**) |
| [doc/pont-magazine-jeu.md](doc/pont-magazine-jeu.md) | Pont magazine ↔ jeux : lien catalogue, homonymes, admin |
| [doc/import-musique.md](doc/import-musique.md) | **Spécification** import musique (vinyles, CD — phase M8) |
| [doc/import-gog.md](doc/import-gog.md) | **Spécification** import bibliothèque GOG (à implémenter) |
| [ROADMAP.md](ROADMAP.md) | Plan détaillé M0 → M7 |
| [roadmap-amelioration-code.md](roadmap-amelioration-code.md) | Roadmap qualité / refactor code (phases A–F) |
| [CHANGELOG.md](CHANGELOG.md) | Journal des versions (Médiathèque + historique Monciné) |

---

## Fonctionnalités par onglet

| Domaine | Statut | Points clés |
|---------|--------|-------------|
| **Multi-médias** | ✅ | Onglets Films / BD / Livres / **Musique** / Jeux / Magazines + thème couleur par domaine |
| **Films** | ✅ Production | Collection, envies, TMDB/OMDB, quiz « Ce soir », prêts, sagas, **vue Bibliothèque** (**0.5.7**), listes imprimables (**0.4.4+**) |
| **Jeux vidéo** | ✅ Utilisable | Collection, envies, notes, stats, **extensions DLC**, **remakes**, **enrichissement IGDB**, **sagas jeux**, **vue Bibliothèque**, recherche **acronymes**, fichiers attachés, Linux tri-état, **pont magazine ↔ jeux** (**0.6.3**), fiche `/oeuvre-jeu.php`, autocomplétion à l’ajout, **recherche tolérante** (**0.5.7**, [doc/jeux.md](doc/jeux.md)) |
| **Magazines** | ✅ Complet (M5) | Séries, numéros, PDF, FTS, import/export catalogue ABM, autocomplétion série/numéro, profil public ([doc/magazines.md](doc/magazines.md)) |
| **BD / Manga** | 🔄 En cours (M2) | Collection, envies, partage, profil public, impression ; import CSV à venir |
| **Livres** | ⏸️ Placeholder | Onglet + page « bientôt » (`/livres.php`) — **0.7.8** |
| **Musique** | ⏸️ Placeholder | Onglet ambre + page « bientôt » (`/musique.php`) — vinyles/CD — **0.7.8** |
| **Transversal** | Partiel | Catalogue partagé multi-domaines, foyers, amis, partage visiteur, profil public (films + magazines + **jeux**) |

**Commun à tous les onglets actifs :** prêts entre amis (physique), import/export, comptes et foyers, codes EAN catalogue, soumissions au catalogue, notifications.

### Catalogue partagé (admin)

| Domaine | Fiche catalogue | Ajout à ma collection |
|---------|-----------------|------------------------|
| Film | `/oeuvre.php` | Autocomplétion titre + réalisateur |
| Jeu | `/oeuvre-jeu.php` | Autocomplétion titre (plateforme · année) |
| Magazine | `/oeuvre-magazine.php` | Fiche catalogue ; ajout série via autocomplétion catalogue (**0.6.0**) |

### Prochaines étapes (résumé)

Voir le détail dans [ROADMAP.md](ROADMAP.md).

| Priorité | Contenu | Version cible |
|----------|---------|---------------|
| 1 | Tag **v0.6.0** (import catalogue magazines ABM) | **0.6.0** |
| 2 | Magazines : autocomplétion à l’ajout numéro, profil public | **0.6.x** |
| 3 | ~~Pont magazine ↔ jeu~~ | ✅ 0.6.3 |
| 4 | Polish jeux (plateformes admin, non prêtable si démat) | 0.5.x |
| 5 | Transversal multi-domaines (stats, partage, import/export) | **0.9.0** |
| 6 | BD / Manga, Livres | 0.6.x+ |
| 7 | Identité Médiathèque aboutie | **1.0.0** |

### Versions récentes livrées

| Version | Contenu |
|---------|---------|
| **0.7.9** | Import Steam, refonte fiche jeu, stats temps de jeu, maintenance doublons légitimes |
| **0.7.81** | Qualité code : moteur autocomplétion JS partagé, nettoyage CSS (CodeFactor), lisibilité PHP |
| **0.7.8** | Onglets Livres et Musique (placeholder), refactor `BdRepository` / `MagazineRepository` (phase B), fix navigation onglets, [doc/import-musique.md](doc/import-musique.md) |
| **0.7.7** | Battle.net, ressentis sociaux discrets, refactor `GameRepository` |
| **0.7.1** | Correctif tri « Fini le » sur Mes jeux (erreur 500 SQL) |
| **0.7.0** | Partage visiteur : filtres jeux (plateforme, support, magasin), colonnes Note/Fini le (jeux) et Note/Dernière vue (films) ; barre recherche Mes jeux sur une ligne ; correctif filtre Steam/Epic |
| **0.6.9** | Jeux terminés (date, stats, accueil), filtres recherche plateforme/magasin, icône disquette, fix jaquette |
| **0.6.8** | Propositions jeux au catalogue (comme les films), plateforme SNES, fix validation admin |
| **0.6.7** | Partage visiteur : pages jeux publiques, jaquettes sans login, extensions/remakes sur fiche partagée |
| **0.6.5** | Prêts jeux physiques, multi-plateformes, admin plateformes, foyer personnel auto, formulaires bibliothèque utilisateur, **sagas films catalogue partagé** |
| **0.6.4** | Fix liens croisés jeux ↔ magazines (clic depuis l’autre onglet ouvre la bonne fiche) |
| **0.6.3** | Pont magazine ↔ jeux : rattachement rétroactif admin, recherche globale par titre catalogue, doc homonymes |
| **0.6.2** | Jeux : sagas en vignettes, extensions triées chronologiquement sur les fiches |
| **0.6.1** | Magazines M5 : autocomplétion numéro catalogue, export JSON, profil public numéros récents ; jeux : partage visiteur et listes imprimables |
| **0.6.0** | Magazines : import catalogue **ABM**, ajout série catalogue, retrait série, dates parution FR, couvertures par lots |
| **0.5.7** | Vue **Bibliothèque** (films + jeux), option « Garder la jaquette » IGDB, recherche jeux par acronymes |
| **0.5.6** | Sagas jeux (`/sagas-jeux.php`), autocomplétion saga, doc base de données, correctif filtre genre multi-tags |
| **0.5.5** | Enrichissement jeux IGDB (jaquette, titres FR/EN, studio, genres, franchise, modes, thèmes, acronymes) |
| **0.5.4** | Remakes jeux, affichage jaquettes extensions/remakes, recherche insensible accents + 1 faute (autocomplétion) |
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
| `lib/BdRepository.php` | Collection BD + catalogue (façade ; logique dans `BdLibraryQuery`, …) |
| `lib/GameRepository.php` | Collection jeux + catalogue partagé jeux |
| `lib/MagazineRepository.php` | Numéros magazines + catalogue (façade ; logique dans `MagazineLibraryQuery`, …) |
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
│   └── migrations/   001 … 045 (remakes jeux, extensions, media_domain, …)
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
- **Jeux** (**0.5.7**) : collection (`/jeux.php`), envies, statistiques, extensions, remakes, **enrichissement IGDB**, **sagas jeux**, **vue Bibliothèque**, recherche acronymes ; détail [doc/jeux.md](doc/jeux.md).
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
| Sagas jeux | **Sagas jeux** (`/sagas-jeux.php`) — regroupement par saga IGDB, action de masse (**0.5.6**) |
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
| Version fork multi-médias | — | **0.6.0** (films + vue Bibliothèque, jeux + IGDB + sagas, magazines + import ABM) |
| Nom affiché | Monciné | Médiathèque |
| Code PHP | `Moncine\` | `Moncine\` (inchangé — voir [conventions-techniques.md](doc/conventions-techniques.md)) |

L’historique complet Monciné **0.7 → 1.0.0** est dans [CHANGELOG.md](CHANGELOG.md).
