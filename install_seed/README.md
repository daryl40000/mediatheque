# Graine d’installation (catalogue + affiches)

Ce dossier sert à **préremplir Moncine automatiquement** lors d’une **installation neuve** uniquement.

## Fichiers à déposer

| Fichier | Noms reconnus |
|---------|----------------|
| Export **catalogue** (CSV admin) | `catalogue.csv`, `moncine-catalogue.csv`, ou `*catalogue*.csv` |
| Archive **affiches** | `affiches.zip`, `posters.zip`, ou tout `*.zip` |

Vous pouvez ne fournir que le CSV, seulement le ZIP, ou les deux.

## Où placer les fichiers ?

**Option A — dans le paquet** (avant de construire / installer YunoHost) :

```text
Moncine/install_seed/catalogue.csv
Moncine/install_seed/affiches.zip
```

**Option B — sur le serveur** (dossier persistant, recommandé pour YunoHost) :

```text
/home/yunohost.appfiles/moncine/install_seed/catalogue.csv
/home/yunohost.appfiles/moncine/install_seed/affiches.zip
```

Le chemin exact de `data/` dépend de votre instance ; c’est le même dossier que `moncine.db`.

## Quand l’import a lieu ?

- **Uniquement** à la fin de `yunohost app install moncine` (pas lors des mises à jour).
- **Uniquement** si le catalogue est **vide** (aucune œuvre en base).
- **Une seule fois** par instance (marqueur `install_seed_applied` en base).

Si une base existe déjà avec des films, les fichiers ici sont **ignorés** — aucun risque d’écraser vos données.

## Après l’installation

1. Ouvrez l’application et créez le compte administrateur (`/premier-compte.php`).
2. Importez la **bibliothèque** de chaque utilisateur via l’interface (export CSV bibliothèque), si besoin.

## Ne pas versionner vos données personnelles

Les fichiers `.csv` et `.zip` de ce dossier sont exclus de git (voir `.gitignore`).  
Ne commitez que ce `README.md` et `.gitkeep`.
