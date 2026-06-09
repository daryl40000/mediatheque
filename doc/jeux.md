# Jeux vidéo (phase M4)

Documentation du module **Jeux** dans la médiathèque Monciné.

**Version : 0.5.0** · **Date : 2026-06-10**

## Objectif

Gérer une **collection de jeux vidéo** (physiques ou dématérialisés) avec le même principe que les films et magazines :

- un **catalogue partagé** (`oeuvres` + `oeuvre_jeu`) ;
- une **bibliothèque personnelle** (`bibliotheque`) : collection du foyer ou envies individuelles ;
- un **pont avec les magazines** : relier un sujet test/preview/interview à une fiche jeu.

## Schéma base de données

### Table `oeuvre_jeu`

| Colonne | Rôle |
|---------|------|
| `oeuvre_id` | Clé vers `oeuvres.id` (jeu catalogue) |
| `studio` | Développeur |
| `editeur` | Éditeur |
| `genre` | Genres (Action-RPG, FPS…) — liste séparée par virgules |
| `platform` | Plateforme principale (`pc`, `ps5`, `switch`…) |
| `is_digital` | 1 = version démat, 0 = physique |
| `physical_supports` | Supports physiques possédés (CD/DVD, disquette…) |
| `digital_stores` | Magasins démat (Steam, GOG, Epic…) + URLs |

Migrations :

- `sql/migrations/039_oeuvre_jeu_magazine_link.sql` — table + pont magazine ;
- `sql/migrations/040_oeuvre_jeu_editions.sql` — exemplaires ;
- `sql/migrations/041_bibliotheque_tested_on_linux.sql` — flag Linux par exemplaire bibliothèque.

### Lien magazine → jeu

Colonne **`magazine_subject.catalog_oeuvre_id`** (nullable) :

- pointe vers `oeuvres.id` où `media_domain = 'jeu'` ;
- utilisée pour les sujets **Test**, **Preview**, **Interview** ;
- la saisie libre du sujet reste possible (données existantes conservées).

## Pages web

| URL | Rôle |
|-----|------|
| `/` (onglet Jeux) | Accueil jeux — activité récente, raccourcis |
| `/jeux.php` | Liste « Mes jeux » (tri colonnes, recherche) |
| `/jeux-envies.php` | Liste des envies jeux |
| `/jeu.php?id=` | Fiche jeu (+ section « Dans vos magazines ») |
| `/ajouter-jeu.php` | Formulaire d’ajout (collection ou envie) |
| `/modifier-jeu.php?id=` | Modification fiche catalogue (admin) |
| `/rechercher-jeux-catalogue.php` | API JSON autocomplétion catalogue |
| `/marquer-joue.php` | Enregistrer une note sur 10 |
| `/supprimer-jeu.php` | Retirer un jeu de la collection ou des envies |
| `/promouvoir-jeu-collection.php` | Passer une envie en collection (« J’ai acheté ») |
| `/statistiques.php` | Statistiques jeux (onglet Jeux actif) |

## Classes PHP

| Classe | Rôle |
|--------|------|
| `GameRepository` | CRUD collection, recherche catalogue, jaquettes, flag Linux |
| `GameGenre` | Genres réutilisables (tags, comme magazines) |
| `GameCollectionStats` | Statistiques collection jeux |
| `GamePlatform` | Liste et normalisation des plateformes |
| `GamePhysicalSupport` | Supports physiques (CD/DVD, disquette) |
| `GameDigitalStore` | Magasins démat PC et stores console |
| `MagazineGameLink` | Validation et gestion du pont sujet ↔ jeu |

## Jaquettes

Comme pour les **films** :

- **Fichier** (JPEG, PNG, WebP) : enregistré dans `MONCINE_DATA/posters/` ;
- **URL HTTPS** : téléchargée automatiquement puis stockée en local ;
- affichage en liste (vignette) et sur la fiche.

Méthode : `GameRepository::savePoster()` → `PosterStorage::ensureLocalForOeuvre()`.

## Exemplaires (physique / démat)

| Type | Saisie |
|------|--------|
| Physique | CD/DVD, Disquette (plusieurs possibles) |
| Démat PC | Steam, GOG, Epic — plusieurs magasins, lien HTTPS optionnel par magasin |
| Démat console | Store imposé (PSN, Xbox, eShop) — **sans** lien personnalisé |

Le panneau démat s’adapte à la plateforme choisie (PC vs console) via JavaScript (`initGameEditionFields`).

## Notes et bibliothèque

Comme pour les **films** (table `historique`, réutilisée avec l’id bibliothèque) :

- **Note sur 10** : note personnelle + moyenne du foyer (sans date de session visible) ;
- **Date d’ajout** : `bibliotheque.created_at` (collection ou envie) ;
- **Envie → collection** : bouton « J’ai acheté » sur la fiche ou la liste des envies ;
- **Suppression** : icône poubelle en bas de fiche — retire l’entrée bibliothèque et la note.

## Jeux PC testés sous Linux

- Case **« Testé sur Linux »** dans le formulaire d’ajout/modification (visible si plateforme = PC) ;
- Colonne `bibliotheque.tested_on_linux` ;
- **Badge pingouin Tux** (fond bleu ciel) sur la fiche jeu et dans les listes (Mes jeux, Mes envies).

## Accueil (onglet Jeux)

Quand l’onglet **Jeux** est actif, la page d’accueil (`home-jeu.php`) affiche :

- nombre de jeux en collection ;
- 5 derniers jeux notés ;
- 5 derniers ajouts collection et envies (vignettes cliquables) ;
- raccourcis vers Mes jeux, envies et ajout.

## Synergie magazines

**En place (0.5.0) :**

- schéma `catalog_oeuvre_id` sur `magazine_subject` ;
- API `/rechercher-jeux-catalogue.php` ;
- **autocomplétion catalogue jeux** à l’ajout d’un sujet test / preview / interview sur un numéro magazine ;
- lien sujet → fiche jeu ; affichage sur fiche sujet, numéro et section « Dans vos magazines » sur la fiche jeu ;
- `MagazineGameLink::setSubjectCatalogLink()` pour rattacher un sujet.

**Prochaine étape (M5+) :**

- rattachement rétroactif des sujets existants ;
- recherche globale incluant le titre catalogue jeu.

## Liste et tri

Colonnes triables sur `/jeux.php` : titre, année, studio, genre, support, note, date d’ajout.

Recherche texte : titre, studio, genre.

## Statistiques

Page `/statistiques.php` (onglet Jeux) : répartition par plateforme, physique/démat, genres, décennies, sujets magazine reliés.

## Priorité produit

La phase **M4 (Jeux)** est prioritaire sur **M2 (BD)** et **M3 (Livres)** car elle prépare le pont avec les magazines déjà en production (PC Jeux, Joystick…).

**MVP livré en 0.5.0** — voir [CHANGELOG.md](../CHANGELOG.md) et [ROADMAP.md](../ROADMAP.md) § M4.
