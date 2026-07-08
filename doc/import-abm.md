# Import catalogue depuis Abandonware Magazines (ABM)

Outil **ponctuel** pour préparer un fichier JSON avant import dans le catalogue Médiathèque.  
**Ne pas utiliser en production** — script supprimable une fois les imports préparés.

## Source

- Site : [abandonware-magazines.org](https://www.abandonware-magazines.org/)
- API développement : `https://www.abandonware-magazines.org/api-dev.php?choixapi=N`
  - `choixapi=12` : liste des revues (id, titre, fichier logo)
  - `choixapi=10` : tous les numéros (~23k) avec URL de couverture

L’API renvoie du texte séparé par `;` (pas du JSON). Le script ne télécharge **pas** les ZIP PDF.

### URLs de couverture (depuis **0.7.15**)

Certaines revues ont un **espace** dans le chemin image (ex. `…/PC Team/pcteam_numerocd01.jpg`). L’export JSON encode ces espaces en **`%20`** (`AbmApiParser::normalizeCoverUrl`) pour que :

- le JSON reste valide ;
- l’import catalogue et le téléchargement des couvertures fonctionnent sans modifier le code de production.

Regénérez le JSON après mise à jour du script fetch si vous aviez un export antérieur avec des URLs « cassées ».

## Commande

Depuis la racine du dépôt :

```bash
php lib/cli/abm-fetch-catalog.php --stats
```

Options utiles :

| Option | Description |
|--------|-------------|
| `--output=FILE` | Fichier JSON (défaut : `install_seed/abm-magazines.json`) |
| `--magazine=Tilt` | Une seule revue (filtre par sous-chaîne du titre) |
| `--magazine-id=29` | Filtre par identifiant ABM |
| `--cache-dir=DIR` | Garde les réponses brutes pour éviter de re-télécharger |
| `--dry-run` | Stats sans écrire le fichier |
| `--stats` | Résumé après export |

Exemple pour tester sur Tilt uniquement :

```bash
php lib/cli/abm-fetch-catalog.php --magazine=Tilt --output=install_seed/abm-tilt.json --stats
```

## Format JSON produit

```json
{
  "format_version": 1,
  "source": "https://www.abandonware-magazines.org/",
  "generated_at": "2026-06-16T12:00:00+00:00",
  "stats": {
    "series_count": 1,
    "issue_count": 120,
    "issues_with_cover_url": 120
  },
  "series": [
    {
      "abm_magazine_id": 29,
      "titre": "Tilt",
      "logo_filename": "logo_tilt.jpg",
      "logo_url": "https://www.abandonware-magazines.org/images_logomags/logo_tilt.jpg",
      "issues": [
        {
          "abm_issue_id": 1,
          "numero": "033",
          "numero_ordre": 33,
          "hors_serie": false,
          "is_cd": false,
          "date_label": "mars 1986",
          "annee": 1986,
          "cover_filename": "tilt033petitecouverture.jpg",
          "cover_url": "http://..."
        }
      ]
    }
  ]
}
```

Les champs `numero_ordre` et `annee` facilitent un futur import catalogue (`mag_numero_ordre`, `mag_annee`, etc.).

## Fichiers versionnés

- `lib/AbmApiParser.php` — analyse du texte API
- `lib/AbmCatalogFetcher.php` — téléchargement HTTP
- `lib/cli/abm-fetch-catalog.php` — point d’entrée CLI

Les gros JSON générés et le cache API sont ignorés par Git (voir `.gitignore`).

## Suite prévue

1. Exécuter le script en local / sur l’interface de test
2. Valider le JSON sur quelques revues
3. **Importer dans le catalogue** (voir ci-dessous)
4. Supprimer les scripts ABM une fois l’import stabilisé

## Importer dans le catalogue Médiathèque

### Interface admin (recommandé)

Page : **`/import-catalogue-magazines.php`** (administrateur connecté).

1. Générer le JSON : `php lib/cli/abm-fetch-catalog.php --magazine=Tilt --output=install_seed/abm-tilt.json`
2. Ouvrir la page d’import admin
3. Envoyer le fichier **ou** indiquer le chemin serveur (`install_seed/abm-tilt.json`)
4. Cocher **Simulation** pour un premier essai, puis lancer l’import réel

Les séries et numéros sont créés **uniquement au catalogue** — pas dans votre bibliothèque personnelle. Les utilisateurs pourront ensuite ajouter les numéros via le catalogue.

Options :

| Option | Effet |
|--------|--------|
| Ignorer les doublons | Ne recrée pas un numéro déjà présent (même série + n°) |
| Simulation | Affiche les stats sans écrire en base |
| Télécharger les couvertures | Met en cache les images (HTTPS), **par lots** (défaut 20 par passage) |
| Couvertures par lot | Nombre max de téléchargements HTTP par import (1–40) |
| Filtrer une revue | Import partiel (ex. `Tilt`) |

Pour les couvertures d’une revue déjà importée : gardez **Ignorer les doublons**, cochez **Télécharger les couvertures**, filtrez la revue, relancez jusqu’à ce que « couvertures restantes » soit à 0.

### Ligne de commande (gros volumes)

```bash
php lib/cli/abm-import-catalog.php --json=install_seed/abm-magazines.json --dry-run --stats
php lib/cli/abm-import-catalog.php --json=install_seed/abm-tilt.json --download-covers --cover-batch-size=20 --stats
```

### Créer une série à la main (catalogue seul)

Sur la même page admin, section **« Créer une série catalogue »** — sans passer par « Mes magazines ».
