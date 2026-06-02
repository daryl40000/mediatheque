# Médiathèque

**Version : 0.2.3**

**Auteur :** Stéphane MATER  
**Licence :** [GNU General Public License v3.0 ou ultérieure](LICENSE) (GPL-3.0-or-later)

**Médiathèque** est l’évolution de **[Monciné](CHANGELOG.md)** : une application web pour gérer **plusieurs types de médias** (films, BD/manga, livres, jeux vidéo, magazines) dans une seule interface, avec des **onglets** et une **couleur par média**.

En **0.2.x**, l’onglet **Films** reprend toute la dvdthèque Monciné ; l’onglet **Magazines** permet de gérer des séries et numéros (PDF, recherche, tags papier/PDF). Les autres onglets affichent « Bientôt disponible ».

| Document | Contenu |
|----------|---------|
| [doc/conventions-techniques.md](doc/conventions-techniques.md) | **Règles de nommage** Monciné vs Médiathèque (obligatoire pour les devs) |
| [doc/mediatheque.md](doc/mediatheque.md) | Guide du fork, socle multi-médias |
| [doc/magazines.md](doc/magazines.md) | Magazines : PDF, recherche, tags, hors-série |
| [ROADMAP.md](ROADMAP.md) | Plan détaillé M0 → M7 |
| [CHANGELOG.md](CHANGELOG.md) | Journal des versions (Médiathèque + historique Monciné) |

---

## Fonctionnalités (onglet Films — v0.1.0)

| Domaine | Disponible |
|---------|------------|
| Multi-médias | Onglets Films / BD / Livres / Jeux / Magazines + thème couleur |
| Magazines | Séries, numéros, couvertures, PDF, sommaire, recherche OCR 6 pages, tags support (**0.2.0+**, détail [doc/magazines.md](doc/magazines.md)) |
| Collection & envies | Mes films, Mes envies, sagas, statistiques, **questionnaire du soir**, **listes imprimables** |
| Prêts | Demandes entre amis, réservation, validation et retour (`/mes-prets.php`) |
| Stockage médias | Fichiers volumineux hors `www/` (PDF magazines par numéro) |
| Foyers & famille | Collection partagée par foyer ; envies et historique personnels |
| Catalogue partagé | Fiches œuvres, enrichissement TMDB / OMDB, affiches |
| Comptes | Connexion, rôles admin/utilisateur, inscription publique optionnelle |
| Social | Amis, groupe famille, envies du groupe, partage visiteur |
| EAN | Codes-barres multiples par œuvre catalogue |

### Prochaines versions (résumé)

| Version cible | Contenu |
|---------------|---------|
| **0.2.x (M1)** | Stabilisation films, tests de non-régression |
| **0.3.x (M2)** | BD / Manga |
| **0.4.x (M3)** | Livres |
| **0.5.x (M4)** | Jeux vidéo |
| **0.6.x (M5)** | Magazines (PDF, lecteur, recherche) |
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

- PHP **8.2+** avec extension **sqlite3**
- [Composer](https://getcomposer.org/) (pour les tests)

---

## Installation et test en local

```bash
cd /chemin/vers/mediatheque
composer install
php lib/cli/migrate.php          # ou --fresh la première fois
php -S localhost:8080 -t www
```

Ouvrir http://localhost:8080 — créer le **compte administrateur** sur `/premier-compte.php` si besoin.

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

## Migrations SQL

- **Install fraîche** : `sql/schema.sql` puis `sql/migrations/*.sql`
- **Mise à jour** : `php lib/cli/migrate.php`
- **Version schéma actuelle** : **030** (`media_domain`)

Les fichiers `sql/migrations_legacy/` ne sont **pas** exécutés (historique Monciné).

---

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
