# Listes imprimables (Mes films / Mes envies / Mes jeux / Mes BD / Mes magazines)

**Version : 0.8.0** (films, jeux, BD, magazines)

Alternative **légère** à l’export PDF côté serveur (phase 10 reportée pour compatibilité **YunoHost** : pas de Dompdf ni autre dépendance Composer en production).

## Utilisation

1. Ouvrez **Mes films**, **Mes jeux**, **Mes BD**, **Mes magazines** (ou les pages d’envies correspondantes).
2. Appliquez vos **filtres** et votre **tri** comme d’habitude.
3. Cliquez sur **Version imprimable** (s’ouvre dans un **nouvel onglet**).
4. Sur la page d’impression :
   - cliquez sur **Imprimer / Enregistrer en PDF** ;
   - dans la fenêtre du navigateur, choisissez une imprimante ou **Enregistrer au format PDF** (Chrome, Firefox, Edge…).

La liste imprimée reprend les **mêmes critères** que l’écran d’origine (filtres, tri).

**Limite :** au plus **500 lignes** par impression (évite les pages trop lourdes). Si vous en avez plus, un message vous invite à affiner les filtres.

## Pages

| URL | Source |
|-----|--------|
| `/imprimer-films.php` | Mes films — collection partagée du foyer |
| `/imprimer-envies.php` | Mes envies personnelles, ou **envies du groupe** si cet onglet est actif |
| `/imprimer-jeux.php` | Mes jeux — collection partagée du foyer |
| `/imprimer-envies-jeux.php` | Mes envies jeux (liste personnelle) |
| `/imprimer-bd.php` | Mes BD — liste des séries |
| `/imprimer-envies-bd.php` | Mes envies BD — liste des séries |
| `/imprimer-magazines.php` | Mes magazines — liste des séries |
| `/imprimer-envies-magazines.php` | Mes envies magazines — liste des séries |
| `/imprimer-serie-bd.php` | Une série BD (tomes) — depuis la fiche série |
| `/imprimer-serie-magazine.php` | Une série magazine (numéros) — depuis la fiche série |

Paramètres d’URL identiques aux listes : `q`, `sort`, `dir`, `kind` (films), `scope=group` (envies groupe), filtres jeux (`platform`, `genre`, `decade`, `support`, `extensions`).

## Colonnes imprimées

### Mes films

Affiche (miniature), titre, type, année, réalisateur, durée, style, saga, support, note personnelle, dernière vue.

### Mes envies

Titre, année, nationalité, réalisateur, style, versions recherchées (support + EAN).

### Envies du groupe

Nombre de demandes, titre, année, personnes concernées, réalisateur.

### Mes jeux

Jaquette (miniature), titre, plateforme, année, studio, genres, support, note personnelle, date d’ajout.

### Mes envies jeux

Jaquette, titre, plateforme, année, studio, genres, support, note, date d’ajout.

### Mes BD / Mes magazines (listes de séries)

Titre de série, type ou éditeur, compteurs (possédés / catalogue ou envies).

## Fichiers techniques

| Fichier | Rôle |
|---------|------|
| `www/imprimer-films.php` | Contrôleur collection films |
| `www/imprimer-envies.php` | Contrôleur envies films |
| `www/imprimer-jeux.php` | Contrôleur collection jeux |
| `www/imprimer-envies-jeux.php` | Contrôleur envies jeux |
| `www/imprimer-bd.php` | Contrôleur collection BD |
| `www/imprimer-envies-bd.php` | Contrôleur envies BD |
| `www/imprimer-magazines.php` | Contrôleur collection magazines |
| `www/imprimer-envies-magazines.php` | Contrôleur envies magazines |
| `templates/layout_print.php` | Mise en page minimale (sans menu du site) |
| `www/assets/css/print.css` | Styles écran et `@media print` |
| `www/assets/js/print-page.js` | Déclenche `window.print()` (obligatoire : voir CSP ci-dessous) |
| `lib/PrintListService.php` | Préparation des données films |
| `lib/GamePrintListService.php` | Préparation des données jeux |
| `lib/BdPrintListService.php` | Préparation BD (séries + série détaillée) |
| `lib/MagazinePrintListService.php` | Préparation magazines (séries + série détaillée) |
| `lib/PrintListHelper.php` | Libellés des filtres et du tri |
| `lib/View.php` | `filmsPrintUrl()`, `wishlistPrintUrl()`, `gamesPrintUrl()`, `bdPrintUrl()`, `magazinesPrintUrl()`, … |
| `templates/_print_button.php` | Bouton sur les listes |
| `templates/_print_collection_table.php` | Tableau collection films |
| `templates/_print_game_table.php` | Tableau jeux |
| `templates/_print_series_table.php` | Tableau séries BD / magazines |
| `templates/_print_wishlist_table.php` | Tableau envies personnelles films |
| `templates/_print_wishlist_group_table.php` | Tableau envies du groupe films |

## Sécurité (CSP)

Moncine envoie une politique **Content-Security-Policy** avec `script-src 'self'`. Les scripts **inline** (`onclick="…"`) sont donc **bloqués**.

Le bouton d’impression utilise un fichier JavaScript **externe** : `www/assets/js/print-page.js`. Ne pas remettre d’`onclick` inline sur `layout_print.php`.

## Phase 10 (export PDF serveur)

Reportée : génération PDF côté serveur (Dompdf, etc.) pour rester compatible YunoHost sans dépendance Composer lourde en production.
