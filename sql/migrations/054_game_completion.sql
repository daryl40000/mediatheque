-- Fin de partie : l'utilisateur peut marquer un jeu comme terminé (avec date, plusieurs fois).

CREATE TABLE IF NOT EXISTS game_completion (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    completed_at TEXT NOT NULL DEFAULT (date('now')),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_game_completion_bib_user ON game_completion(bibliotheque_id, user_id);
CREATE INDEX IF NOT EXISTS idx_game_completion_user_date ON game_completion(user_id, completed_at DESC);
