# Sécurité — bonnes pratiques hébergement

Complément à la revue de sécurité applicative. À appliquer surtout en **production**.

## Racine web

Le serveur web doit pointer uniquement sur le dossier **`www/`**, pas sur la racine du dépôt. Ainsi `data/` (base SQLite, clés API, PDF, sessions) n’est pas exposé directement.

## Permissions recommandées sur `data/`

Depuis la racine du projet (adapté à l’utilisateur PHP-FPM, ex. `www-data`) :

```bash
chmod 700 data
chmod 700 data/sessions data/media data/auth_rate_limit data/posters 2>/dev/null || true
chmod 600 data/moncine.db data/*_api_key.txt data/*_credentials.json 2>/dev/null || true
chmod 700 data/.keys 2>/dev/null || true
```

Sur un serveur partagé, des droits trop ouverts (`644` / `755`) permettent à un autre compte de lire la base ou les sessions.

## Secrets (ne pas versionner)

Ignorés par Git (voir `.gitignore`) :

| Fichier / dossier | Contenu |
|-------------------|---------|
| `data/moncine.db*` | Base SQLite |
| `data/tmdb_api_key.txt`, `data/omdb_api_key.txt` | Clés films |
| `data/igdb_credentials.json` | IGDB |
| `data/steam_api_key.txt` | Steam |
| `data/gog_credentials.json` | GOG |
| `data/.keys/` | Clés chiffrement locales |
| `data/sessions/`, `data/media/` | Sessions et PDF |

Préférez les variables d’environnement en production quand elles existent (`MONCINE_*`).

## Proxy (YunoHost / Nginx)

Définir **`MONCINE_TRUST_PROXY=1`** uniquement derrière un reverse proxy de confiance. Cela active :

- IP client via `X-Real-IP` / `X-Forwarded-For` (anti brute-force) ;
- HTTPS via `X-Forwarded-Proto` (cookie `Secure`, HSTS).

Sans ce réglage, ces en-têtes sont **ignorés** (évite qu’un client forge HTTPS ou une fausse IP).

## Affiches vs PDF

| Ressource | Accès anonyme |
|-----------|----------------|
| Jaquettes (`/poster.php`) | Oui (volontaire, pour le partage visiteur) |
| PDF magazines (`/media-object.php`) | Non (connexion + droit foyer / admin) |

Voir [partage-visiteur.md](partage-visiteur.md).
