# Import bibliothèque Steam

Spécification de l’import de la collection Steam dans Moncine (implémenté en 0.7.x).

## Objectif

Synchroniser la bibliothèque Steam d’un utilisateur connecté vers **Mes jeux** :

1. **GetOwnedGames** (API Steam Web) — tous les jeux, y compris `playtime_forever = 0`
2. Correspondance **AppID → IGDB** via `external_games` (category Steam = 1)
3. Enrichissement catalogue via le pipeline IGDB existant quand une correspondance existe
4. Fiche catalogue minimale (titre Steam + PC + magasin Steam) sinon
5. Fusion automatique des magasins démat (pas de doublon si le jeu existe déjà via GOG, Epic, etc.)
6. Stockage et affichage du **temps de jeu Steam** (distinct de « Fini le » / `game_completion`)

## Prérequis

| Élément | Où |
|--------|-----|
| Clé API Steam Web (globale) | Page **Importer** (`data/steam_api_key.txt` ou `MONCINE_STEAM_API_KEY`) |
| SteamID64 (par utilisateur) | **Paramètres du compte** |
| Identifiants IGDB (optionnel mais recommandé) | Page **Importer** |
| Migration `058_steam_import.sql` | Base SQLite |

## Schéma

- `oeuvre_jeu.steam_appid` — index unique si > 0
- `utilisateurs.steam_id` — SteamID64
- `game_steam_stats` — `bibliotheque_id`, `steam_appid`, `playtime_minutes`, `last_played_unix`, `synced_at`
- `game_steam_appid_map` — correspondance persistante `steam_appid` → `oeuvre_id` (liens manuels)

## Parcours utilisateur

1. Admin : enregistre la clé API sur `/import.php`
2. Utilisateur : renseigne son SteamID64 sur `/parametres.php`
3. Utilisateur : **Préparer l’import Steam** → aperçu `/import-steam.php`
4. Validation :
   - **Administrateur** : sections « Relier au catalogue », « Ajouter à ma bibliothèque » et « Créer au catalogue »
   - **Utilisateur** : une seule liste à cocher — jeux au catalogue ajoutés tout de suite ; jeux absents → proposition automatique + ajout Mes jeux **en attente** jusqu’à validation admin
5. Lors de l’**acceptation** d’une proposition issue d’un import Steam : ajout automatique à la bibliothèque du demandeur (magasin Steam + temps de jeu)

## Règles métier

- **Ne pas filtrer** les jeux avec 0 minute de jeu
- Appel API avec `skip_unvetted_apps=false`
- Throttle IGDB : 250 ms entre lots `external_games`
- Requête IGDB : `external_game_source` (Steam) + `uid` entre guillemets ; repli sur `category = 1` (ancienne API)
- Rapprochement catalogue (par priorité) :
  1. **`game_steam_appid_map`** (lien manuel enregistré)
  2. `steam_appid` en base
  3. `igdb_id` (API + fiche catalogue)
  4. URL Steam déjà enregistrée dans `digital_stores`
  5. Titre (collection utilisateur, puis catalogue)
- Fusion catalogue (`CatalogMaintenance`) : les liens `game_steam_appid_map` suivent la fiche conservée
- URL magasin : `https://store.steampowered.com/app/{appid}/`
- Icône Steam (fiche minimale) : CDN `media.steampowered.com/.../apps/{appid}/{hash}.jpg`

## Fichiers principaux

- `lib/SteamConfig.php`, `lib/SteamWebApiClient.php`, `lib/SteamLibraryImporter.php`
- `lib/GameSteamStatsRepository.php`
- `lib/GameSteamAppIdMapRepository.php`
- `lib/IgdbClient::mapSteamAppIdsToIgdbIds()`
- `lib/GameDigitalStore::mergeStore()`
- `www/import-steam-actions.php`, `www/import-steam.php`

## Limites connues (v1)

- Pas de synchronisation automatique planifiée (import manuel)
- Pas d’import des succès Steam
- Le temps de jeu n’est pas triable dans la liste
- Titre catalogue en doublon strict : création refusée si un jeu du même titre existe déjà
