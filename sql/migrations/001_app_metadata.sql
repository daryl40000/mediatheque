-- Paquet Moncine : métadonnées d’installation (edition yunohost, numéro de schéma).
-- Le schéma complet est dans sql/schema.sql ; ce fichier marque la piste de migration paquet.

CREATE TABLE IF NOT EXISTS app_metadata (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);

INSERT OR IGNORE INTO app_metadata (key, value) VALUES ('package_edition', 'yunohost');
INSERT OR IGNORE INTO app_metadata (key, value) VALUES ('schema_version', '1');
