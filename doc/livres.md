# Livres (phase M3)

**Version :** **0.8.1**  
Module de gestion des **livres** papier ou numériques, sur le même parcours que Films / Jeux / BD / Magazines.

## Fonctionnalités

- Collection (`/livres.php`) et envies (`/livres-envies.php`)
- Fiche livre (`/livre.php`) : ajout / modification / suppression
- Modes d’affichage collection : **Liste**, **Vignettes**, **Bibliothèque** (choix **mémorisé** dans le navigateur pour la prochaine visite)
- **Sous-titre**, **saga** (comme films / jeux) avec numéro d’ordre
- **Couverture** + **4e de couverture** (clic pour agrandir)
- **Catégories** (comme les magazines) : Jeux vidéo, Cinéma, Figurines, Divers…
- Si la catégorie **Jeux vidéo** est cochée : liens vers les jeux du catalogue
- Sur la fiche d’un jeu : bouton **Livres** (à côté de Magazines) quand des livres sont reliés
- Page **Sagas livres** (`/sagas-livres.php`)
- Actions fiche (comme BD / films) : **ressenti**, **modifier**, **marquer comme lu** (+ date + historique)
- Bandeau **saga** sur la fiche : volumes voisins, tome courant encadré (comme les BD)
- **Statistiques livres** (`/statistiques.php` en onglet Livres) : lectures, ressentis, catégories, supports

## Pages principales

| URL | Rôle |
|-----|------|
| `/livres.php` | Mes livres (collection) |
| `/livres-envies.php` | Mes envies |
| `/livre.php?id=` | Fiche d’un exemplaire |
| `/ajouter-livre.php` | Nouveau livre |
| `/modifier-livre.php?id=` | Modifier un livre |
| `/oeuvre-livre.php?id=` | Fiche catalogue |
| `/sagas-livres.php` | Liste / détail des sagas |
| `/jeu-livres.php?id=` | Livres liés à un jeu |
| `/statistiques.php` | Stats du domaine Livres (onglet actif) |
| `/marquer-livre-lu.php` | Enregistrer une lecture (POST) |
| `/marquer-livre-ressenti.php` | Enregistrer un ressenti (POST) |

## Base de données

- `oeuvre_livre` : auteur, ISBN, pages, éditeur, catégories, langue, collection, sous-titre, 4e de couverture
- `oeuvres.saga` / `oeuvres.saga_ordre` : sagas (comme les films)
- `livre_game_link` : liens livre catalogue ↔ jeu catalogue
- `historique` : dates de lecture et ressentis (même table que films / BD)

Migrations : `068_oeuvre_livre.sql`, `069_oeuvre_livre_sous_titre_back_cover.sql`

## Classes principales

| Classe | Rôle |
|--------|------|
| `LivreRepository` | CRUD collection / envies, sagas, couvertures |
| `LivreCategory` | Catégories (normalisation, filtre) |
| `LivreGameLink` | Liens livre ↔ jeux |
| `LivreSagaContext` | Bandeau volumes voisins sur la fiche |
| `LivreCollectionStats` | Tableau de bord statistiques |
| `LivreUrls` / `View` | URLs fiches, listes, sagas |

## Parcours utilisateur (résumé)

1. Onglet **Livres** → Mes livres / Mes envies / Sagas / Statistiques
2. Ajouter un livre → collection ou envies
3. Renseigner titre, sous-titre, auteur, saga, catégories, couvertures…
4. Si « Jeux vidéo » : rechercher et lier les jeux concernés
5. Sur la fiche : noter un ressenti, marquer comme lu, voir les autres tomes de la saga
6. Sur la fiche d’un jeu lié : bouton **Livres** pour retrouver ces ouvrages

## Limites (0.8.1)

- Pas encore d’import CSV catalogue livres
- Pas de partage visiteur dédié ni de listes imprimables livres
- PDF ebook éventuels : sous-dossier stockage réservé, pas d’UI dédiée

*Dernière mise à jour : **0.8.1** — 2026-07-30.*
