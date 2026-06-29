-- Plateformes jeux configurables (admin).
CREATE TABLE IF NOT EXISTS game_platform (
    platform_key TEXT PRIMARY KEY,
    label TEXT NOT NULL,
    short_label TEXT NOT NULL DEFAULT '',
    kind TEXT NOT NULL DEFAULT 'other'
        CHECK (kind IN ('pc', 'console', 'mobile', 'multi', 'other')),
    console_store TEXT NOT NULL DEFAULT '',
    sort_order INTEGER NOT NULL DEFAULT 100,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_game_platform_active_sort
    ON game_platform(active, sort_order, label COLLATE NOCASE);

INSERT OR IGNORE INTO game_platform (platform_key, label, short_label, kind, console_store, sort_order) VALUES
    ('pc', 'PC', 'PC', 'pc', '', 10),
    ('ps5', 'PlayStation 5', 'PS5', 'console', 'psn', 20),
    ('ps4', 'PlayStation 4', 'PS4', 'console', 'psn', 30),
    ('xbox_series', 'Xbox Series', 'Xbox Series', 'console', 'xbox', 40),
    ('xbox_one', 'Xbox One', 'Xbox One', 'console', 'xbox', 50),
    ('snes', 'Super Nintendo (SNES)', 'SNES', 'console', '', 55),
    ('switch', 'Nintendo Switch', 'Switch', 'console', 'eshop', 60),
    ('switch2', 'Nintendo Switch 2', 'Switch 2', 'console', 'eshop', 65),
    ('mobile', 'Mobile', 'Mobile', 'mobile', '', 70),
    ('multi', 'Multi-plateformes', 'Multi', 'multi', '', 80),
    ('other', 'Autre', 'Autre', 'other', '', 90);
