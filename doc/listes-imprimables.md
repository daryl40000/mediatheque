# Listes imprimables (Mes films / Mes envies)

**Version : 0.1.0** (onglet Films — identique à Monciné 1.0.0)

Alternative **légère** à l’export PDF côté serveur (phase 10 reportée pour compatibilité **YunoHost** : pas de Dompdf ni autre dépendance Composer en production).

## Utilisation

1. Ouvrez **Mes films** ou **Mes envies**.
2. Appliquez vos **filtres** et votre **tri** comme d’habitude (recherche, type film/série, onglet envies du groupe, etc.).
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

Paramètres d’URL identiques aux listes : `q`, `sort`, `dir`, `kind` (films), `scope=group` (envies groupe).

## Colonnes imprimées

### Mes films

Affiche (miniature), titre, type, année, réalisateur, durée, style, saga, support, note personnelle, dernière vue.

### Mes envies

Titre, année, nationalité, réalisateur, style, versions recherchées (support + EAN).

### Envies du groupe

Nombre de demandes, titre, année, personnes concernées, réalisateur.

## Fichiers techniques

| Fichier | Rôle |
|---------|------|
| `www/imprimer-films.php` | Contrôleur collection |
| `www/imprimer-envies.php` | Contrôleur envies |
| `templates/layout_print.php` | Mise en page minimale (sans menu du site) |
| `www/assets/css/print.css` | Styles écran et `@media print` |
| `www/assets/js/print-page.js` | Déclenche `window.print()` (obligatoire : voir CSP ci-dessous) |
| `lib/PrintListService.php` | Préparation des données (filtres, tri, listes) |
| `lib/PrintListHelper.php` | Libellés des filtres et du tri |
| `lib/View.php` | `filmsPrintUrl()`, `wishlistPrintUrl()` |
| `templates/_print_button.php` | Bouton sur Mes films / Mes envies |
| `templates/_print_collection_table.php` | Tableau collection |
| `templates/_print_wishlist_table.php` | Tableau envies personnelles |
| `templates/_print_wishlist_group_table.php` | Tableau envies du groupe |

## Sécurité (CSP)

Moncine envoie une politique **Content-Security-Policy** avec `script-src 'self'`. Les scripts **inline** (`onclick="…"`) sont donc **bloqués**.

Le bouton d’impression utilise un fichier JavaScript **externe** : `www/assets/js/print-page.js`. Ne pas remettre d’`onclick` inline sur `layout_print.php`.

## Phase 10 (export PDF serveur)

Si un export PDF **généré par le serveur** est ajouté un jour (Dompdf, mPDF…), il restera **optionnel** : les listes imprimables couvrent le besoin courant sans dépendance supplémentaire sur YunoHost.
