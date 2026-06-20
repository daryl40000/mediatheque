# Conventions techniques — Médiathèque (fork Monciné)

**Version document :** alignée sur **Médiathèque 0.1.0**  
**Public :** développeurs et assistants de codage travaillant sur ce dépôt.

Ce document fixe les **règles de nommage** et les **pièges à éviter** pour que les évolutions multi-médias restent cohérentes avec le code Monciné existant.

---

## 1. Deux « noms » pour un même projet

| Niveau | Nom utilisé | Où ? |
|--------|-------------|------|
| **Produit / utilisateur** | **Médiathèque** | Titre des pages, README, CHANGELOG, version **0.1.0** |
| **Code / déploiement hérité** | **Monciné** | Namespace PHP, constantes `MONCINE_*`, fichier `moncine.db`, paquet Composer historique |

Ce n’est **pas** une incohérence à corriger au fil de l’eau : c’est un **choix volontaire** en 0.1.0 pour :

- réutiliser tout le code Monciné 1.0.0 sans refactor massif ;
- rester compatible avec un déploiement type **YunoHost** / variables d’environnement déjà documentées ;
- limiter les risques de régression sur l’onglet **Films**.

> **Règle d’or :** tant que la phase **M7** (roadmap) n’a pas explicitement prévu et testé un renommage global, **ne pas renommer** les identifiants techniques `Moncine` / `MONCINE_*` / `moncine.db`.

---

## 2. Ce qu’il ne faut PAS renommer (sans décision M7)

### 2.1 Namespace PHP : `Moncine\`

```php
// ✅ Correct — nouvelle classe multi-médias
namespace Moncine;

final class MediaDomain { … }

// ❌ Incorrect — ne pas introduire un second namespace parallèle
namespace Mediatheque;
```

- **Autoload :** `lib/NomClasse.php` → `Moncine\NomClasse` (voir `lib/bootstrap.php`).
- **Tests :** `Moncine\Tests\…` dans `tests/`.
- Toute nouvelle classe va dans `lib/` avec le namespace **`Moncine\`**.

### 2.2 Constantes et configuration (`lib/config.php`)

| Constante | Rôle | Renommer ? |
|-----------|------|------------|
| `MONCINE_ROOT`, `MONCINE_DATA`, `MONCINE_WWW` | Chemins | **Non** |
| `MONCINE_DB_FILE` | Chemin base SQLite (`…/moncine.db`) | **Non** |
| `MONCINE_MEDIA_PATH` | Racine fichiers volumineux | **Non** |
| `MONCINE_APP_NAME` | Nom **affiché** dans l’UI | **Oui** — déjà « Médiathèque » en 0.1.0 |
| `MONCINE_PACKAGE_VERSION` | Version semver du **fork** (0.1.0, 0.2.0…) | **Oui** — suivre CHANGELOG |

### 2.3 Variables d’environnement

Conserver les préfixes **`MONCINE_`** :

- `MONCINE_DATA_PATH`
- `MONCINE_MEDIA_PATH`
- `MONCINE_BASE_URL`
- `MONCINE_MAIL_FROM`
- `MONCINE_TRUST_PROXY`

Ne pas documenter ni lire en priorité des variables `MEDIATHEQUE_*` tant que M7 ne prévoit pas un alias officiel avec tests de déploiement.

### 2.4 Fichiers et dossiers techniques

| Élément | Conserver | Commentaire |
|---------|-----------|-------------|
| `data/moncine.db` | **Oui** | Nom de fichier SQLite |
| `lib/FilmRepository.php` | **Oui** | Façade collection ; gère le domaine `film` via filtres SQL |
| `www/films.php`, `www/film.php` | **Oui** | URLs stables ; libellés UI via `MediaContext::navLabels()` |
| Session `moncine_session` | **Oui** | Clés session quiz, auth, etc. |
| Dossier Composer `moncine/app` → `mediatheque/app` | **OK** | Seul le `composer.json` a été adapté ; le code reste `Moncine\` |

---

## 3. Ce qui identifie le fork multi-médias (à utiliser pour le nouveau code)

### 3.1 Domaine média : `media_domain`

**Colonne SQL :** `oeuvres.media_domain`  
**Valeurs :** `film` | `bd` | `livre` | `jeu` | `magazine`  
**Classe :** `MediaDomain`  
**Session :** `MediaContext::current()`

C’est le **type de média de l’onglet actif** (Films, BD, Livres…).

```php
// ✅ Toute requête liste / catalogue doit filtrer le domaine
CatalogSchema::applyMediaDomainFilter($whereParts, $params);

// ✅ Toute insertion catalogue
$oeuvres->insert([…, 'media_domain' => MediaContext::current()]);
// ou MediaDomain::FILM par défaut
```

### 3.2 Sous-type film : `moncine_kind` (ne pas confondre)

**Colonne SQL :** `oeuvres.moncine_kind`  
**Valeurs :** `film` | `serie` | `spectacle` (dvdthèque uniquement)  
**Classe :** `MoncineContentKind`  
**Filtre UI :** `ContentKindFilter` (onglet Films seulement)

| Concept | Champ / classe | Portée |
|---------|----------------|--------|
| Onglet **Films / BD / Livres…** | `media_domain` / `MediaDomain` | **Toute** l’application |
| Film vs **série** vs spectacle | `moncine_kind` / `MoncineContentKind` | **Domaine `film` uniquement** |

```php
// ❌ Erreur fréquente : utiliser moncine_kind pour l’onglet BD
// ✅ Utiliser media_domain pour séparer BD et films
```

### 3.3 Classes à privilégier pour le multi-médias

| Besoin | Classe / fichier |
|--------|------------------|
| Couleur, libellés onglets | `MediaDomain` |
| Onglet actif (session) | `MediaContext` |
| Pages « bientôt », quiz, redirection onglets | `MediaDomainGuards` |
| Filtre SQL central | `CatalogSchema::applyMediaDomainFilter()` |
| Changer d’onglet | `www/set-media-domain.php` |
| Onglets HTML | `templates/_media_domain_tabs.php` |

---

## 4. Règles pour les développements futurs (M2 → M7)

### 4.1 Ajouter un nouveau type de média (ex. BD)

1. Constante dans `MediaDomain` (déjà `bd`).
2. Migration : champs métier dans une **table fille** (`oeuvre_bd`, …) — pas un gros JSON opaque (décision roadmap).
3. **Toujours** renseigner `oeuvres.media_domain = 'bd'` à l’insertion.
4. Réutiliser `bibliotheque` + `CatalogSchema::JOIN` + filtre domaine (pas une table collection séparée).
5. Libellés UI : `MediaDomain::navLabels()` — pas de chaînes en dur « Mes films » dans les templates communs.
6. Marquer le domaine implémenté : `MediaDomain::isCollectionImplemented()`.
7. Mettre à jour **`sql/schema.sql`** et **[base-de-donnees.md](base-de-donnees.md)** (checklist § Maintenance).

### 4.2 Modifier une requête SQL existante

- Si la requête lit `oeuvres` ou `CatalogSchema::JOIN`, vérifier si le **filtre `media_domain`** est appliqué.
- Exception documentée : **partage visiteur films** force `MediaDomain::FILM` (liens créés pour la dvdthèque).
- Exception : **profil public** — domaine choisi via `?domain=` (`UserPublicProfileService`, films + magazines en **0.3.2**).

### 4.3 Pages et URLs

- Ne pas renommer `films.php` en `collection.php` sans plan de redirection (M7).
- Pages **réservées aux films** : les ajouter à `MediaDomainGuards::FILM_ONLY_PATHS` si elles ne doivent pas exister sur BD/livres/etc.
- Changement d’onglet depuis une page **film-only** ou **magazine-only** → redirection vers la collection du domaine cible (voir `redirectTargetForTabSwitch()`).

### 4.4 Affichage utilisateur vs code

| Contexte | Utiliser |
|----------|----------|
| Titre de page, menu, footer | `MediaContext::navLabels()`, `MONCINE_APP_NAME` |
| Commentaires, noms de classes, migrations | Termes techniques stables (`FilmRepository`, `moncine.db`) |
| CHANGELOG / README version | **Médiathèque** + semver **0.x** |

---

## 5. Anti-patterns (erreurs à ne pas commiter)

| ❌ À éviter | ✅ À faire |
|------------|-----------|
| Renommer `Moncine\` → `Mediatheque\` « pour être cohérent » | Garder `Moncine\` jusqu’à M7 |
| Renommer `MONCINE_DATA_PATH` en doc ou code | Documenter `MONCINE_DATA_PATH` |
| Créer `MediathequeDomain` en doublon de `MediaDomain` | Étendre `MediaDomain` |
| Filtrer les BD avec `moncine_kind` | Filtrer avec `media_domain = 'bd'` |
| Oublier `media_domain` à l’import CSV | Colonne ou défaut `film` / domaine actif |
| Hardcoder « Mes films » dans `layout.php` | `MediaContext::navLabels()['collection']` |
| Renommer `moncine.db` sans script migration serveur | Garder le nom fichier |
| Supprimer `FilmRepository` et tout refaire | Étendre / filtrer la façade existante |

---

## 6. Quand le renommage global sera autorisé (phase M7)

Un renommage `Moncine` → `Mediatheque` (namespace, constantes, base SQLite) ne sera envisagé **qu’avec** :

- [ ] Checklist M1–M6 validée ;
- [ ] Plan de migration déploiement (YunoHost, scripts, doc admin) ;
- [ ] Alias temporaires `MONCINE_*` **et** `MEDIATHEQUE_*` si besoin ;
- [ ] Version **1.0.0** Médiathèque et entrée CHANGELOG dédiée.

Jusque-là, toute PR qui renomme massivement les identifiants techniques doit être **refusée ou reportée**, sauf petits ajouts **documentés** (ex. `MONCINE_APP_NAME` = « Médiathèque »).

---

## 7. Références croisées

| Document | Contenu |
|----------|---------|
| [base-de-donnees.md](base-de-donnees.md) | Structure SQLite, tables, migrations |
| [mediatheque.md](mediatheque.md) | Vue d’ensemble du fork 0.1.0 |
| [ROADMAP.md](../ROADMAP.md) | Phases M0–M7 et décisions actées |
| [CHANGELOG.md](../CHANGELOG.md) | Versions Médiathèque vs Monciné |
| `lib/config.php` | Constantes officielles |
| `lib/MediaDomain.php` | Domaines et thème |
| `lib/CatalogSchema.php` | Filtre SQL `media_domain` |

---

*Dernière mise à jour : 0.1.0 — à relire avant tout refactor de nommage.*
