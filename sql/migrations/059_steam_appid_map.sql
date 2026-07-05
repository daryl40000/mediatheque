-- Correspondance manuelle AppID Steam → fiche catalogue (persistante entre imports).

CREATE TABLE IF NOT EXISTS game_steam_appid_map (
    steam_appid INTEGER PRIMARY KEY,
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    mapped_by_user_id INTEGER NOT NULL DEFAULT 0,
    source TEXT NOT NULL DEFAULT 'manual',
    mapped_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_game_steam_appid_map_oeuvre
    ON game_steam_appid_map(oeuvre_id) WHERE oeuvre_id > 0;
