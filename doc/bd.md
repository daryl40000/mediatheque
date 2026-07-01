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
| `/ajouter-tome-bd.php?series_id=` | Nouveau tome |
| `/album-bd.php?id=` | Fiche d’un tome |
| `/bd-envies.php` | Séries en envies |
| `/utilisateur.php?domain=bd` | Profil public (amis) — séries et tomes |
| `/utilisateur-serie-bd.php` | Tomes d’une série sur un profil ami |
| `/utilisateur-album-bd.php` | Fiche tome sur un profil ami |
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

## Limites v0.8.x

- Pas d’import CSV ni d’API externe.
- Profil public, partage visiteur et liste imprimable par série : disponibles (comme magazines / jeux).
- Modification de série limitée (pas encore de page dédiée).
