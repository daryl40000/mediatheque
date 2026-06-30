# Partage visiteur (liens lecture seule)

**Version : 0.7.0** · **Date : 2026-06-16** : une URL secrète permettant à un **visiteur non connecté** de consulter une collection ou une liste d’envies, **sans modifier** les données.

---

## 1. Principe

| Élément | Détail |
|---------|--------|
| Création | Utilisateur connecté → `/gerer-partages.php` (films ou jeux, collection ou envies) |
| Token | Chaîne aléatoire dans l’URL (`?t=…`) ; hash SHA-256 en base (`share_links`) |
| Périmètre | **Collection** = tout le foyer du lien ; **Envies** = titres en wishlist de l’utilisateur créateur |
| Sécurité | Pas de session requise ; rate limiting sur résolution du token ; expiration optionnelle ; révocation |

Classes principales : `ShareLinkService`, `ShareLinkRepository`, `ShareLinkFilmRepository`, `ShareLinkGameRepository`.

---

## 2. Pages publiques

| Média | Liste | Fiche détail |
|-------|-------|--------------|
| Films | `/partage.php?t=…` | `/partage-film.php?t=…&id=…` |
| Jeux | `/partage-jeux.php?t=…` | `/partage-jeu.php?t=…&id=…` |

Les jaquettes/affiches passent par `/poster.php` (accès autorisé pour les œuvres visibles via un lien actif — voir **0.6.7**).

---

## 3. Recherche et filtres (0.7.0)

### Films (`/partage.php`)

| Fonction | Paramètre URL | Interface |
|----------|---------------|-----------|
| Texte (titre, réalisateur, acteurs, styles, saga…) | `q` | Champ « Rechercher » |
| Type de contenu | `kind` | Filtres film / série / spectacle… |
| Tri colonnes | `sort`, `dir` | En-têtes de tableau |
| Mode d’affichage | `view` | Liste / vignettes / bibliothèque |

**Colonnes liste (collection partagée, pas envies)** depuis **0.7.0** :

- **Note** — meilleure note du propriétaire du lien + moyenne du foyer (`ShareLinkFilmRepository` + `historique`)
- **Dernière vue** — date de dernière vision du propriétaire

Données : sous-requêtes `note_max` et `derniere_vue` sur `historique` (même logique que `CatalogFilmRepository::collectionRatingSelectSql()`).

### Jeux (`/partage-jeux.php`)

| Fonction | Paramètre URL | Interface |
|----------|---------------|-----------|
| Texte (titre, studio, genre, acronymes IGDB…) | `q` | Champ « Rechercher » |
| Plateforme | `platform` | Liste déroulante |
| Type de support | `support` | `physical` ou `digital` |
| Magasin démat. | `store` | `steam`, `epic`, `gog`, `psn`, `xbox`, `eshop` |
| Tri colonnes | `sort`, `dir` | En-têtes (dont **note**, **finished_at** / Fini le) |
| Mode d’affichage | `view` | Liste / vignettes / bibliothèque |

Les filtres jeux réutilisent **`GameListFilter`** et `GameListFilter::applyToSql()` dans `ShareLinkGameRepository::findAllForLink()` — même moteur que `/jeux.php`.

Filtres **statistiques** (genre, décennie, extensions, `platform_kind`…) : pas de menus sur la page partagée, mais conservés en champs cachés si l’URL les contient déjà.

Template filtres partagé avec Mes jeux : `templates/_games_collection_search_filters.php`.

### Filtre magasin démat. (correctif SQL)

Le filtre `store` utilise `json_each` + `json_extract` sur `oeuvre_jeu.digital_stores` (`GameDigitalStore::sqlStoredJsonContains()`), avec repli pour jeux console démat sans JSON explicite (`sqlImplicitConsoleStoreMatch()`). Remplace l’ancien `LIKE '%"store":"steam"%'` peu fiable.

---

## 4. Colonnes liste jeux partagée (0.7.0)

En plus des colonnes existantes (jaquette, titre, plateforme, année, studio, genres, support) :

| Colonne | Tri (`sort`) | Source |
|---------|--------------|--------|
| Note | `note` | `note_max` + `note_foyer_moy` (`_film_ratings.php`) |
| Fini le | `finished_at` | `GameCompletionRepository` → `finished_at_label` |

Les données étaient déjà chargées par `ShareLinkGameRepository::selectGameHistoryExtras()` ; l’affichage manquait dans `_partage_games_list.php`.

---

## 5. Conservation du contexte (navigation)

Lorsqu’un visiteur ouvre une fiche puis revient à la liste, les paramètres de recherche/tri/filtres sont conservés dans l’URL.

Méthode : `ShareLinkService::collectionQueryParams()` accepte un `GameListFilter` optionnel (jeux) et fusionne `toQueryParams()` dans les liens de tri, vignettes et fiches.

Exemple de retour liste depuis une fiche jeu :

```text
/partage-jeux.php?t=TOKEN&q=zelda&platform=switch&support=physical&sort=titre
```

---

## 6. Interface Mes jeux (utilisateur connecté, 0.7.0)

Sur `/jeux.php`, la barre de recherche et les listes déroulantes sont sur **une seule ligne** (PC) :

- Classe CSS : `collection-search--filters` + `collection-search__toolbar`
- Le filtre **type de plateforme** (`platform_kind`) a été retiré des menus (toujours utilisable via liens statistiques)
- Filtre **type de support** : physique uniquement / dématérialisé uniquement (`GameListFilter::supportChoices()`)

Voir [jeux.md](jeux.md) § Recherche et filtres.

---

## 7. Fichiers concernés

| Fichier | Rôle |
|---------|------|
| `www/partage.php` / `www/partage-jeux.php` | Contrôleurs listes partagées |
| `www/partage-film.php` / `www/partage-jeu.php` | Fiches détail |
| `templates/partage.php` / `templates/partage-jeux.php` | Formulaires recherche + vues |
| `templates/_partage_collection_list.php` | Tableau films visiteur |
| `templates/_partage_games_list.php` | Tableau jeux visiteur |
| `templates/_games_collection_search_filters.php` | Menus plateforme / support / magasin |
| `lib/ShareLinkService.php` | URLs, query params, tri |
| `lib/ShareLinkFilmRepository.php` | SQL films + historique notes/vues |
| `lib/ShareLinkGameRepository.php` | SQL jeux + filtres + notes/fins |
| `lib/GameListFilter.php` | Filtres liste jeux (connecté et partagé) |
| `lib/GameDigitalStore.php` | Filtre magasin JSON |
| `www/assets/css/style.css` | Barre recherche unifiée |

---

## 8. Tests

| Test | Fichier |
|------|---------|
| Filtres physique / Steam sur lien partagé | `ShareFeaturesTest::testGameShareLinkAppliesListFilters` |
| Filtre magasin + support Mes jeux | `GameRepositoryTest::testListFilterByDigitalStoreAndPlatformKind`, `testListFilterByPhysicalAndDigitalSupport` |

---

*Dernière mise à jour : **0.7.0** (2026-06-16) — parité recherche partage jeux, colonnes notes/vues, correctif filtre Steam/Epic.*
