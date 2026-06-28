# Pont magazine ↔ jeu vidéo

Relier **optionnellement** un sujet magazine (test, preview, interview) à une **fiche jeu du catalogue** via `magazine_subject.catalog_oeuvre_id`.

## Principe

| Élément | Rôle |
|---------|------|
| **Libellé sujet** (`label`, `detail`, `parution_year`) | Texte affiché sur les tags et dans les listes — saisie libre, conservée telle quelle |
| **Lien catalogue** (`catalog_oeuvre_id`) | Pointeur vers `oeuvres.id` où `media_domain = 'jeu'` — croisement, stats, recherche |
| **Tag série** (PS5, PC…) | Contexte de la revue sur le numéro, **pas** l’identité du jeu catalogue |

Un sujet **sans lien** reste parfaitement valide. Le pont enrichit l’expérience ; il ne remplace pas la saisie historique.

## Parcours utilisateur

1. **À l’ajout** d’un sujet sur un numéro : autocomplétion catalogue jeux (test / preview / interview).
2. **Fiche sujet** : lien vers la fiche jeu si le jeu est dans votre bibliothèque.
3. **Fiche jeu** (`/jeu.php`) : section « Dans vos magazines » (numéros de **votre** bibliothèque) — le clic bascule l’onglet Magazines si besoin.
4. **Fiche catalogue admin** (`/oeuvre-jeu.php`) : sujets reliés (tous numéros du catalogue).
5. **Recherche globale** (`/magazines.php`) : remonte aussi les sujets reliés dont le **titre catalogue** correspond.

## Rattachement rétroactif (admin)

Page **`/maintenance-magazine-jeux-liens.php`** (menu Gestion) :

- liste les sujets test / preview / interview **sans lien** ;
- propose des **suggestions** catalogue à partir du libellé ;
- permet de **relier**, **corriger** ou **retirer** un lien ;
- journalise les actions dans l’audit catalogue.

## Cas ambigus (homonymes)

### Même titre, jeux différents

Exemples fréquents :

- *Gran Turismo* (1997, PS1) vs *Gran Turismo 7* (2022, PS5) ;
- *Resident Evil* (original) vs remakes ;
- suites numérotées (*FIFA 23*, *FIFA 24*).

**Recommandation :** comparez **année du sujet** (tag), **tag plateforme** du numéro et **plateforme / année** de la fiche catalogue avant de valider le lien.

### Acronymes

« GTA », « GT », « RE » peuvent correspondre à plusieurs fiches si les acronymes IGDB (`alternative_names`) sont proches.

La recherche globale magazines utilise aussi ces acronymes pour les sujets **déjà reliés**. Pour un sujet non relié, seul le libellé libre compte.

### Extensions et remakes

Reliez le sujet au **jeu testé dans l’article**, pas systématiquement au jeu de base :

- test d’un DLC → fiche **extension** si elle existe ;
- test d’un remake → fiche **remake**.

### Quand ne pas lier

- dossier multi-jeux (« Top 10 PS5 ») ;
- sujet matériel / interview généraliste sans jeu unique ;
- doute sur l’opus exact — **mieux vaut laisser sans lien**.

## Fusion de sujets

Lors d’une fusion (`/maintenance-magazine-sujets.php`), si le sujet conservé n’a pas de lien catalogue et que le sujet fusionné en a un, le lien est **reporté** sur la fiche conservée.

## Fichiers techniques

| Fichier | Rôle |
|---------|------|
| `lib/MagazineGameLink.php` | Validation, liaison, affichage croisé |
| `lib/MagazineGameLinkMaintenance.php` | Outil admin rétroactif |
| `sql/migrations/039_oeuvre_jeu_magazine_link.sql` | Colonne `catalog_oeuvre_id` |
| `www/maintenance-magazine-jeux-liens.php` | Interface admin |
| `www/rechercher-jeux-catalogue.php` | API autocomplétion |

Voir aussi [jeux.md](jeux.md) et [magazines.md](magazines.md).
