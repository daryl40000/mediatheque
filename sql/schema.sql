-- Schéma Moncine — dvdthèque personnelle (catalogue + bibliothèque)
-- Exécuté automatiquement au premier lancement si la base n'existe pas.

CREATE TABLE IF NOT EXISTS foyers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL DEFAULT '',
    kind TEXT NOT NULL DEFAULT 'famille',
    created_by_user_id INTEGER DEFAULT NULL REFERENCES utilisateurs(id),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS friendships (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    requester_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    addressee_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'accepted', 'blocked')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    responded_at TEXT DEFAULT NULL,
    UNIQUE (requester_id, addressee_id)
);

CREATE INDEX IF NOT EXISTS idx_friendships_addressee_status
    ON friendships(addressee_id, status);

CREATE TABLE IF NOT EXISTS group_members (
    foyer_id INTEGER NOT NULL REFERENCES foyers(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    role TEXT NOT NULL DEFAULT 'member' CHECK (role IN ('founder', 'member')),
    joined_at TEXT NOT NULL DEFAULT (datetime('now')),
    invited_by INTEGER DEFAULT NULL REFERENCES utilisateurs(id),
    PRIMARY KEY (foyer_id, user_id)
);

CREATE INDEX IF NOT EXISTS idx_group_members_user ON group_members(user_id);

CREATE TABLE IF NOT EXISTS group_invitations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    foyer_id INTEGER NOT NULL REFERENCES foyers(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    invited_by INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'accepted', 'declined')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    responded_at TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_group_invitations_user_status
    ON group_invitations(user_id, status);

CREATE TABLE IF NOT EXISTS utilisateurs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nom TEXT NOT NULL DEFAULT '',
    prenom TEXT NOT NULL DEFAULT '',
    pseudo TEXT NOT NULL DEFAULT '',
    ville TEXT NOT NULL DEFAULT '',
    searchable INTEGER NOT NULL DEFAULT 1,
    email TEXT NOT NULL DEFAULT '',
    password_hash TEXT NOT NULL DEFAULT '',
    role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('admin', 'user')),
    actif INTEGER NOT NULL DEFAULT 1,
    last_login_at TEXT DEFAULT NULL,
    foyer_id INTEGER DEFAULT NULL REFERENCES foyers(id),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_utilisateurs_email
    ON utilisateurs(email) WHERE email != '';

CREATE INDEX IF NOT EXISTS idx_utilisateurs_foyer ON utilisateurs(foyer_id);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    used_at TEXT DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_password_reset_token_hash
    ON password_reset_tokens(token_hash);

CREATE INDEX IF NOT EXISTS idx_password_reset_expires
    ON password_reset_tokens(expires_at);

CREATE TABLE IF NOT EXISTS inscription_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    nom TEXT NOT NULL DEFAULT '',
    prenom TEXT NOT NULL DEFAULT '',
    pseudo TEXT NOT NULL DEFAULT '',
    password_hash TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending_email'
        CHECK (status IN ('pending_email', 'pending_admin', 'approved', 'rejected')),
    confirm_token_hash TEXT NOT NULL DEFAULT '',
    confirm_expires_at TEXT NOT NULL,
    email_confirmed_at TEXT DEFAULT NULL,
    user_id INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    reviewed_by INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    review_note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_inscription_requests_email_active
    ON inscription_requests(LOWER(TRIM(email)))
    WHERE status IN ('pending_email', 'pending_admin');

CREATE INDEX IF NOT EXISTS idx_inscription_requests_status
    ON inscription_requests(status);

CREATE TABLE IF NOT EXISTS email_change_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    new_email TEXT NOT NULL,
    old_email TEXT NOT NULL,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_email_change_user_pending
    ON email_change_requests(user_id)
    WHERE expires_at > datetime('now');

CREATE INDEX IF NOT EXISTS idx_email_change_token
    ON email_change_requests(token_hash);

CREATE TABLE IF NOT EXISTS oeuvres (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    media_domain TEXT NOT NULL DEFAULT 'film',
    titre TEXT NOT NULL,
    titre_original TEXT DEFAULT '',
    realisateur TEXT DEFAULT '',
    duree_min INTEGER DEFAULT 0,
    styles TEXT DEFAULT '',
    annee INTEGER DEFAULT 0,
    nationalite TEXT DEFAULT '',
    tmdb_id INTEGER DEFAULT 0,
    tmdb_media_type TEXT DEFAULT '',
    tmdb_tv_kind TEXT DEFAULT '',
    realisateur_tmdb_id INTEGER DEFAULT 0,
    acteur_1 TEXT DEFAULT '',
    acteur_1_tmdb_id INTEGER DEFAULT 0,
    acteur_2 TEXT DEFAULT '',
    acteur_2_tmdb_id INTEGER DEFAULT 0,
    acteur_3 TEXT DEFAULT '',
    acteur_3_tmdb_id INTEGER DEFAULT 0,
    poster_url TEXT DEFAULT '',
    synopsis TEXT DEFAULT '',
    moncine_kind TEXT DEFAULT 'film',
    omdb_imdb_id TEXT DEFAULT '',
    omdb_enriched_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT NULL,
    UNIQUE (titre, realisateur)
);

CREATE INDEX IF NOT EXISTS idx_oeuvres_tmdb ON oeuvres(tmdb_id) WHERE tmdb_id > 0;
CREATE INDEX IF NOT EXISTS idx_oeuvres_media_domain ON oeuvres(media_domain);

CREATE TABLE IF NOT EXISTS bibliotheque (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL DEFAULT 1 REFERENCES utilisateurs(id),
    foyer_id INTEGER DEFAULT NULL REFERENCES foyers(id),
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    statut TEXT NOT NULL DEFAULT 'collection' CHECK (statut IN ('collection', 'wishlist')),
    support_physique TEXT DEFAULT '',
    format_image TEXT DEFAULT '',
    format_son TEXT DEFAULT '',
    saga TEXT DEFAULT '',
    saga_ordre INTEGER DEFAULT 0,
    saison_numero INTEGER DEFAULT 0,
    saison_label TEXT DEFAULT '',
    ean TEXT DEFAULT '',
    tested_on_linux INTEGER NOT NULL DEFAULT 0,
    linux_not_supported INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_bibliotheque_user_statut ON bibliotheque(user_id, statut);
CREATE INDEX IF NOT EXISTS idx_bibliotheque_foyer_statut ON bibliotheque(foyer_id, statut);

CREATE UNIQUE INDEX IF NOT EXISTS idx_bibliotheque_foyer_collection
    ON bibliotheque(foyer_id, oeuvre_id)
    WHERE statut = 'collection' AND foyer_id IS NOT NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_bibliotheque_user_wishlist
    ON bibliotheque(user_id, oeuvre_id)
    WHERE statut = 'wishlist';

CREATE TABLE IF NOT EXISTS films (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    titre TEXT NOT NULL,
    titre_original TEXT DEFAULT '',
    realisateur TEXT DEFAULT '',
    duree_min INTEGER DEFAULT 0,
    format_image TEXT DEFAULT '',
    format_son TEXT DEFAULT '',
    support_physique TEXT DEFAULT '',
    styles TEXT DEFAULT '',
    saga TEXT DEFAULT '',
    saga_ordre INTEGER DEFAULT 0,
    annee INTEGER DEFAULT 0,
    nationalite TEXT DEFAULT '',
    tmdb_id INTEGER DEFAULT 0,
    tmdb_media_type TEXT DEFAULT '',
    tmdb_tv_kind TEXT DEFAULT '',
    realisateur_tmdb_id INTEGER DEFAULT 0,
    acteur_1 TEXT DEFAULT '',
    acteur_1_tmdb_id INTEGER DEFAULT 0,
    acteur_2 TEXT DEFAULT '',
    acteur_2_tmdb_id INTEGER DEFAULT 0,
    acteur_3 TEXT DEFAULT '',
    acteur_3_tmdb_id INTEGER DEFAULT 0,
    poster_url TEXT DEFAULT '',
    synopsis TEXT DEFAULT '',
    moncine_kind TEXT DEFAULT 'film',
    saison_numero INTEGER DEFAULT 0,
    saison_label TEXT DEFAULT '',
    ean TEXT DEFAULT '',
    omdb_imdb_id TEXT DEFAULT '',
    omdb_enriched_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (titre, realisateur)
);

CREATE TABLE IF NOT EXISTS historique (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    film_id INTEGER NOT NULL,
    user_id INTEGER DEFAULT NULL REFERENCES utilisateurs(id),
    date_vue TEXT NOT NULL DEFAULT (date('now')),
    note INTEGER,
    FOREIGN KEY (film_id) REFERENCES bibliotheque(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_historique_film ON historique(film_id);
CREATE INDEX IF NOT EXISTS idx_historique_date ON historique(date_vue);
CREATE INDEX IF NOT EXISTS idx_historique_user ON historique(user_id);
CREATE INDEX IF NOT EXISTS idx_historique_film_user ON historique(film_id, user_id);

CREATE TABLE IF NOT EXISTS catalogue_soumissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    payload_json TEXT NOT NULL,
    user_note TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'approved', 'rejected')),
    resulting_oeuvre_id INTEGER DEFAULT NULL REFERENCES oeuvres(id) ON DELETE SET NULL,
    review_note TEXT NOT NULL DEFAULT '',
    reviewed_by INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    reviewed_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_catalogue_soumissions_status
    ON catalogue_soumissions(status, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_catalogue_soumissions_user
    ON catalogue_soumissions(user_id, created_at DESC);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    kind TEXT NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    link_url TEXT NOT NULL DEFAULT '',
    related_submission_id INTEGER DEFAULT NULL,
    related_oeuvre_id INTEGER DEFAULT NULL,
    read_at TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_notifications_user_created
    ON notifications(user_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_notifications_user_unread
    ON notifications(user_id)
    WHERE read_at IS NULL;

CREATE TABLE IF NOT EXISTS share_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    token_hash TEXT NOT NULL UNIQUE,
    scope TEXT NOT NULL CHECK (scope IN ('collection', 'wishlist')),
    foyer_id INTEGER DEFAULT NULL REFERENCES foyers(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    label TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at TEXT DEFAULT NULL,
    revoked_at TEXT DEFAULT NULL,
    last_access_at TEXT DEFAULT NULL,
    access_count INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_share_links_user ON share_links(user_id);
CREATE INDEX IF NOT EXISTS idx_share_links_foyer ON share_links(foyer_id) WHERE foyer_id IS NOT NULL;

CREATE TABLE IF NOT EXISTS oeuvre_eans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    ean TEXT NOT NULL,
    support_physique TEXT NOT NULL DEFAULT '',
    label TEXT NOT NULL DEFAULT '',
    source TEXT NOT NULL DEFAULT 'manual',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (oeuvre_id, support_physique),
    UNIQUE (ean)
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_eans_oeuvre ON oeuvre_eans(oeuvre_id);
CREATE INDEX IF NOT EXISTS idx_oeuvre_eans_ean ON oeuvre_eans(ean);

CREATE TABLE IF NOT EXISTS wishlist_targets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    support_physique TEXT NOT NULL DEFAULT '',
    ean TEXT NOT NULL DEFAULT '',
    oeuvre_ean_id INTEGER DEFAULT NULL REFERENCES oeuvre_eans(id) ON DELETE SET NULL,
    label TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (bibliotheque_id, support_physique)
);

CREATE INDEX IF NOT EXISTS idx_wishlist_targets_bibliotheque ON wishlist_targets(bibliotheque_id);

CREATE INDEX IF NOT EXISTS idx_wishlist_targets_ean
    ON wishlist_targets(ean)
    WHERE ean != '';

CREATE TABLE IF NOT EXISTS loans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    lender_user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    borrower_user_id INTEGER DEFAULT NULL REFERENCES utilisateurs(id) ON DELETE SET NULL,
    borrower_name TEXT NOT NULL DEFAULT '',
    loaned_at TEXT NOT NULL DEFAULT (date('now')),
    due_at TEXT DEFAULT NULL,
    returned_at TEXT DEFAULT NULL,
    note TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_loans_bibliotheque_active
    ON loans(bibliotheque_id)
    WHERE returned_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_loans_lender_active
    ON loans(lender_user_id, returned_at)
    WHERE returned_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_loans_borrower_active
    ON loans(borrower_user_id, returned_at)
    WHERE borrower_user_id IS NOT NULL AND returned_at IS NULL;

CREATE TABLE IF NOT EXISTS loan_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    owner_user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    requester_user_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'accepted', 'declined', 'canceled', 'lent')),
    requested_at TEXT NOT NULL DEFAULT (datetime('now')),
    responded_at TEXT DEFAULT NULL,
    lent_at TEXT DEFAULT NULL,
    loan_id INTEGER DEFAULT NULL REFERENCES loans(id) ON DELETE SET NULL,
    note TEXT NOT NULL DEFAULT ''
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_loan_requests_unique_active
    ON loan_requests(bibliotheque_id, requester_user_id)
    WHERE status IN ('pending', 'accepted');

CREATE INDEX IF NOT EXISTS idx_loan_requests_owner_status
    ON loan_requests(owner_user_id, status, requested_at DESC);

CREATE INDEX IF NOT EXISTS idx_loan_requests_requester_status
    ON loan_requests(requester_user_id, status, requested_at DESC);

CREATE TABLE IF NOT EXISTS stored_objects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    backend TEXT NOT NULL DEFAULT 'local' CHECK (backend IN ('local')),
    relative_path TEXT NOT NULL,
    mime TEXT NOT NULL DEFAULT 'application/octet-stream',
    size_bytes INTEGER NOT NULL DEFAULT 0,
    checksum TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_stored_objects_path ON stored_objects(relative_path);
CREATE INDEX IF NOT EXISTS idx_stored_objects_backend ON stored_objects(backend);

CREATE TABLE IF NOT EXISTS series (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    media_domain TEXT NOT NULL DEFAULT 'magazine',
    titre TEXT NOT NULL,
    publication_type TEXT NOT NULL DEFAULT 'mensuel',
    poster_url TEXT DEFAULT '',
    editeur TEXT DEFAULT '',
    issn TEXT DEFAULT '',
    langue TEXT DEFAULT '',
    pays TEXT DEFAULT '',
    date_debut TEXT DEFAULT NULL,
    date_fin TEXT DEFAULT NULL,
    notes TEXT DEFAULT '',
    tags TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT DEFAULT NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_series_domain_titre
    ON series(media_domain, titre COLLATE NOCASE);

CREATE INDEX IF NOT EXISTS idx_series_media_domain ON series(media_domain);

CREATE TABLE IF NOT EXISTS oeuvre_magazine (
    oeuvre_id INTEGER PRIMARY KEY REFERENCES oeuvres(id) ON DELETE CASCADE,
    series_id INTEGER NOT NULL REFERENCES series(id) ON DELETE CASCADE,
    numero TEXT NOT NULL DEFAULT '',
    numero_ordre REAL NOT NULL DEFAULT 0,
    date_parution TEXT DEFAULT NULL,
    sommaire TEXT DEFAULT '',
    pages INTEGER NOT NULL DEFAULT 0,
    est_hors_serie INTEGER NOT NULL DEFAULT 0,
    stored_object_id INTEGER DEFAULT NULL REFERENCES stored_objects(id) ON DELETE SET NULL,
    pdf_text_preview TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_magazine_series ON oeuvre_magazine(series_id);
CREATE INDEX IF NOT EXISTS idx_oeuvre_magazine_series_ordre ON oeuvre_magazine(series_id, numero_ordre);

CREATE TABLE IF NOT EXISTS series_bibliotheque (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    series_id INTEGER NOT NULL REFERENCES series(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL,
    foyer_id INTEGER NOT NULL,
    statut TEXT NOT NULL DEFAULT 'collection',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_series_bib_foyer
    ON series_bibliotheque(series_id, foyer_id)
    WHERE statut = 'collection';

CREATE UNIQUE INDEX IF NOT EXISTS idx_series_bib_user
    ON series_bibliotheque(series_id, user_id)
    WHERE statut = 'wishlist';

CREATE INDEX IF NOT EXISTS idx_series_bib_series ON series_bibliotheque(series_id);

CREATE TABLE IF NOT EXISTS oeuvre_jeu (
    oeuvre_id INTEGER PRIMARY KEY REFERENCES oeuvres(id) ON DELETE CASCADE,
    studio TEXT NOT NULL DEFAULT '',
    editeur TEXT NOT NULL DEFAULT '',
    genre TEXT NOT NULL DEFAULT '',
    platform TEXT NOT NULL DEFAULT '',
    is_digital INTEGER NOT NULL DEFAULT 0,
    physical_supports TEXT NOT NULL DEFAULT '',
    digital_stores TEXT NOT NULL DEFAULT '',
    is_extension INTEGER NOT NULL DEFAULT 0,
    base_game_oeuvre_id INTEGER DEFAULT NULL REFERENCES oeuvres(id) ON DELETE SET NULL,
    is_remake INTEGER NOT NULL DEFAULT 0,
    original_game_oeuvre_id INTEGER DEFAULT NULL REFERENCES oeuvres(id) ON DELETE SET NULL,
    igdb_id INTEGER NOT NULL DEFAULT 0,
    igdb_enriched_at TEXT DEFAULT NULL,
    franchise TEXT NOT NULL DEFAULT '',
    game_mode TEXT NOT NULL DEFAULT '',
    theme TEXT NOT NULL DEFAULT '',
    alternative_names TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_platform ON oeuvre_jeu(platform);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_studio ON oeuvre_jeu(studio COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_genre ON oeuvre_jeu(genre COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_is_extension ON oeuvre_jeu(is_extension);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_base_game ON oeuvre_jeu(base_game_oeuvre_id);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_is_remake ON oeuvre_jeu(is_remake);
CREATE INDEX IF NOT EXISTS idx_oeuvre_jeu_original_game ON oeuvre_jeu(original_game_oeuvre_id);

CREATE TABLE IF NOT EXISTS game_attachment (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bibliotheque_id INTEGER NOT NULL REFERENCES bibliotheque(id) ON DELETE CASCADE,
    stored_object_id INTEGER NOT NULL REFERENCES stored_objects(id) ON DELETE CASCADE,
    label TEXT NOT NULL DEFAULT '',
    original_filename TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_game_attachment_bib ON game_attachment(bibliotheque_id);
CREATE UNIQUE INDEX IF NOT EXISTS idx_game_attachment_object ON game_attachment(stored_object_id);

CREATE TABLE IF NOT EXISTS magazine_subject (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    category TEXT NOT NULL,
    label TEXT NOT NULL,
    detail TEXT NOT NULL DEFAULT '',
    parution_year INTEGER NOT NULL DEFAULT 0,
    catalog_oeuvre_id INTEGER DEFAULT NULL REFERENCES oeuvres(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_magazine_subject_unique
    ON magazine_subject(category, label COLLATE NOCASE, detail COLLATE NOCASE, parution_year);

CREATE INDEX IF NOT EXISTS idx_magazine_subject_category ON magazine_subject(category);
CREATE INDEX IF NOT EXISTS idx_magazine_subject_label ON magazine_subject(label COLLATE NOCASE);
CREATE INDEX IF NOT EXISTS idx_magazine_subject_catalog_oeuvre ON magazine_subject(catalog_oeuvre_id);

CREATE TABLE IF NOT EXISTS oeuvre_magazine_subject (
    oeuvre_id INTEGER NOT NULL REFERENCES oeuvres(id) ON DELETE CASCADE,
    subject_id INTEGER NOT NULL REFERENCES magazine_subject(id) ON DELETE CASCADE,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    PRIMARY KEY (oeuvre_id, subject_id)
);

CREATE INDEX IF NOT EXISTS idx_oms_subject ON oeuvre_magazine_subject(subject_id);
CREATE INDEX IF NOT EXISTS idx_oms_oeuvre ON oeuvre_magazine_subject(oeuvre_id);
