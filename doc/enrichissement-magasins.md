# Liens magasins catalogue (GOG + Epic)

**Statut :** ✅ **Saisie manuelle uniquement** — migration `063`, panneau admin sur `/oeuvre-jeu.php`.  
**Retiré (0.7.19+)** : enrichissement automatique GOG/Epic (API publiques peu fiables).  
**Dernière mise à jour :** 2026-07-11

> Les URLs GOG / Epic / Steam se renseignent **à la main** sur la fiche catalogue jeu (admin).  
> Ce document conserve l’historique de la spec d’enrichissement automatique (non maintenu).

---

## Objectif

Enrichir les fiches catalogue (`oeuvres` + `oeuvre_jeu`) avec des **URLs de magasin** GOG et Epic, en recherchant par **titre**, avec :

- score de confiance du rapprochement ;
- validation manuelle pour les matchs douteux ;
- stockage dans `oeuvre_store_links` (catalogue) — **sans** écrire dans `digital_stores` (possession utilisateur, **0.7.14**) ;
- icônes **cliquables** sur les fiches et listes (`GameEditionIcons`).

### Ce que cette feature fait

| Oui | Non |
|-----|-----|
| Recherche publique GOG / Epic par titre | Connexion compte GOG ou Epic |
| Lien `https://www.gog.com/game/{slug}` | Import bibliothèque utilisateur |
| Lien `https://store.epicgames.com/p/{slug}` | Temps de jeu |
| Override manuel admin | Achat / panier |
| Extension future Steam / itch.io | |

### Différence avec les autres docs

| Document | Rôle |
|----------|------|
| **Ce fichier** | Liens **catalogue** via API publique + matching titre |
| [import-steam.md](import-steam.md) | Bibliothèque **Steam** utilisateur + playtime + AppID |
| [import-gog.md](import-gog.md) | Bibliothèque **GOG** utilisateur (OAuth) + `gog_product_id` |

Les trois convergent dans `digital_stores` et les icônes `GameEditionIcons`.

---

## État actuel du projet (à réutiliser)

| Besoin | Existant |
|--------|----------|
| Stockage magasins | `oeuvre_jeu.digital_stores` (JSON `[{store, url}]`) |
| Fusion multi-magasins | `GameDigitalStore::mergeStore()`, `GameRepository::mergeDigitalStoreForOeuvre()` |
| Affichage icônes + liens | `GameEditionIcons`, `templates/_game_edition_icons.php` |
| Repli URL Steam sans URL | `GameEditionIcons::linkUrlForKey()` + `steam_appid` |
| Matching titre | `SearchMatch`, `SteamTitleMatch` |
| Enrichissement par lot (modèle) | `GameEnricher`, `GameCatalogEnrichment`, page `/import.php` |
| Magasins supportés | `steam`, `gog`, `epic`, `battlenet` dans `GameDigitalStore` |

**Manque aujourd’hui :** clients HTTP GOG/Epic catalogue, table de métadonnées de match, workflow admin, repli URL GOG/Epic dans `linkUrlForKey()`.

---

## API publiques (sans authentification)

### GOG — recherche catalogue

```
GET https://catalog.gog.com/v1/catalog?query={query}&limit=10&locale=en-US
```

(`productSearch` est obsolète — GOG l’ignore et renvoie un catalogue générique. Éviter les « : » dans la requête ; le client tronque le sous-titre automatiquement.)
```

(Repli éventuel sur l’ancienne `https://api.gog.com/products?search=` si indisponible.)

- Pas d’en-tête `Authorization`.
- Réponse JSON : produits avec `title`, `slug`, `id`, métadonnées.
- URL magasin : `https://www.gog.com/game/{slug}`
- Images souvent en `//images-...` → préfixer `https:`

**Attention :** API non officielle, peut changer. Parser défensivement ; timeout court (15–25 s).

Référence : [GOG API Documentation (communauté)](https://gogapidocs.readthedocs.io/en/latest/)

### Epic Games Store — GraphQL

```
POST https://store.epicgames.com/graphql
Content-Type: application/json
```

Opération typique : `searchStoreQuery` avec variable `keyword` (voir crate Rust `egs-api-rs` ou requêtes capturées pour la structure exacte du body).

- URL magasin : `https://store.epicgames.com/p/{slug}`
- Pas d’auth pour la recherche catalogue.

**Attention :** schéma GraphQL non contractuel ; isoler dans `EpicCatalogClient`.

---

## Schéma base de données

Migration **`063_oeuvre_store_links.sql`** (à créer) :

```sql
-- Métadonnées d'enrichissement magasin (workflow admin).
-- L'affichage utilisateur reste sur oeuvre_jeu.digital_stores.

CREATE TABLE IF NOT EXISTS oeuvre_store_links (
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    store TEXT NOT NULL,                    -- 'gog', 'epic', extensible
    store_slug TEXT NOT NULL DEFAULT '',
    store_url TEXT NOT NULL DEFAULT '',
    store_title TEXT NOT NULL DEFAULT '',   -- titre retourné par l'API magasin
    match_confidence REAL,                  -- 0.0 à 1.0, NULL si saisie manuelle pure
    manually_verified INTEGER NOT NULL DEFAULT 0,
    last_verified_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (oeuvre_id, store)
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_store_links_unverified
    ON oeuvre_store_links(store, manually_verified)
    WHERE manually_verified = 0;
```

### Pourquoi une table en plus de `digital_stores` ?

| `oeuvre_store_links` | `digital_stores` |
|----------------------|------------------|
| Score, slug, titre API, vérification | Ce que l’UI lit pour les icônes |
| Liste « à revoir » admin | Filtres Mes jeux (`GameListFilter`) |
| Historique d’enrichissement | Déjà en production |

**Règle (depuis 0.7.14) :** les **URLs catalogue** sont dans `oeuvre_store_links` ; `digital_stores` indique seulement si l’utilisateur **possède** le jeu sur un magasin (cases bibliothèque, sans URL). L’affichage public « Disponible sur » et les liens des icônes utilisent `oeuvre_store_links` (hydratation `catalog_store_urls` dans `GameRowMapper`).

Mettre à jour `lib/GameSchema.php` : `oeuvreStoreLinksTableExists()`.

---

## Algorithme de matching

Réutiliser `SearchMatch` et s’inspirer de `SteamTitleMatch` (`lib/SteamTitleMatch.php`).

### 1. Normalisation du titre catalogue

Classe **`StoreTitleNormalizer`** :

1. Titre depuis `oeuvres.titre` (+ `titre_original`, `alternative_names` si utile).
2. `SearchMatch::fold()` (minuscules, sans accents).
3. Retirer `™`, `®`, `©`.
4. Variantes sans suffixe après ` - `, `: `, ` — `.
5. Retirer mots d’édition faibles : `goty`, `deluxe`, `complete`, `remastered`, `definitive`, `edition`, `ultimate` (liste configurable).
6. Produire **2 à 4 requêtes** de recherche (titre complet, racine, sans édition).

### 2. Recherche et scoring

Classe **`StoreLinkMatcher`** :

Pour chaque candidat API :

1. **Éliminatoire :** `SearchMatch::matches($candidatTitre, $titreCatalogue, 1)` doit passer.
2. **Score brut** à partir de `SearchMatch::score()` (plus bas = mieux → convertir en 0..1).
3. **Bonus** : égalité exacte titre plié (+0.2), même année catalogue (+0.1).
4. **Malus** : édition différente détectée (GOTY vs Standard) (−0.2).
5. Garder le **meilleur** candidat par magasin.

### 3. Seuils d’action

| `match_confidence` | Comportement |
|--------------------|--------------|
| ≥ **0.85** | `manually_verified = 1`, sync `digital_stores`, ajouter entrée `store` si absente |
| **0.60 – 0.84** | Enregistrer dans `oeuvre_store_links`, **ne pas** sync UI ; file « à revoir » |
| < **0.60** | Ne rien enregistrer ; log optionnel |

Ne **jamais** écraser un lien `manually_verified = 1` lors d’un batch automatique.

### Exemples

| Titre catalogue | Candidat GOG | Confiance | Action |
|-----------------|--------------|-----------|--------|
| The Witcher 3: Wild Hunt | The Witcher 3: Wild Hunt | ~0.95 | Auto |
| Witcher 3 | The Witcher 3: Wild Hunt | ~0.75 | Revue admin |
| Cyberpunk 2077 | Cyberpunk 2077 Ultimate | ~0.70 | Revue (édition) |
| Jeu inconnu | (aucun) | — | Ignorer |
| Hollow Knight | Hollow Knight: Silksong | ~0.55 | Rejeter |

---

## Architecture code (fichiers à créer)

```
lib/
  GogCatalogClient.php           # GET products?search=
  EpicCatalogClient.php          # POST graphql searchStoreQuery
  StoreTitleNormalizer.php       # variantes de titre
  StoreLinkMatcher.php           # score 0..1
  OeuvreStoreLinkRepository.php  # CRUD oeuvre_store_links
  StoreLinkEnricher.php          # orchestration (1 œuvre ou lot)

www/
  enrichir-liens-magasins.php        # POST batch (admin)
  valider-lien-magasin.php           # POST valider / ignorer / URL manuelle

templates/
  _store_link_review_panel.php       # liste liens à revoir (maintenance)
  maintenance-catalogue.php          # section à ajouter

tests/Unit/
  StoreTitleNormalizerTest.php
  StoreLinkMatcherTest.php
  GogCatalogClientTest.php           # fixtures JSON
  EpicCatalogClientTest.php
  StoreLinkEnricherTest.php
```

### `GogCatalogClient` (esquisse)

```php
final class GogCatalogClient
{
    private const SEARCH_URL = 'https://api.gog.com/products';
    private const HTTP_TIMEOUT = 20;

    /** @return list<array{product_id: int, title: string, slug: string}> */
    public function search(string $query, int $limit = 10): array;

    public static function storeUrl(string $slug): string;
}
```

### `EpicCatalogClient` (esquisse)

```php
final class EpicCatalogClient
{
    /** @return list<array{title: string, slug: string}> */
    public function search(string $keyword, int $limit = 10): array;

    public static function storeUrl(string $slug): string;
}
```

### `StoreLinkEnricher` (cœur métier)

```php
final class StoreLinkEnricher
{
    /**
     * @param list<string> $stores ex. ['gog', 'epic']
     * @return array{gog: ?array, epic: ?array, errors: list<string>}
     */
    public function enrichOeuvre(int $oeuvreId, array $stores, bool $force = false): array;

    /**
     * @return array{processed: int, linked: int, pending_review: int, skipped: int, errors: list<string>}
     */
    public function enrichBatch(int $limit, array $stores, bool $onlyMissing = true): array;
}
```

**Flux `enrichOeuvre` :**

1. Charger œuvre + `oeuvre_jeu`.
2. Pour chaque magasin : si lien vérifié et `!$force` → skip.
3. `StoreTitleNormalizer` → requêtes API.
4. `StoreLinkMatcher` → meilleur candidat + confidence.
5. `OeuvreStoreLinkRepository::upsert(...)`.
6. Si confidence ≥ seuil → `GameRepository::mergeDigitalStoreForOeuvre($oeuvreId, $store, $url)` et ajouter `store` dans `digital_stores` si l’icône doit apparaître sans posséder le jeu (voir § Affichage).
7. Capturer exceptions → `errors[]`, ne pas faire planter la page.

**Batch :** comme `GameEnricher::enrichBatch()` — `usleep(300_000)` entre œuvres (rate limit).

---

## Affichage : icônes et liens cliquables

### Déjà en place

`templates/_game_edition_icons.php` enveloppe l’icône dans un `<a>` si `GameEditionIcons::linkUrlForKey()` retourne une URL.

Les icônes n’apparaissent que si le magasin est listé dans `digital_stores` (`GameEditionIcons::iconKeys()`).

### Après enrichissement auto (confidence haute)

Lors de la sync, s’assurer que `digital_stores` contient :

```json
[{"store": "gog", "url": "https://www.gog.com/game/cyberpunk_2077"}]
```

Sans entrée `store: gog`, l’icône GOG ne s’affiche pas — même avec une URL en base dans `oeuvre_store_links`.

**Choix produit v1 :** l’enrichissement ajoute à la fois l’**URL** et l’entrée **magasin** dans `digital_stores` (le jeu est « disponible sur ce magasin », pas « possédé par l’utilisateur »). C’est cohérent avec un catalogue de référence.

### Extension `GameEditionIcons::linkUrlForKey()`

Comme pour Steam (`steam_appid`), ajouter un repli GOG/Epic :

1. URL dans `digital_stores` (priorité).
2. Sinon `oeuvre_store_links.store_url` si vérifié (jointure ou champ hydraté dans `GameRowMapper`).
3. Sinon construction depuis `store_slug` : `GogCatalogClient::storeUrl($slug)` / `EpicCatalogClient::storeUrl($slug)`.

Tests : étendre `tests/Unit/GameEditionIconsLinkTest.php`.

---

## Interface admin (parcours)

### 1. Page Importer ou Maintenance catalogue

Section **« Enrichir les liens magasins »** (admin uniquement, `CatalogAdmin::canAccess()`) :

- Cases à cocher : GOG, Epic.
- Option : « Uniquement les fiches sans lien vérifié ».
- Bouton **Lancer** → POST `enrichir-liens-magasins.php`.
- Rapport : X traités, Y liés, Z à revoir, erreurs.

### 2. File de relecture

Sur `/maintenance-catalogue.php` ou panneau dédié :

| Colonne | Contenu |
|---------|---------|
| Titre catalogue | `oeuvres.titre` |
| Magasin | GOG / Epic |
| Titre API | `store_title` |
| Confiance | `match_confidence` |
| URL proposée | lien |
| Actions | **Valider**, **Ignorer**, **Saisir URL** |

Validation → `manually_verified = 1`, sync `digital_stores`, `last_verified_at = now`.

### 3. Fiche catalogue `/oeuvre-jeu.php`

Bloc optionnel (admin) : liens magasins connus + bouton **Rechercher GOG/Epic** pour une seule fiche.

### 4. Saisie manuelle

Formulaire : URL ou slug + magasin → `manually_verified = 1`, `match_confidence = NULL`.

---

## Sécurité et robustesse

- Pas de token utilisateur ; seules les credentials admin ne sont pas nécessaires (API publiques).
- Valider les URLs avec `SecureUrl::sanitizePosterUrl()` (déjà utilisé par `GameDigitalStore`).
- CSRF sur tous les POST (`Csrf::rejectUnlessValid`).
- Logger les erreurs HTTP dans un canal fichier, pas en flash utilisateur détaillé.
- Timeout HTTP 15–25 s ; pas de retry agressif.
- Feature désactivable si `GameSchema::oeuvreStoreLinksTableExists()` est false.

---

## Ordre d’implémentation

| Phase | Tâche | Livrable testable |
|-------|--------|-------------------|
| **1** | Migration `063` + `GameSchema` | Tables OK |
| **2** | `GogCatalogClient` + tests fixture | Recherche GOG en CLI/test |
| **3** | `EpicCatalogClient` + tests fixture | Recherche Epic en test |
| **4** | `StoreTitleNormalizer` + `StoreLinkMatcher` + tests | Scores sur exemples connus |
| **5** | `OeuvreStoreLinkRepository` + `StoreLinkEnricher` | Enrichir 1 œuvre en PHP |
| **6** | `enrichir-liens-magasins.php` + section import/maintenance | Batch admin |
| **7** | UI relecture + validation manuelle | Valider un match douteux |
| **8** | Sync `digital_stores` + `GameEditionIcons` repli | Icône cliquable sur fiche |
| **9** | Doc `jeux.md`, `base-de-donnees.md`, CHANGELOG | Release |

Estimation : **5 à 7 jours** de développement.

---

## Top 3 risques

| Risque | Mitigation |
|--------|------------|
| API GOG/Epic instable | Clients isolés, fixtures de test, pas de crash si JSON invalide |
| Faux positifs (mauvaise édition) | Seuil 0.85, relecture admin, ne pas écraser les liens vérifiés |
| Désync `oeuvre_store_links` / `digital_stores` | Une seule voie vers l’UI : sync explicite après validation |

---

## Critères d’acceptation (tests manuels)

1. Admin lance un batch sur 10 jeux PC → au moins quelques liens GOG/Epic proposés.
2. Match confiance haute → icône magasin cliquable sur `/oeuvre-jeu.php` et `/jeu.php`.
3. Match moyen → visible dans la file admin, pas sur la fiche tant que non validé.
4. Validation manuelle → icône + bon URL.
5. Jeu déjà avec URL Steam → ajout GOG **en plus**, pas d’écrasement Steam.
6. API GOG down → message d’erreur, reste de l’app OK.
7. Saisie manuelle d’URL → lien immédiat, `manually_verified = 1`.

---

## Évolutions ultérieures

- Magasins : Steam (recherche publique), itch.io, Humble.
- Vérification périodique des liens (`last_verified_at`, HEAD HTTP).
- Champ catalogue `gog_product_id` (synergie avec [import-gog.md](import-gog.md)).
- File d’attente asynchrone si le catalogue dépasse quelques centaines de jeux.

---

## Liens

- [doc/jeux.md](jeux.md) — `digital_stores`, `GameEditionIcons`
- [doc/import-steam.md](import-steam.md) — import bibliothèque Steam
- [doc/import-gog.md](import-gog.md) — import bibliothèque GOG (OAuth)
- [doc/conventions-techniques.md](conventions-techniques.md) — nommage PHP / SQL
- [doc/base-de-donnees.md](base-de-donnees.md) — migrations (ajouter 063 à l’implémentation)
