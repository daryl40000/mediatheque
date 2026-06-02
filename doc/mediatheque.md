# Médiathèque — guide du fork

**Version : 0.2.5** · **Date : 2026-05-31**

Ce document décrit ce qu’est la **Médiathèque**, ce qui a été livré en **0.1.0**, et comment cela s’articule avec **Monciné**.

---

## 1. D’où vient le projet ?

**Monciné 1.0.0** est une application web pour gérer une **dvdthèque** : films, envies, notes, TMDB, foyers, prêts, partage, etc.

**Médiathèque** est une **évolution** du même code : une seule application pour plusieurs types de médias, avec le **même principe** (catalogue partagé + ma collection + mes envies) mais des **onglets** en haut de page.

En **0.2.x**, les onglets **Films** et **Magazines** sont utilisables. Les autres onglets affichent « Bientôt disponible ».

---

## 2. Ce qui change pour l’utilisateur (0.1.0)

### Barre d’onglets

En haut de chaque page connectée :

- **Films** (gris) — comportement identique à Monciné 1.0.0  
- **BD / Manga** (rose) — à venir  
- **Livres** (bleu) — à venir  
- **Jeux** (violet) — à venir  
- **Magazines** (vert d’eau) — séries, numéros, PDF (**0.2.0+**, voir [magazines.md](magazines.md))  

Un clic change **toute l’interface** : couleur, libellés du menu (« Mes films », « Mes envies »…), fond léger.

### Ce qui ne change pas (onglet Films)

- Mes films, Mes envies, statistiques, quiz du soir, sagas, prêts, amis, partage, import/export, catalogue admin, compte, etc.

### Pages réservées aux films

Depuis le quiz, les sagas, l’ajout de film, etc., un changement vers un autre onglet vous envoie sur **Mes films** (évite les blocages).

---

## 3. Ce qui a été fait techniquement

| Zone | Détail |
|------|--------|
| **Base** | Colonne `media_domain` sur `oeuvres` (`film` par défaut) |
| **Migration** | `sql/migrations/030_media_domain.sql` |
| **Session** | `lib/MediaContext.php` — onglet actif mémorisé |
| **Filtres SQL** | `CatalogSchema::applyMediaDomainFilter()` — listes, catalogue, envies groupe… |
| **Garde-fous** | `lib/MediaDomainGuards.php` — pages « bientôt », pages films-only |
| **Interface** | `templates/_media_domain_tabs.php`, variables CSS sur `body` |
| **Tests** | Unitaires + intégration domaine média |

Le code métier reste largement celui de Monciné (`FilmRepository`, TMDB, etc.) : on **filtre** par domaine plutôt que de tout réécrire.

---

## 4. Fichiers importants à connaître

| Fichier | Rôle |
|---------|------|
| `lib/MediaDomain.php` | Types de médias, couleurs, libellés |
| `lib/MediaContext.php` | Onglet actif (session) |
| `lib/MediaDomainGuards.php` | Redirections et page « bientôt » |
| `www/set-media-domain.php` | Change l’onglet puis redirige |
| `ROADMAP.md` | Plan M0 → M7 |
| `CHANGELOG.md` | Historique des versions |
| `doc/magazines.md` | Module magazines (PDF, recherche, tags) |
| `doc/conventions-techniques.md` | **Nommage Monciné vs Médiathèque — lecture obligatoire** |

---

## 5. Installation et migrations

Comme Monciné :

```bash
composer install
php lib/cli/migrate.php
php -S localhost:8080 -t www
```

Après mise à jour vers 0.1.0, exécuter les migrations (au minimum **030**). Les œuvres existantes reçoivent `media_domain = film`.

---

## 6. Données locales (non versionnées)

Voir `.gitignore` :

- `data/moncine.db` — votre base  
- `data/omdb_api_key.txt`, `data/tmdb_api_key.txt`  
- `data/sessions/`, `data/auth_rate_limit/`  
- `install_seed/*.csv`, `install_seed/*.zip` — graine d’install optionnelle  

---

## 8. Conventions techniques (développeurs)

**Obligatoire avant tout nouveau code ou refactor :** [doc/conventions-techniques.md](conventions-techniques.md)

Points essentiels :

- **Médiathèque** = nom produit (version dans `MONCINE_PACKAGE_VERSION`, actuellement **0.2.5**) ; **`Moncine\`** + **`MONCINE_*`** + **`moncine.db`** = identifiants code **à ne pas renommer** avant la phase M7.
- **`media_domain`** (onglet Films/BD/…) ≠ **`moncine_kind`** (film/série/spectacle dans l’onglet Films).
- Nouveau code multi-médias : `MediaDomain`, `MediaContext`, `CatalogSchema::applyMediaDomainFilter()`.

---

## 7. Suite prévue

Voir [ROADMAP.md](../ROADMAP.md) :

- **M1** — Valider qu’aucune régression sur les films  
- **M2–M4** — BD, livres, jeux  
- **M5** — magazines : suite (FTS, polish) — voir [magazines.md](magazines.md) pour l’existant  
- **M6–M7** — Prêts/partage par domaine, identité et doc  

---

*Auteur du fork : évolution du projet Monciné par Stéphane MATER — licence GPL-3.0-or-later.*
