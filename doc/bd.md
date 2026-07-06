# BD / Manga — module collection (phase M2)

## Organisation (comme les magazines)

1. **Créer une série** (`/ajouter-serie-bd.php`) — ex. « Astérix », « One Piece ».
2. **Ajouter des tomes** à cette série (`/ajouter-tome-bd.php?series_id=…`).

La page **Mes BD** (`/bd.php`) affiche vos **séries**. Cliquez sur une série pour voir ses **tomes** (`/serie-bd.php`).

## Schéma

- **`series`** (`media_domain = 'bd'`) : titre, type (BD/manga/comic dans `tags`), éditeur, couverture.
- **`series_bibliotheque`** : séries suivies (collection ou envies), comme pour les magazines.
- **`oeuvre_bd`** : métadonnées de chaque tome (numéro, auteurs, genre…).
- **`bibliotheque`** : votre exemplaire (`support_physique`).
- **`historique`** : dates « Lu le » et notes.

## Pages principales

| URL | Rôle |
|-----|------|
| `/bd.php` | Liste des séries en collection |
| `/serie-bd.php?series_id=` | Tomes d’une série |
| `/ajouter-serie-bd.php` | Nouvelle série |
| `/modifier-serie-bd.php?series_id=` | Modifier titre, type, éditeur, notes, couverture |
| `/ajouter-tome-bd.php?series_id=` | Nouveau tome |
| `/album-bd.php?id=` | Fiche d’un tome |
| `/oeuvre-bd.php?id=` | Fiche **catalogue** d’un tome (consultable par tout utilisateur connecté depuis **0.7.12**) |
| `/bd-envies.php` | Séries en envies |
| `/utilisateur.php?domain=bd` | Profil public (amis) — séries et tomes ; **clic sur un tome** → fiche catalogue (**0.7.12**) |
| `/partage-bd.php?t=` | Liste partagée (visiteur sans compte) |
| `/partage-serie-bd.php` | Tomes d’une série partagée |
| `/partage-album-bd.php` | Fiche tome partagée |
| `/imprimer-serie-bd.php` | Liste imprimable / PDF d’une série |
| `/gerer-partages.php?domain=bd` | Créer un lien de partage |

## Parcours utilisateur

```
Mes BD → Ajouter une série → (fiche série) → Ajouter un tome → Fiche tome
```

L’ancien lien `/ajouter-bd.php` redirige vers la création de série ou l’ajout de tome si `series_id` est fourni.

## Classes PHP

- `BdRepository` — séries, tomes, catalogue
- `BdSeriesMetadata` — type BD/manga/comic sur la série
- `BdRowMapper`, `BdKind`, `BdPhysicalSupport`

## Couverture d’un tome

À l’**ajout** ou à la **modification** d’un tome :

- **Fichier** : JPEG, PNG ou WebP (taille max. configurée sur le serveur).
- **URL HTTPS** : comme pour les jeux vidéo, collez un lien direct vers l’image ; Médiathèque la **télécharge** et l’enregistre localement sous `/posters/{oeuvre_id}.ext` (`BdRepository::savePoster()` → `PosterStorage::ensureLocalForOeuvre()`).

Le fichier uploadé est prioritaire si les deux sont fournis.

## Numérotation et hors-série

Comme pour les **magazines**, deux champs distincts :

- **Numéro de tome** (`tome_numero`) — numéro affiché (0, 1, 2, 38…). Le **tome 0** est accepté (préquel, hors chronologie). Pour un album **sans numéro**, utilisez plutôt un **libellé alternatif** (intégrale, HS…).
- **Ordre de tri** (`tome_ordre`) — nombre décimal pour classer la liste (ex. `38`, `38.5`, `38.1`).

**Hors-série** : case à cocher + badge **HS** sur les cartes. À la **création**, si l’ordre est un entier (ex. `38`) et la case est cochée, l’ordre devient **38.5** (entre 38 et 39). Utile pour les albums dérivés (films Astérix, intégrales, etc.).

Sur la fiche série (`/serie-bd.php`), filtre **Afficher : Hors-série** (collection uniquement).

## Limites v0.8.x

- Pas d’import CSV ni d’API externe.
- Profil public, partage visiteur et liste imprimable par série : disponibles (comme magazines / jeux).
- **Couverture de série** : sans image dédiée, l’application affiche automatiquement la couverture du **tome 1** (hors-série exclus). Vous pouvez aussi téléverser une couverture propre à la série via **Modifier la série**.
- **Maintenance catalogue** : les logos de série (`/posters/s{id}.jpg`) ne sont plus traités comme des affiches orphelines.
