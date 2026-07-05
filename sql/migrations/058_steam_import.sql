-- Import bibliothèque Steam : appid catalogue, stats playtime, SteamID utilisateur.

ALTER TABLE oeuvre_jeu ADD COLUMN steam_appid INTEGER NOT NULL DEFAULT 0;

CREATE UNIQUE INDEX IF NOT EXISTS idx_oeuvre_jeu_steam_appid
    ON oeuvre_jeu(steam_appid) WHERE steam_appid > 0;

ALTER TABLE utilisateurs ADD COLUMN steam_id TEXT NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS game_steam_stats (
    bibliotheque_id INTEGER PRIMARY KEY REFERENCES bibliotheque(id) ON DELETE CASCADE,
    steam_appid INTEGER NOT NULL DEFAULT 0,
    playtime_minutes INTEGER NOT NULL DEFAULT 0,
    last_played_unix INTEGER NOT NULL DEFAULT 0,
    synced_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_game_steam_stats_appid ON game_steam_stats(steam_appid) WHERE steam_appid > 0;
